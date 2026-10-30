<?php

namespace Tests\Feature;

use App\Events\GameRoomInput;
use App\Events\GameRoomUpdated;
use App\Models\CompanyProfile;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class GameRoomInputTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([GameRoomUpdated::class, GameRoomInput::class]);
    }

    public function test_returns_202_and_broadcasts_room_input_without_changing_the_room(): void
    {
        $this->publish('ular');
        $this->travelTo('2026-10-30 06:57:00');

        $room = $this->postJson('http://ular.jadidaring.test/api/game/rooms', [
            'displayName' => 'Ada',
            'visibility' => 'public',
            'state' => ['wall' => 1],
            'private' => ['hand' => ['secret']],
        ])->assertOk()->json();

        $response = $this->withToken($room['playerToken'])
            ->postJson('http://ular.jadidaring.test/api/game/rooms/'.$room['code'].'/input', [
                'direction' => '  up  ',
                'key' => 'ArrowUp',
                'payload' => ['seq' => 1],
                'playerToken' => $room['playerToken'],
                'private' => ['hand' => ['secret']],
            ])
            ->assertAccepted();

        $response->assertExactJson([
            'ok' => true,
            'id' => $response->json('id'),
            'at' => '2026-10-30T06:57:00+00:00',
        ]);
        $this->assertTrue(Str::isUuid((string) $response->json('id')));

        Event::assertDispatchedTimes(GameRoomUpdated::class, 1);
        Event::assertDispatched(GameRoomInput::class, function (GameRoomInput $event) use ($room, $response): bool {
            $payload = $event->broadcastWith();

            return $event->subdomain === 'ular'
                && $event->code === $room['code']
                && $event->broadcastAs() === 'room.input'
                && $payload === [
                    'code' => $room['code'],
                    'playerId' => $room['playerId'],
                    'displayName' => 'Ada',
                    'input' => [
                        'direction' => 'up',
                        'key' => 'ArrowUp',
                        'payload' => ['seq' => 1],
                    ],
                    'id' => $response->json('id'),
                    'at' => '2026-10-30T06:57:00+00:00',
                ]
                && ! array_key_exists('version', $payload)
                && ! array_key_exists('private', $payload)
                && ! array_key_exists('playerToken', $payload)
                && count($event->broadcastOn()) === 1
                && $event->broadcastOn()[0] instanceof Channel
                && ! $event->broadcastOn()[0] instanceof PrivateChannel
                && $event->broadcastOn()[0]->name === 'game.ular.'.$room['code'];
        });

        $this->withHeader('X-Player-Token', $room['playerToken'])
            ->postJson('http://ular.jadidaring.test/api/game/rooms/'.$room['code'].'/input', [
                'key' => 'w',
            ])
            ->assertAccepted()
            ->assertJsonPath('at', '2026-10-30T06:57:00+00:00');

        Event::assertDispatchedTimes(GameRoomInput::class, 2);
        Event::assertDispatchedTimes(GameRoomUpdated::class, 1);

        $seen = $this->withToken($room['playerToken'])
            ->getJson('http://ular.jadidaring.test/api/game/rooms/'.$room['code'])
            ->assertOk()
            ->json();

        $this->assertSame(1, $seen['version']);
        $this->assertSame(['wall' => 1], $seen['state']);
        $this->assertSame(['hand' => ['secret']], $seen['private']);
        $this->assertSame(['create'], array_column($seen['events'], 'type'));
        $this->assertDatabaseCount('game_events', 1);
    }

    public function test_returns_401_when_the_player_token_is_missing(): void
    {
        $this->publish('ular');
        $room = $this->openRoom('ular', 'Ada');

        $this->postJson('http://ular.jadidaring.test/api/game/rooms/'.$room['code'].'/input', [
            'direction' => 'up',
        ])->assertUnauthorized()
            ->assertJsonPath('error', 'A player token is required.');

        Event::assertNotDispatched(GameRoomInput::class);
    }

    public function test_returns_403_when_the_token_is_not_a_member(): void
    {
        $this->publish('ular');
        $room = $this->openRoom('ular', 'Ada');

        $this->withToken('not-a-member')
            ->postJson('http://ular.jadidaring.test/api/game/rooms/'.$room['code'].'/input', [
                'direction' => 'left',
            ])
            ->assertForbidden()
            ->assertJsonPath('error', 'You are not a member of this room.');

        Event::assertNotDispatched(GameRoomInput::class);
    }

    public function test_returns_403_after_the_player_leaves(): void
    {
        $this->publish('ular');
        $room = $this->openRoom('ular', 'Ada');

        $this->withToken($room['playerToken'])
            ->postJson('http://ular.jadidaring.test/api/game/rooms/'.$room['code'].'/leave')
            ->assertOk();

        $this->withToken($room['playerToken'])
            ->postJson('http://ular.jadidaring.test/api/game/rooms/'.$room['code'].'/input', [
                'direction' => 'up',
            ])
            ->assertForbidden()
            ->assertJsonPath('error', 'You are not a member of this room.');

        Event::assertNotDispatched(GameRoomInput::class);
        $this->assertDatabaseHas('game_rooms', [
            'code' => $room['code'],
            'version' => 2,
        ]);
    }

    public function test_returns_404_for_a_room_on_another_subdomain(): void
    {
        $this->publish('ular');
        $this->publish('other');
        $room = $this->openRoom('ular', 'Ada');

        $this->withToken($room['playerToken'])
            ->postJson('http://other.jadidaring.test/api/game/rooms/'.$room['code'].'/input', [
                'direction' => 'up',
            ])
            ->assertNotFound();

        Event::assertNotDispatched(GameRoomInput::class);
    }

    public function test_returns_404_when_the_room_does_not_exist(): void
    {
        $this->publish('ular');
        $room = $this->openRoom('ular', 'Ada');

        $this->withToken($room['playerToken'])
            ->postJson('http://ular.jadidaring.test/api/game/rooms/ABCDEF/input', [
                'direction' => 'up',
            ])
            ->assertNotFound()
            ->assertJsonPath('error', 'Room not found.');
    }

    public function test_returns_422_when_the_room_code_is_invalid(): void
    {
        $this->publish('ular');
        $room = $this->openRoom('ular', 'Ada');

        $this->withToken($room['playerToken'])
            ->postJson('http://ular.jadidaring.test/api/game/rooms/nope/input', [
                'direction' => 'up',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'Room code is invalid.');
    }

    public function test_returns_422_when_input_is_empty(): void
    {
        $this->publish('ular');
        $room = $this->openRoom('ular', 'Ada');

        $this->withToken($room['playerToken'])
            ->postJson('http://ular.jadidaring.test/api/game/rooms/'.$room['code'].'/input', [])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'Input is required.');

        $this->withToken($room['playerToken'])
            ->postJson('http://ular.jadidaring.test/api/game/rooms/'.$room['code'].'/input', [
                'direction' => '   ',
                'key' => '',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'Input is required.');

        Event::assertNotDispatched(GameRoomInput::class);
        $this->assertSame(1, $room['version']);
        $this->assertDatabaseHas('game_rooms', [
            'code' => $room['code'],
            'version' => 1,
        ]);
    }

    public function test_returns_422_when_direction_is_not_a_string(): void
    {
        $this->publish('ular');
        $room = $this->openRoom('ular', 'Ada');

        $this->withToken($room['playerToken'])
            ->postJson('http://ular.jadidaring.test/api/game/rooms/'.$room['code'].'/input', [
                'direction' => ['up'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['direction']);

        Event::assertNotDispatched(GameRoomInput::class);
    }

    public function test_returns_422_when_direction_or_key_is_too_long(): void
    {
        $this->publish('ular');
        $room = $this->openRoom('ular', 'Ada');

        $this->withToken($room['playerToken'])
            ->postJson('http://ular.jadidaring.test/api/game/rooms/'.$room['code'].'/input', [
                'direction' => str_repeat('u', 33),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['direction']);

        $this->withToken($room['playerToken'])
            ->postJson('http://ular.jadidaring.test/api/game/rooms/'.$room['code'].'/input', [
                'key' => str_repeat('k', 65),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['key']);

        Event::assertNotDispatched(GameRoomInput::class);
    }

    public function test_returns_422_when_payload_is_not_an_object(): void
    {
        $this->publish('ular');
        $room = $this->openRoom('ular', 'Ada');

        $this->withToken($room['playerToken'])
            ->postJson('http://ular.jadidaring.test/api/game/rooms/'.$room['code'].'/input', [
                'payload' => 'up',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payload']);

        Event::assertNotDispatched(GameRoomInput::class);
    }

    public function test_returns_422_when_input_is_too_large(): void
    {
        $this->publish('ular');
        $room = $this->openRoom('ular', 'Ada');

        $this->withToken($room['playerToken'])
            ->postJson('http://ular.jadidaring.test/api/game/rooms/'.$room['code'].'/input', [
                'payload' => ['blob' => str_repeat('x', (int) config('games.input_max_bytes'))],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'Input is too large.');

        Event::assertNotDispatched(GameRoomInput::class);
        $this->assertDatabaseHas('game_rooms', [
            'code' => $room['code'],
            'version' => 1,
        ]);
    }

    public function test_returns_409_when_the_room_is_closed_and_does_not_bump_version(): void
    {
        $this->publish('ular');
        $room = $this->openRoom('ular', 'Ada');

        $this->withToken($room['playerToken'])
            ->postJson('http://ular.jadidaring.test/api/game/rooms/'.$room['code'].'/actions', [
                'type' => 'close',
                'status' => 'closed',
                'expectedVersion' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('version', 2);

        $this->withToken($room['playerToken'])
            ->postJson('http://ular.jadidaring.test/api/game/rooms/'.$room['code'].'/input', [
                'direction' => 'up',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error', 'This room is closed.');

        Event::assertDispatchedTimes(GameRoomUpdated::class, 2);
        Event::assertNotDispatched(GameRoomInput::class);
        $this->assertDatabaseHas('game_rooms', [
            'code' => $room['code'],
            'version' => 2,
        ]);
    }

    public function test_a_spectator_can_send_room_input(): void
    {
        $this->publish('ular');
        $room = $this->openRoom('ular', 'Ada');

        $watcher = $this->postJson('http://ular.jadidaring.test/api/game/rooms/'.$room['code'].'/watch', [
            'displayName' => 'Deni',
        ])->assertOk()->json();

        $this->withToken($watcher['playerToken'])
            ->postJson('http://ular.jadidaring.test/api/game/rooms/'.$room['code'].'/input', [
                'direction' => 'left',
            ])
            ->assertAccepted();

        Event::assertDispatched(GameRoomInput::class, function (GameRoomInput $event) use ($watcher): bool {
            return $event->playerId === $watcher['playerId']
                && $event->displayName === 'Deni'
                && $event->broadcastWith()['input'] === ['direction' => 'left'];
        });
        $this->assertDatabaseHas('game_rooms', [
            'code' => $room['code'],
            'version' => $watcher['version'],
        ]);
    }

    public function test_limits_each_player_token_to_120_inputs_per_minute(): void
    {
        $this->publish('ular');

        $limiter = RateLimiter::limiter('game-input');
        $this->assertIsCallable($limiter);

        $bearer = Request::create('/api/game/rooms/ABCDEF/input', 'POST');
        $bearer->headers->set('Authorization', 'Bearer player-token-a');
        $header = Request::create('/api/game/rooms/ABCDEF/input', 'POST');
        $header->headers->set('X-Player-Token', 'player-token-a');
        $other = Request::create('/api/game/rooms/ABCDEF/input', 'POST');
        $other->headers->set('Authorization', 'Bearer player-token-b');
        $anonymous = Request::create('/api/game/rooms/ABCDEF/input', 'POST', server: ['REMOTE_ADDR' => '203.0.113.8']);

        $bearerLimit = $limiter($bearer);
        $headerLimit = $limiter($header);
        $otherLimit = $limiter($other);
        $anonymousLimit = $limiter($anonymous);

        $this->assertInstanceOf(Limit::class, $bearerLimit);
        $this->assertSame(120, $bearerLimit->maxAttempts);
        $this->assertSame(60, $bearerLimit->decaySeconds);
        $this->assertSame(hash('sha256', 'player-token-a'), $bearerLimit->key);
        $this->assertSame($bearerLimit->key, $headerLimit->key);
        $this->assertSame(hash('sha256', 'player-token-b'), $otherLimit->key);
        $this->assertNotSame($bearerLimit->key, $otherLimit->key);
        $this->assertSame($anonymous->ip(), $anonymousLimit->key);
        $this->assertStringNotContainsString('player-token-a', (string) $bearerLimit->key);

        RateLimiter::for('game-input', function (Request $request): Limit {
            $token = (string) ($request->bearerToken() ?: $request->header('X-Player-Token', ''));
            $identity = $token !== '' ? hash('sha256', $token) : (string) $request->ip();

            return Limit::perMinute(2)->by($identity);
        });

        $room = $this->openRoom('ular', 'Ada');
        $friend = $this->postJson('http://ular.jadidaring.test/api/game/rooms/join', [
            'code' => $room['code'],
            'displayName' => 'Bayu',
        ])->assertOk()->json();

        $send = function (string $token) use ($room) {
            return $this->withToken($token)->postJson(
                'http://ular.jadidaring.test/api/game/rooms/'.$room['code'].'/input',
                ['direction' => 'up'],
            );
        };

        $send($room['playerToken'])->assertAccepted();
        $send($room['playerToken'])->assertAccepted();
        $send($room['playerToken'])->assertTooManyRequests();
        $send($friend['playerToken'])->assertAccepted();

        $this->assertDatabaseHas('game_rooms', [
            'code' => $room['code'],
            'version' => $friend['version'],
        ]);
    }

    private function publish(string $subdomain): void
    {
        CompanyProfile::factory()->published()->create([
            'subdomain' => $subdomain,
            'html' => '<html><body>'.$subdomain.'</body></html>',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function openRoom(string $subdomain, string $displayName): array
    {
        return $this->postJson('http://'.$subdomain.'.jadidaring.test/api/game/rooms', [
            'displayName' => $displayName,
            'visibility' => 'private',
        ])->assertOk()->json();
    }
}
