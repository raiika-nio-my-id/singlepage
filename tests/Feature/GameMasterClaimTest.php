<?php

namespace Tests\Feature;

use App\Events\GameRoomUpdated;
use App\Models\CompanyProfile;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class GameMasterClaimTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([GameRoomUpdated::class]);
    }

    public function test_wrong_code_does_not_mark_the_player_as_gamemaster(): void
    {
        $this->publish('coratcoret');
        $room = $this->openRoom('coratcoret', 'Ada');

        $wrong = [
            'wrongsecret',
            'gamemasterj',
            'gamemasterjdX',
            'GameMasterJD',
        ];

        foreach ($wrong as $code) {
            $this->withToken($room['playerToken'])
                ->postJson('http://coratcoret.jadidaring.test/api/game/rooms/'.$room['code'].'/gamemaster', [
                    'code' => $code,
                ])
                ->assertForbidden()
                ->assertExactJson(['error' => 'Invalid game master code.']);
        }

        $seen = $this->withToken($room['playerToken'])
            ->getJson('http://coratcoret.jadidaring.test/api/game/rooms/'.$room['code'])
            ->assertOk()
            ->json();

        $this->assertFalse($seen['gamemaster']);
        $this->assertFalse($seen['players'][0]['gamemaster']);
        $this->assertSame($room['version'], $seen['version']);
    }

    public function test_correct_code_marks_the_player_as_gamemaster_in_the_present_payload(): void
    {
        $this->publish('coratcoret');
        $room = $this->openRoom('coratcoret', 'Ada');

        $friend = $this->postJson('http://coratcoret.jadidaring.test/api/game/rooms/join', [
            'code' => $room['code'],
            'displayName' => 'Bayu',
        ])->assertOk()->json();

        $claimed = $this->withToken($room['playerToken'])
            ->postJson('http://coratcoret.jadidaring.test/api/game/rooms/'.$room['code'].'/gamemaster', [
                'code' => 'gamemasterjd',
            ])
            ->assertOk()
            ->json();

        $this->assertTrue($claimed['gamemaster']);
        $this->assertSame($friend['version'] + 1, $claimed['version']);
        $this->assertTrue(collect($claimed['players'])->firstWhere('id', $room['playerId'])['gamemaster']);
        $this->assertFalse(collect($claimed['players'])->firstWhere('id', $friend['playerId'])['gamemaster']);
        $this->assertArrayNotHasKey('playerToken', $claimed);
        $this->assertSame('gamemaster', collect($claimed['events'])->last()['type']);

        Event::assertDispatched(GameRoomUpdated::class, function (GameRoomUpdated $event) use ($room, $claimed): bool {
            return $event->subdomain === 'coratcoret'
                && $event->code === $room['code']
                && $event->version === $claimed['version'];
        });

        $friendView = $this->withToken($friend['playerToken'])
            ->getJson('http://coratcoret.jadidaring.test/api/game/rooms/'.$room['code'])
            ->assertOk()
            ->json();

        $this->assertFalse($friendView['gamemaster']);
        $this->assertTrue(collect($friendView['players'])->firstWhere('id', $room['playerId'])['gamemaster']);
        $this->assertFalse(collect($friendView['players'])->firstWhere('id', $friend['playerId'])['gamemaster']);
    }

    public function test_describe_does_not_include_the_gamemaster_secret(): void
    {
        $this->publish('coratcoret');

        $config = $this->getJson('http://coratcoret.jadidaring.test/api/game/config')
            ->assertOk()
            ->assertJsonMissingPath('gamemasterKey')
            ->assertJsonMissingPath('gamemasterSecret')
            ->assertJsonMissingPath('gamemaster_secret')
            ->assertJsonMissingPath('gamemaster');

        $body = (string) $config->getContent();
        $this->assertStringNotContainsString('gamemasterjd', $body);
        $this->assertStringNotContainsString('gmjd1212', $body);
        $this->assertStringNotContainsString('GAME_GAMEMASTER_SECRET', $body);
    }

    public function test_gm_query_listing_still_includes_private_rooms(): void
    {
        $this->publish('coratcoret');
        $public = $this->openRoom('coratcoret', 'Ada', 'public');
        $private = $this->openRoom('coratcoret', 'Bayu', 'private');

        $this->getJson('http://coratcoret.jadidaring.test/api/game/rooms')
            ->assertOk()
            ->assertJsonCount(1, 'rooms')
            ->assertJsonPath('rooms.0.code', $public['code']);

        $rooms = $this->getJson('http://coratcoret.jadidaring.test/api/game/rooms?gm=gamemasterjd')
            ->assertOk()
            ->assertJsonCount(2, 'rooms')
            ->json('rooms');

        $codes = collect($rooms)->pluck('code');
        $this->assertTrue($codes->contains($public['code']));
        $this->assertTrue($codes->contains($private['code']));

        $this->getJson('http://coratcoret.jadidaring.test/api/game/rooms', [
            'X-Game-Master' => 'gamemasterjd',
        ])->assertOk()
            ->assertJsonCount(2, 'rooms');
    }

    public function test_claiming_requires_a_seated_player_token(): void
    {
        $this->publish('coratcoret');
        $room = $this->openRoom('coratcoret', 'Ada');

        $this->postJson('http://coratcoret.jadidaring.test/api/game/rooms/'.$room['code'].'/gamemaster', [
            'code' => 'gamemasterjd',
        ])->assertUnauthorized()
            ->assertExactJson(['error' => 'A player token is required.']);

        $this->withToken('not-a-member')
            ->postJson('http://coratcoret.jadidaring.test/api/game/rooms/'.$room['code'].'/gamemaster', [
                'code' => 'gamemasterjd',
            ])->assertForbidden()
            ->assertExactJson(['error' => 'You are not a member of this room.']);
    }

    public function test_only_one_gamemaster_is_kept_and_leaving_clears_the_flag(): void
    {
        $this->publish('coratcoret');
        $room = $this->openRoom('coratcoret', 'Ada');

        $friend = $this->postJson('http://coratcoret.jadidaring.test/api/game/rooms/join', [
            'code' => $room['code'],
            'displayName' => 'Bayu',
        ])->assertOk()->json();

        $this->withToken($room['playerToken'])
            ->postJson('http://coratcoret.jadidaring.test/api/game/rooms/'.$room['code'].'/gamemaster', [
                'code' => 'gamemasterjd',
            ])->assertOk()
            ->assertJsonPath('gamemaster', true);

        $transferred = $this->withToken($friend['playerToken'])
            ->postJson('http://coratcoret.jadidaring.test/api/game/rooms/'.$room['code'].'/gamemaster', [
                'code' => 'gamemasterjd',
            ])->assertOk()
            ->json();

        $this->assertTrue($transferred['gamemaster']);
        $this->assertFalse(collect($transferred['players'])->firstWhere('id', $room['playerId'])['gamemaster']);
        $this->assertTrue(collect($transferred['players'])->firstWhere('id', $friend['playerId'])['gamemaster']);

        $this->withToken($friend['playerToken'])
            ->postJson('http://coratcoret.jadidaring.test/api/game/rooms/'.$room['code'].'/leave')
            ->assertOk();

        $afterLeave = $this->withToken($room['playerToken'])
            ->getJson('http://coratcoret.jadidaring.test/api/game/rooms/'.$room['code'])
            ->assertOk()
            ->json();

        $this->assertFalse($afterLeave['gamemaster']);
        $this->assertFalse(collect($afterLeave['players'])->contains(fn (array $player): bool => $player['gamemaster'] === true));
        $this->assertFalse(collect($afterLeave['players'])->contains(fn (array $player): bool => $player['id'] === $friend['playerId']));
    }

    public function test_settings_secret_is_the_source_of_truth_for_claim(): void
    {
        $this->publish('coratcoret');
        $room = $this->openRoom('coratcoret', 'Ada');

        Setting::query()->updateOrCreate(
            ['key' => 'gamemaster_secret'],
            ['value' => 'gmjd1212'],
        );

        $this->withToken($room['playerToken'])
            ->postJson('http://coratcoret.jadidaring.test/api/game/rooms/'.$room['code'].'/gamemaster', [
                'code' => 'gamemasterjd',
            ])->assertForbidden()
            ->assertExactJson(['error' => 'Invalid game master code.']);

        $this->withToken($room['playerToken'])
            ->postJson('http://coratcoret.jadidaring.test/api/game/rooms/'.$room['code'].'/gamemaster', [
                'code' => 'gmjd1212',
            ])->assertOk()
            ->assertJsonPath('gamemaster', true);
    }

    /**
     * @return array<string, mixed>
     */
    private function openRoom(string $subdomain, string $displayName, string $visibility = 'private'): array
    {
        return $this->postJson('http://'.$subdomain.'.jadidaring.test/api/game/rooms', [
            'displayName' => $displayName,
            'visibility' => $visibility,
        ])->assertOk()->json();
    }

    private function publish(string $subdomain): void
    {
        CompanyProfile::factory()->published()->create([
            'subdomain' => $subdomain,
            'html' => '<html><body>'.$subdomain.'</body></html>',
        ]);
    }
}
