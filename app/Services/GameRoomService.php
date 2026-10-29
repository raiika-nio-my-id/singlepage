<?php

namespace App\Services;

use App\Events\GameRoomUpdated;
use App\Exceptions\GameRoomException;
use App\Models\CompanyProfile;
use App\Models\GamePlayer;
use App\Models\GameRoom;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class GameRoomService
{
    /**
     * Broadcasts collected inside a transaction and sent only after commit.
     *
     * @var array<int, array{subdomain: string, code: string, version: int}>
     */
    private array $pendingBroadcasts = [];

    /**
     * @return array<string, mixed>
     */
    public function describe(Request $request, CompanyProfile $profile): array
    {
        $key = (string) config('broadcasting.connections.reverb.key');
        $prefix = 'game.'.$profile->subdomain;
        $scheme = $request->getScheme() === 'https' ? 'wss' : 'ws';

        return [
            'game' => $profile->subdomain,
            'host' => $request->getHost(),
            'api' => '/api/game',
            'database' => [
                'shared' => true,
                'isolatedBy' => 'subdomain',
            ],
            'websocket' => [
                'enabled' => $key !== '',
                'url' => $key === ''
                    ? null
                    : $scheme.'://'.$request->getHttpHost().'/app/'.$key.'?protocol=7&client=jadidaring&version=1.0',
                'key' => $key === '' ? null : $key,
                'channelPrefix' => $prefix,
                'lobbyChannel' => $prefix,
                'roomChannel' => $prefix.'.{code}',
                'event' => 'room.updated',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function list(CompanyProfile $profile, bool $includePrivate = false): array
    {
        $rooms = GameRoom::query()
            ->where('company_profile_id', $profile->id)
            ->when(! $includePrivate, fn ($query) => $query->where('visibility', 'public'))
            ->whereIn('status', ['waiting', 'playing'])
            ->withCount([
                'players as seated_count' => fn ($query) => $query->whereNull('left_at')->where('is_spectator', false),
                'players as spectator_count' => fn ($query) => $query->whereNull('left_at')->where('is_spectator', true),
            ])
            ->latest('updated_at')
            ->limit(30)
            ->get();

        return [
            'rooms' => $rooms->map(fn (GameRoom $room): array => [
                'code' => $room->code,
                'status' => $room->status,
                'visibility' => $room->visibility,
                'seated' => (int) $room->seated_count,
                'spectators' => (int) $room->spectator_count,
                'maxPlayers' => $room->max_players,
                'version' => $room->version,
                'updatedAt' => $room->updated_at?->toIso8601String(),
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function create(CompanyProfile $profile, array $input): array
    {
        $data = $this->validate($input, [
            'displayName' => ['required', 'string', 'max:40'],
            'visibility' => ['sometimes', 'in:public,private'],
            'maxPlayers' => ['sometimes', 'integer', 'min:2', 'max:16'],
            'state' => ['sometimes', 'array'],
            'private' => ['sometimes', 'array'],
        ]);

        return $this->transaction(fn (): array => $this->openRoom($profile, $data));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function join(CompanyProfile $profile, array $input): array
    {
        $data = $this->validate($input, [
            'code' => ['required', 'string', 'max:12'],
            'displayName' => ['required', 'string', 'max:40'],
            'private' => ['sometimes', 'array'],
        ]);

        return $this->transaction(function () use ($profile, $data): array {
            $room = $this->lockRoom($profile, $this->code($data['code']));

            if ($room->status !== 'waiting') {
                throw new GameRoomException('This room is not open for new players.');
            }

            if ($this->seatedCount($room) >= $room->max_players) {
                throw new GameRoomException('This room is full.');
            }

            return $this->seat($room, $profile, $data['displayName'], false, $data['private'] ?? []);
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function match(CompanyProfile $profile, array $input): array
    {
        $data = $this->validate($input, [
            'displayName' => ['required', 'string', 'max:40'],
            'maxPlayers' => ['sometimes', 'integer', 'min:2', 'max:16'],
            'state' => ['sometimes', 'array'],
            'private' => ['sometimes', 'array'],
        ]);
        $data['visibility'] = 'public';

        return $this->transaction(function () use ($profile, $data): array {
            $room = $this->findPublicSeat($profile);

            if (! $room instanceof GameRoom) {
                return $this->openRoom($profile, $data);
            }

            return $this->seat($room, $profile, $data['displayName'], false, $data['private'] ?? []);
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function watch(CompanyProfile $profile, string $code, array $input): array
    {
        $data = $this->validate($input, [
            'displayName' => ['required', 'string', 'max:40'],
        ]);

        return $this->transaction(function () use ($profile, $code, $data): array {
            $room = $this->lockRoom($profile, $this->code($code));

            if ($room->status === 'closed') {
                throw new GameRoomException('This room is closed.');
            }

            $spectators = $room->players()->whereNull('left_at')->where('is_spectator', true)->count();

            if ($spectators >= (int) config('games.spectator_limit')) {
                throw new GameRoomException('This room cannot take more spectators.');
            }

            return $this->seat($room, $profile, $data['displayName'], true, []);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function show(CompanyProfile $profile, string $code, string $token, ?int $since): array
    {
        $room = $this->findRoom($profile, $this->code($code));
        $player = $this->player($room, $token);

        if ($since !== null && $since === $room->version) {
            return [
                'unchanged' => true,
                'code' => $room->code,
                'version' => $room->version,
            ];
        }

        return $this->present($room, $player, null, $since);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function act(CompanyProfile $profile, string $code, string $token, array $input): array
    {
        $data = $this->validate($input, [
            'type' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_.:-]+$/'],
            'payload' => ['sometimes', 'array'],
            'state' => ['sometimes', 'array'],
            'private' => ['sometimes', 'array'],
            'status' => ['sometimes', 'in:waiting,playing,closed'],
            'expectedVersion' => ['required', 'integer', 'min:0'],
        ]);

        return $this->transaction(function () use ($profile, $code, $token, $data): array {
            $room = $this->lockRoom($profile, $this->code($code));
            $player = $this->player($room, $token);

            if ($room->status === 'closed') {
                throw new GameRoomException('This room is closed.');
            }

            if ((int) $data['expectedVersion'] !== $room->version) {
                throw new GameRoomException('The room changed. Refresh and try again.');
            }

            if (array_key_exists('state', $data)) {
                $this->assertDocument($data['state'], 'state', (int) config('games.state_max_bytes'));
                $room->state = $data['state'];
            }

            if (array_key_exists('private', $data)) {
                $this->assertDocument($data['private'], 'private', (int) config('games.private_max_bytes'));
                $player->private_state = $data['private'];
                $player->save();
            }

            if (array_key_exists('status', $data)) {
                $room->status = $data['status'];
            }

            $payload = $data['payload'] ?? [];
            $this->assertDocument($payload, 'payload', (int) config('games.payload_max_bytes'));
            $this->record($room, $profile, $player, $data['type'], $payload);

            return $this->present($room->refresh(), $player->refresh());
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function leave(CompanyProfile $profile, string $code, string $token): array
    {
        return $this->transaction(function () use ($profile, $code, $token): array {
            $room = $this->lockRoom($profile, $this->code($code));
            $player = $this->player($room, $token);
            $player->forceFill([
                'left_at' => now(),
                'is_host' => false,
                'is_gamemaster' => false,
            ])->save();

            $nextHost = $room->players()
                ->whereNull('left_at')
                ->where('is_spectator', false)
                ->orderBy('seat')
                ->first();

            if ($nextHost instanceof GamePlayer) {
                $nextHost->forceFill(['is_host' => true])->save();
            } elseif ($room->players()->whereNull('left_at')->where('is_spectator', false)->doesntExist()) {
                $room->status = 'closed';
            }

            $this->record($room, $profile, $player, 'leave', []);

            return ['ok' => true, 'version' => $room->version];
        });
    }

    public function token(Request $request): string
    {
        $token = (string) ($request->bearerToken() ?: $request->header('X-Player-Token', ''));

        if ($token === '') {
            throw new GameRoomException('A player token is required.', 401);
        }

        return $token;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function claimGameMaster(CompanyProfile $profile, string $code, string $token, array $input): array
    {
        $data = $this->validate($input, [
            'code' => ['required', 'string', 'max:128'],
        ]);

        return $this->transaction(function () use ($profile, $code, $token, $data): array {
            $room = $this->lockRoom($profile, $this->code($code));
            $player = $this->player($room, $token);

            if ($room->status === 'closed') {
                throw new GameRoomException('This room is closed.');
            }

            if (! $this->matchesGameMasterSecret($data['code'])) {
                throw new GameRoomException('Invalid game master code.', 403);
            }

            if (! $player->is_gamemaster) {
                $room->players()
                    ->where('id', '!=', $player->id)
                    ->where('is_gamemaster', true)
                    ->update(['is_gamemaster' => false]);

                $player->forceFill(['is_gamemaster' => true])->save();
                $this->record($room, $profile, $player, 'gamemaster', []);
            }

            return $this->present($room->refresh(), $player->refresh());
        });
    }

    public function isGameMaster(Request $request): bool
    {
        foreach ([$request->query('gm'), $request->header('X-Game-Master')] as $candidate) {
            if ($this->matchesGameMasterSecret(is_string($candidate) ? $candidate : null)) {
                return true;
            }
        }

        return false;
    }

    private function matchesGameMasterSecret(?string $candidate): bool
    {
        $secret = Setting::gamemasterSecret();

        if ($secret === '' || $candidate === null) {
            return false;
        }

        $candidate = trim($candidate);

        return $candidate !== '' && hash_equals($secret, $candidate);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function openRoom(CompanyProfile $profile, array $data): array
    {
        $state = $data['state'] ?? [];
        $private = $data['private'] ?? [];
        $this->assertDocument($state, 'state', (int) config('games.state_max_bytes'));
        $this->assertDocument($private, 'private', (int) config('games.private_max_bytes'));

        $room = $this->insertRoom($profile, [
            'visibility' => $data['visibility'] ?? 'private',
            'max_players' => (int) ($data['maxPlayers'] ?? 4),
            'state' => $state,
        ]);

        return $this->seat($room, $profile, $data['displayName'], false, $private, 'create');
    }

    /**
     * @param  array<string, mixed>  $private
     * @return array<string, mixed>
     */
    private function seat(GameRoom $room, CompanyProfile $profile, string $name, bool $spectator, array $private, string $type = 'join'): array
    {
        $this->assertDocument($private, 'private', (int) config('games.private_max_bytes'));
        $issued = $this->addPlayer($room, $this->displayName($name), $spectator, $private);
        $this->record($room, $profile, $issued['player'], $type, []);

        return $this->present($room->refresh(), $issued['player']->refresh(), $issued['token']);
    }

    /**
     * @param  array{visibility: string, max_players: int, state: array<mixed>}  $attributes
     */
    private function insertRoom(CompanyProfile $profile, array $attributes): GameRoom
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = $this->makeCode();
            $taken = GameRoom::query()
                ->where('company_profile_id', $profile->id)
                ->where('code', $code)
                ->exists();

            if ($taken) {
                continue;
            }

            return GameRoom::query()->create([
                'company_profile_id' => $profile->id,
                'code' => $code,
                'visibility' => $attributes['visibility'],
                'status' => 'waiting',
                'max_players' => $attributes['max_players'],
                'state' => $attributes['state'],
                'version' => 0,
            ]);
        }

        throw new GameRoomException('Could not open a room.', 500);
    }

    /**
     * @param  array<mixed>  $private
     * @return array{player: GamePlayer, token: string}
     */
    private function addPlayer(GameRoom $room, string $name, bool $spectator, array $private): array
    {
        $token = Str::random(48);
        $seat = null;
        $host = false;

        if (! $spectator) {
            $maxSeat = $room->players()->max('seat');
            $seat = $maxSeat === null ? 0 : ((int) $maxSeat + 1);
            $host = $this->seatedCount($room) === 0;
        }

        $player = GamePlayer::query()->create([
            'game_room_id' => $room->id,
            'display_name' => $name,
            'seat' => $seat,
            'token_hash' => hash('sha256', $token),
            'is_host' => $host,
            'is_spectator' => $spectator,
            'is_gamemaster' => false,
            'private_state' => $private,
        ]);

        return ['player' => $player, 'token' => $token];
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function record(GameRoom $room, CompanyProfile $profile, ?GamePlayer $player, string $type, array $payload): void
    {
        $room->version++;
        $room->save();

        $room->events()->create([
            'game_player_id' => $player?->id,
            'version' => $room->version,
            'type' => $type,
            'payload' => $payload,
        ]);

        $this->pendingBroadcasts[] = [
            'subdomain' => $profile->subdomain,
            'code' => $room->code,
            'version' => $room->version,
        ];
    }

    /**
     * @param  callable(): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    private function transaction(callable $callback): array
    {
        try {
            $result = DB::transaction($callback);
        } catch (Throwable $exception) {
            $this->pendingBroadcasts = [];

            throw $exception;
        }

        $this->flushBroadcasts();

        return $result;
    }

    private function flushBroadcasts(): void
    {
        $pending = $this->pendingBroadcasts;
        $this->pendingBroadcasts = [];

        foreach ($pending as $event) {
            try {
                broadcast(new GameRoomUpdated($event['subdomain'], $event['code'], $event['version']));
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function present(GameRoom $room, GamePlayer $player, ?string $token = null, ?int $since = null): array
    {
        $players = $room->players()
            ->whereNull('left_at')
            ->get()
            ->sort(function (GamePlayer $left, GamePlayer $right): int {
                $seat = ($left->seat === null) <=> ($right->seat === null);

                return $seat !== 0 ? $seat : (($left->seat ?? 0) <=> ($right->seat ?? 0));
            })
            ->values();

        $events = $room->events()
            ->when($since !== null, fn ($query) => $query->where('version', '>', $since))
            ->orderByDesc('version')
            ->limit(50)
            ->get()
            ->sortBy('version')
            ->values();

        $body = [
            'roomId' => $room->id,
            'code' => $room->code,
            'visibility' => $room->visibility,
            'status' => $room->status,
            'version' => $room->version,
            'maxPlayers' => $room->max_players,
            'host' => $player->is_host,
            'spectator' => $player->is_spectator,
            'gamemaster' => $player->is_gamemaster,
            'playerId' => $player->id,
            'state' => $room->state ?? [],
            'private' => $player->private_state ?? [],
            'players' => $players->map(fn (GamePlayer $member): array => [
                'id' => $member->id,
                'displayName' => $member->display_name,
                'seat' => $member->seat,
                'host' => $member->is_host,
                'spectator' => $member->is_spectator,
                'gamemaster' => $member->is_gamemaster,
            ])->all(),
            'events' => $events->map(fn ($event): array => [
                'version' => $event->version,
                'type' => $event->type,
                'payload' => $event->payload ?? [],
                'playerId' => $event->game_player_id,
                'createdAt' => $event->created_at?->toIso8601String(),
            ])->all(),
            'websocket' => [
                'channel' => 'game.'.$room->profile()->value('subdomain').'.'.$room->code,
                'event' => 'room.updated',
            ],
            'updatedAt' => $room->updated_at?->toIso8601String(),
        ];

        if ($token !== null) {
            $body['playerToken'] = $token;
        }

        return $body;
    }

    private function findPublicSeat(CompanyProfile $profile): ?GameRoom
    {
        $rooms = GameRoom::query()
            ->where('company_profile_id', $profile->id)
            ->where('visibility', 'public')
            ->where('status', 'waiting')
            ->orderBy('id')
            ->lockForUpdate()
            ->limit(25)
            ->get();

        foreach ($rooms as $room) {
            if ($this->seatedCount($room) < $room->max_players) {
                return $room;
            }
        }

        return null;
    }

    private function lockRoom(CompanyProfile $profile, string $code): GameRoom
    {
        $room = GameRoom::query()
            ->where('company_profile_id', $profile->id)
            ->where('code', $code)
            ->lockForUpdate()
            ->first();

        if (! $room instanceof GameRoom) {
            throw new GameRoomException('Room not found.', 404);
        }

        return $room;
    }

    private function findRoom(CompanyProfile $profile, string $code): GameRoom
    {
        $room = GameRoom::query()
            ->where('company_profile_id', $profile->id)
            ->where('code', $code)
            ->first();

        if (! $room instanceof GameRoom) {
            throw new GameRoomException('Room not found.', 404);
        }

        return $room;
    }

    private function player(GameRoom $room, string $token): GamePlayer
    {
        $player = $room->players()
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('left_at')
            ->first();

        if (! $player instanceof GamePlayer) {
            throw new GameRoomException('You are not a member of this room.', 403);
        }

        return $player;
    }

    private function seatedCount(GameRoom $room): int
    {
        return $room->players()->whereNull('left_at')->where('is_spectator', false)->count();
    }

    private function code(string $code): string
    {
        $code = strtoupper(trim($code));

        if (! preg_match('/^[A-Z2-9]{6}$/', $code)) {
            throw new GameRoomException('Room code is invalid.', 422);
        }

        return $code;
    }

    private function makeCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';

        for ($i = 0; $i < 6; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }

    private function displayName(string $name): string
    {
        $name = trim((string) preg_replace('/\s+/', ' ', $name));

        if ($name === '') {
            throw new GameRoomException('A display name is required.', 422);
        }

        return $name;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function validate(array $input, array $rules): array
    {
        if (array_key_exists('displayName', $input) && is_string($input['displayName'])) {
            $input['displayName'] = trim((string) preg_replace('/\s+/', ' ', $input['displayName']));
        }

        /** @var array<string, mixed> $data */
        $data = Validator::make($input, $rules)->validate();

        return $data;
    }

    /**
     * @param  array<mixed>  $document
     */
    private function assertDocument(array $document, string $field, int $maxBytes): void
    {
        $json = json_encode($document);

        if ($json === false || strlen($json) > $maxBytes) {
            throw new GameRoomException(ucfirst($field).' is too large.', 422);
        }
    }
}
