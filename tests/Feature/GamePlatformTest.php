<?php

namespace Tests\Feature;

use App\Events\GameRoomUpdated;
use App\Models\CompanyProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class GamePlatformTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([GameRoomUpdated::class]);
    }

    public function test_each_subdomain_discovers_its_own_websocket_and_shared_database(): void
    {
        $this->publish('mahjong');
        $this->publish('explodingkitten');

        $mahjong = $this->getJson('http://mahjong.jadidaring.test/api/game/config')
            ->assertOk()
            ->assertJsonPath('game', 'mahjong')
            ->assertJsonPath('api', '/api/game')
            ->assertJsonPath('database.shared', true)
            ->assertJsonPath('database.isolatedBy', 'subdomain')
            ->assertJsonPath('websocket.enabled', true)
            ->assertJsonPath('websocket.key', 'test-reverb-key')
            ->assertJsonPath('websocket.channelPrefix', 'game.mahjong')
            ->assertJsonPath('websocket.lobbyChannel', 'game.mahjong')
            ->assertJsonPath('websocket.roomChannel', 'game.mahjong.{code}')
            ->assertJsonPath('websocket.event', 'room.updated')
            ->assertJsonPath('websocket.inputEvent', 'room.input');

        $this->assertStringContainsString(
            'ws://mahjong.jadidaring.test/app/test-reverb-key',
            (string) $mahjong->json('websocket.url'),
        );
        $this->assertStringNotContainsString('password', (string) $mahjong->getContent());
        $mahjong->assertJsonMissingPath('database.password');
        $mahjong->assertJsonMissingPath('database.host');

        $this->getJson('http://explodingkitten.jadidaring.test/api/game/config')
            ->assertOk()
            ->assertJsonPath('websocket.channelPrefix', 'game.explodingkitten')
            ->assertJsonPath('websocket.roomChannel', 'game.explodingkitten.{code}');

        CompanyProfile::factory()->create(['subdomain' => 'hidden']);

        $this->getJson('http://hidden.jadidaring.test/api/game/config')->assertNotFound();
        $this->getJson('http://admin.jadidaring.test/api/game/config')->assertNotFound();
    }

    public function test_rooms_stay_inside_the_game_subdomain_and_hide_other_players_hands(): void
    {
        $this->publish('mahjong');
        $this->publish('explodingkitten');

        $table = $this->postJson('http://mahjong.jadidaring.test/api/game/rooms', [
            'displayName' => 'Ada',
            'visibility' => 'public',
            'maxPlayers' => 4,
            'state' => ['wall' => 136],
            'private' => ['hand' => ['East']],
        ])->assertOk()->json();

        $this->assertSame(1, $table['version']);
        $this->assertTrue($table['host']);
        $this->assertSame(['hand' => ['East']], $table['private']);

        Event::assertDispatched(GameRoomUpdated::class, function (GameRoomUpdated $event) use ($table): bool {
            return $event->subdomain === 'mahjong'
                && $event->code === $table['code']
                && $event->version === 1
                && $event->broadcastAs() === 'room.updated'
                && collect($event->broadcastOn())->contains(fn ($channel): bool => $channel->name === 'game.mahjong')
                && collect($event->broadcastOn())->contains(fn ($channel): bool => $channel->name === 'game.mahjong.'.$table['code']);
        });

        $friend = $this->postJson('http://mahjong.jadidaring.test/api/game/rooms/join', [
            'code' => $table['code'],
            'displayName' => 'Bayu',
        ])->assertOk()->json();

        $this->assertNotSame($table['playerToken'], $friend['playerToken']);
        $this->assertSame([], $friend['private']);
        $this->assertSame(['wall' => 136], $friend['state']);
        $this->assertFalse(collect($friend['players'])->contains(fn (array $player): bool => isset($player['private']) || isset($player['hand'])));

        $this->getJson('http://explodingkitten.jadidaring.test/api/game/rooms')
            ->assertOk()
            ->assertJsonCount(0, 'rooms');

        $this->postJson('http://explodingkitten.jadidaring.test/api/game/rooms/join', [
            'code' => $table['code'],
            'displayName' => 'Sneak',
        ])->assertNotFound();

        $this->getJson('http://mahjong.jadidaring.test/api/game/rooms')
            ->assertOk()
            ->assertJsonPath('rooms.0.code', $table['code'])
            ->assertJsonPath('rooms.0.seated', 2);

        $this->getJson('http://mahjong.jadidaring.test/api/game/rooms/'.$table['code'])
            ->assertUnauthorized();

        $seen = $this->withToken($friend['playerToken'])
            ->getJson('http://mahjong.jadidaring.test/api/game/rooms/'.$table['code'].'?since='.$friend['version'])
            ->assertOk()
            ->json();

        $this->assertTrue($seen['unchanged']);
        $this->assertArrayNotHasKey('private', $seen);
    }

    public function test_actions_use_optimistic_versions_and_only_update_the_acting_player(): void
    {
        $this->publish('explodingkitten');

        $room = $this->postJson('http://explodingkitten.jadidaring.test/api/game/rooms', [
            'displayName' => 'Ada',
            'visibility' => 'private',
            'private' => ['cards' => ['defuse']],
        ])->assertOk()->json();

        $this->postJson('http://explodingkitten.jadidaring.test/api/game/rooms/'.$room['code'].'/actions', [
            'type' => 'draw',
            'expectedVersion' => 1,
            'state' => ['deck' => 40],
            'private' => ['cards' => ['defuse', 'skip']],
            'payload' => ['card' => 'skip'],
        ], [
            'Authorization' => 'Bearer '.$room['playerToken'],
        ])->assertOk()
            ->assertJsonPath('version', 2)
            ->assertJsonPath('state.deck', 40)
            ->assertJsonPath('private.cards.1', 'skip');

        $this->postJson('http://explodingkitten.jadidaring.test/api/game/rooms/'.$room['code'].'/actions', [
            'type' => 'draw',
            'expectedVersion' => 1,
            'state' => ['deck' => 1],
        ], [
            'Authorization' => 'Bearer '.$room['playerToken'],
        ])->assertStatus(409);

        $this->withToken('not-a-member')
            ->getJson('http://explodingkitten.jadidaring.test/api/game/rooms/'.$room['code'])
            ->assertForbidden();

        $refreshed = $this->withToken($room['playerToken'])
            ->getJson('http://explodingkitten.jadidaring.test/api/game/rooms/'.$room['code'])
            ->assertOk()
            ->json();

        $this->assertSame(40, $refreshed['state']['deck']);
        $this->assertSame(['defuse', 'skip'], $refreshed['private']['cards']);
    }

    public function test_matchmaking_fills_a_public_room_and_spectators_do_not_take_seats(): void
    {
        $this->publish('mahjong');

        $first = $this->postJson('http://mahjong.jadidaring.test/api/game/rooms/match', [
            'displayName' => 'Ada',
            'maxPlayers' => 2,
        ])->assertOk()->json();

        $second = $this->postJson('http://mahjong.jadidaring.test/api/game/rooms/match', [
            'displayName' => 'Bayu',
        ])->assertOk()->json();

        $this->assertSame($first['code'], $second['code']);
        $this->assertFalse($second['host']);

        $this->postJson('http://mahjong.jadidaring.test/api/game/rooms/join', [
            'code' => $first['code'],
            'displayName' => 'Cici',
        ])->assertStatus(409);

        $watcher = $this->postJson('http://mahjong.jadidaring.test/api/game/rooms/'.$first['code'].'/watch', [
            'displayName' => 'Deni',
        ])->assertOk()->json();

        $this->assertTrue($watcher['spectator']);
        $this->assertNull($watcher['players'][2]['seat']);

        $this->withToken($first['playerToken'])
            ->postJson('http://mahjong.jadidaring.test/api/game/rooms/'.$first['code'].'/leave')
            ->assertOk();

        $this->withToken($second['playerToken'])
            ->postJson('http://mahjong.jadidaring.test/api/game/rooms/'.$first['code'].'/leave')
            ->assertOk();

        $this->getJson('http://mahjong.jadidaring.test/api/game/rooms')
            ->assertOk()
            ->assertJsonCount(0, 'rooms');
    }

    private function publish(string $subdomain): void
    {
        CompanyProfile::factory()->published()->create([
            'subdomain' => $subdomain,
            'html' => '<html><body>'.$subdomain.'</body></html>',
        ]);
    }
}
