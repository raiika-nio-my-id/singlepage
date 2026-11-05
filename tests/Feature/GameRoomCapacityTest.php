<?php

namespace Tests\Feature;

use App\Events\GameRoomUpdated;
use App\Models\CompanyProfile;
use App\Models\GamePlayer;
use App\Models\GameRoom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class GameRoomCapacityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([GameRoomUpdated::class]);
    }

    public function test_config_keeps_ordinary_limits_and_raises_only_the_crowd_subdomains(): void
    {
        foreach (['coratcoret', 'explodingkitten', 'mahjong', 'duelular'] as $game) {
            $this->publish($game);

            $this->getJson('http://www.jadidaring.test/'.$game.'/api/game/config')
                ->assertOk()
                ->assertJsonPath('limits.maxPlayers', 16)
                ->assertJsonPath('limits.defaultMaxPlayers', 4)
                ->assertJsonPath('limits.spectators', 20);
        }

        foreach (['drawquarium', 'langitbersama'] as $game) {
            $this->publish($game);

            $this->getJson('http://www.jadidaring.test/'.$game.'/api/game/config')
                ->assertOk()
                ->assertJsonPath('limits.maxPlayers', 500)
                ->assertJsonPath('limits.defaultMaxPlayers', 500)
                ->assertJsonPath('limits.spectators', 500);
        }
    }

    public function test_ordinary_games_reject_more_than_sixteen_players_and_default_to_four(): void
    {
        $this->publish('coratcoret');

        $this->postJson('http://www.jadidaring.test/coratcoret/api/game/rooms', [
            'displayName' => 'Ada',
            'visibility' => 'public',
            'maxPlayers' => 17,
        ])->assertJsonValidationErrors(['maxPlayers']);

        $room = $this->postJson('http://www.jadidaring.test/coratcoret/api/game/rooms/match', [
            'displayName' => 'Ada',
        ])->assertOk()->json();

        $this->assertSame(4, $room['maxPlayers']);

        $this->postJson('http://www.jadidaring.test/coratcoret/api/game/rooms', [
            'displayName' => 'Bayu',
            'visibility' => 'private',
            'maxPlayers' => 16,
        ])->assertOk()->assertJsonPath('maxPlayers', 16);
    }

    public function test_crowd_subdomains_default_to_five_hundred_and_share_one_public_room(): void
    {
        foreach (['drawquarium', 'langitbersama'] as $game) {
            $this->publish($game);

            $this->postJson('http://www.jadidaring.test/'.$game.'/api/game/rooms', [
                'displayName' => 'Ada',
                'visibility' => 'public',
                'maxPlayers' => 501,
            ])->assertJsonValidationErrors(['maxPlayers']);

            $created = $this->postJson('http://www.jadidaring.test/'.$game.'/api/game/rooms/match', [
                'displayName' => 'Ada',
            ])->assertOk()->json();

            $this->assertSame(500, $created['maxPlayers']);

            $joined = $this->postJson('http://www.jadidaring.test/'.$game.'/api/game/rooms/match', [
                'displayName' => 'Bayu',
                'maxPlayers' => 16,
            ])->assertOk()->json();

            $this->assertSame($created['code'], $joined['code']);
            $this->assertSame(500, $joined['maxPlayers']);

            $explicit = $this->postJson('http://www.jadidaring.test/'.$game.'/api/game/rooms', [
                'displayName' => 'Cici',
                'visibility' => 'private',
                'maxPlayers' => 500,
            ])->assertOk()->json();

            $this->assertSame(500, $explicit['maxPlayers']);
            $this->assertNotSame($created['code'], $explicit['code']);
        }
    }

    public function test_an_explicit_small_cap_on_a_crowd_game_is_stored_on_that_room(): void
    {
        $this->publish('drawquarium');

        $room = $this->postJson('http://www.jadidaring.test/drawquarium/api/game/rooms', [
            'displayName' => 'Ada',
            'visibility' => 'public',
            'maxPlayers' => 4,
        ])->assertOk()->json();

        $this->assertSame(4, $room['maxPlayers']);
    }

    public function test_ordinary_games_still_stop_at_twenty_spectators(): void
    {
        $this->publish('mahjong');

        $room = $this->postJson('http://www.jadidaring.test/mahjong/api/game/rooms/match', [
            'displayName' => 'Ada',
            'maxPlayers' => 2,
        ])->assertOk()->json();

        $this->fillMembers($room['roomId'], 20, true);

        $this->postJson('http://www.jadidaring.test/mahjong/api/game/rooms/'.$room['code'].'/watch', [
            'displayName' => 'Deni',
        ])->assertStatus(409)
            ->assertJsonPath('error', 'This room cannot take more spectators.');
    }

    public function test_crowd_games_accept_spectators_past_twenty_and_stop_at_five_hundred(): void
    {
        $this->publish('langitbersama');

        $room = $this->postJson('http://www.jadidaring.test/langitbersama/api/game/rooms/match', [
            'displayName' => 'Ada',
        ])->assertOk()->json();

        $this->fillMembers($room['roomId'], 20, true);

        $this->postJson('http://www.jadidaring.test/langitbersama/api/game/rooms/'.$room['code'].'/watch', [
            'displayName' => 'Deni',
        ])->assertOk()->assertJsonPath('spectator', true);

        $this->fillMembers($room['roomId'], 479, true, 0, 20);

        $this->postJson('http://www.jadidaring.test/langitbersama/api/game/rooms/'.$room['code'].'/watch', [
            'displayName' => 'Eka',
        ])->assertStatus(409)
            ->assertJsonPath('error', 'This room cannot take more spectators.');
    }

    public function test_crowd_match_opens_another_room_only_after_five_hundred_seats(): void
    {
        $this->publish('drawquarium');

        $first = $this->postJson('http://www.jadidaring.test/drawquarium/api/game/rooms/match', [
            'displayName' => 'Ada',
        ])->assertOk()->json();

        $this->assertSame(500, $first['maxPlayers']);
        $this->fillMembers($first['roomId'], 499, false, 1);

        $this->assertSame(500, GameRoom::query()->findOrFail($first['roomId'])->players()->whereNull('left_at')->where('is_spectator', false)->count());

        $second = $this->postJson('http://www.jadidaring.test/drawquarium/api/game/rooms/match', [
            'displayName' => 'Bayu',
        ])->assertOk()->json();

        $this->assertNotSame($first['code'], $second['code']);
        $this->assertSame(500, $second['maxPlayers']);
    }

    public function test_backfill_raises_live_public_rooms_only_for_the_crowd_subdomains(): void
    {
        $this->publish('drawquarium');
        $this->publish('langitbersama');
        $this->publish('coratcoret');

        $drawWaiting = $this->openPublic('drawquarium', 'Ada', 4, 'waiting');
        $drawPlaying = $this->openPublic('drawquarium', 'Bayu', 8, 'playing');
        $drawPrivate = $this->postJson('http://www.jadidaring.test/drawquarium/api/game/rooms', [
            'displayName' => 'Cici',
            'visibility' => 'private',
            'maxPlayers' => 4,
        ])->assertOk()->json();
        $langit = $this->openPublic('langitbersama', 'Deni', 4, 'waiting');
        $ordinary = $this->openPublic('coratcoret', 'Eka', 4, 'waiting');

        $migration = require database_path('migrations/2026_11_01_000001_widen_game_room_capacity_for_crowd_subdomains.php');
        $migration->backfillCrowdRooms();

        $this->assertSame(500, GameRoom::query()->findOrFail($drawWaiting['roomId'])->max_players);
        $this->assertSame(500, GameRoom::query()->findOrFail($drawPlaying['roomId'])->max_players);
        $this->assertSame(4, GameRoom::query()->findOrFail($drawPrivate['roomId'])->max_players);
        $this->assertSame(500, GameRoom::query()->findOrFail($langit['roomId'])->max_players);
        $this->assertSame(4, GameRoom::query()->findOrFail($ordinary['roomId'])->max_players);
    }

    public function test_capacity_columns_store_a_crowd_seat(): void
    {
        $this->publish('drawquarium');

        $room = $this->postJson('http://www.jadidaring.test/drawquarium/api/game/rooms/match', [
            'displayName' => 'Ada',
        ])->assertOk()->json();

        $player = GamePlayer::query()->create([
            'game_room_id' => $room['roomId'],
            'display_name' => 'Far',
            'seat' => 499,
            'token_hash' => hash('sha256', 'seat-499'),
            'is_host' => false,
            'is_spectator' => false,
            'is_gamemaster' => false,
            'private_state' => [],
        ]);

        $this->assertSame(500, GameRoom::query()->findOrFail($room['roomId'])->max_players);
        $this->assertSame(499, $player->fresh()->seat);
    }

    /**
     * @return array<string, mixed>
     */
    private function openPublic(string $subdomain, string $name, int $maxPlayers, string $status): array
    {
        $room = $this->postJson('http://www.jadidaring.test/'.$subdomain.'/api/game/rooms', [
            'displayName' => $name,
            'visibility' => 'public',
            'maxPlayers' => $maxPlayers,
        ])->assertOk()->json();

        if ($status !== 'waiting') {
            GameRoom::query()->whereKey($room['roomId'])->update(['status' => $status]);
        }

        return $room;
    }

    private function publish(string $subdomain): void
    {
        CompanyProfile::factory()->published()->create([
            'subdomain' => $subdomain,
            'html' => '<html><body>'.$subdomain.'</body></html>',
        ]);
    }

    private function fillMembers(int $roomId, int $count, bool $spectator, int $startingSeat = 0, int $indexOffset = 0): void
    {
        $now = now()->toDateTimeString();
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $index = $indexOffset + $i;
            $rows[] = [
                'game_room_id' => $roomId,
                'display_name' => ($spectator ? 'Watcher ' : 'Player ').$index,
                'seat' => $spectator ? null : $startingSeat + $i,
                'token_hash' => hash('sha256', $roomId.'-'.($spectator ? 's' : 'p').'-'.$index),
                'is_host' => false,
                'is_spectator' => $spectator,
                'is_gamemaster' => false,
                'private_state' => null,
                'left_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            GamePlayer::query()->insert($chunk);
        }
    }
}
