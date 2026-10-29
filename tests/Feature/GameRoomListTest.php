<?php

namespace Tests\Feature;

use App\Events\GameRoomUpdated;
use App\Models\CompanyProfile;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class GameRoomListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([GameRoomUpdated::class]);
    }

    public function test_room_list_without_secret_returns_only_public_waiting_and_playing_rooms(): void
    {
        $this->publish('coratcoret');

        $publicWaiting = $this->openRoom('coratcoret', 'Ada', 'public');
        $publicPlaying = $this->openRoom('coratcoret', 'Bayu', 'public');
        $this->setStatus('coratcoret', $publicPlaying, 'playing');
        $privateWaiting = $this->openRoom('coratcoret', 'Cici', 'private');
        $closed = $this->openRoom('coratcoret', 'Deni', 'public');
        $this->closeRoom('coratcoret', $closed);

        $rooms = $this->getJson('http://coratcoret.jadidaring.test/api/game/rooms')
            ->assertOk()
            ->assertJsonCount(2, 'rooms')
            ->json('rooms');

        $codes = collect($rooms)->pluck('code');
        $this->assertTrue($codes->contains($publicWaiting['code']));
        $this->assertTrue($codes->contains($publicPlaying['code']));
        $this->assertFalse($codes->contains($privateWaiting['code']));
        $this->assertFalse($codes->contains($closed['code']));

        $listedPlaying = collect($rooms)->firstWhere('code', $publicPlaying['code']);
        $this->assertSame(['code', 'status', 'visibility', 'seated', 'spectators', 'maxPlayers', 'version', 'updatedAt'], array_keys($listedPlaying));
        $this->assertSame('public', $listedPlaying['visibility']);
        $this->assertSame('playing', $listedPlaying['status']);
        $this->assertSame(1, $listedPlaying['seated']);
        $this->assertSame(0, $listedPlaying['spectators']);
        $this->assertSame(4, $listedPlaying['maxPlayers']);
    }

    public function test_wrong_query_or_header_secret_keeps_the_public_only_list(): void
    {
        $this->publish('coratcoret');

        $public = $this->openRoom('coratcoret', 'Ada', 'public');
        $private = $this->openRoom('coratcoret', 'Bayu', 'private');

        foreach ([
            'http://coratcoret.jadidaring.test/api/game/rooms?gm=wrongsecret',
            'http://coratcoret.jadidaring.test/api/game/rooms?gm=GameMasterJD',
        ] as $url) {
            $this->getJson($url)
                ->assertOk()
                ->assertJsonCount(1, 'rooms')
                ->assertJsonPath('rooms.0.code', $public['code'])
                ->assertJsonPath('rooms.0.visibility', 'public');
        }

        $this->getJson('http://coratcoret.jadidaring.test/api/game/rooms', [
            'X-Game-Master' => 'wrongsecret',
        ])->assertOk()
            ->assertJsonCount(1, 'rooms')
            ->assertJsonPath('rooms.0.code', $public['code']);

        $this->assertDatabaseHas('game_rooms', [
            'code' => $private['code'],
            'visibility' => 'private',
        ]);
    }

    public function test_correct_gm_query_lists_public_and_private_waiting_and_playing_rooms(): void
    {
        $this->publish('coratcoret');

        $public = $this->openRoom('coratcoret', 'Ada', 'public');
        $privateWaiting = $this->openRoom('coratcoret', 'Bayu', 'private');
        $privatePlaying = $this->openRoom('coratcoret', 'Cici', 'private');
        $this->setStatus('coratcoret', $privatePlaying, 'playing');
        $closedPrivate = $this->openRoom('coratcoret', 'Deni', 'private');
        $this->closeRoom('coratcoret', $closedPrivate);

        $rooms = $this->getJson('http://coratcoret.jadidaring.test/api/game/rooms?gm=gamemasterjd')
            ->assertOk()
            ->assertJsonCount(3, 'rooms')
            ->json('rooms');

        $codes = collect($rooms)->pluck('code');
        $this->assertTrue($codes->contains($public['code']));
        $this->assertTrue($codes->contains($privateWaiting['code']));
        $this->assertTrue($codes->contains($privatePlaying['code']));
        $this->assertFalse($codes->contains($closedPrivate['code']));
        $this->assertContains('private', collect($rooms)->pluck('visibility'));
        $this->assertSame(['code', 'status', 'visibility', 'seated', 'spectators', 'maxPlayers', 'version', 'updatedAt'], array_keys($rooms[0]));
    }

    public function test_correct_x_game_master_header_lists_private_rooms(): void
    {
        $this->publish('sambungcoret');

        $public = $this->openRoom('sambungcoret', 'Ada', 'public');
        $private = $this->openRoom('sambungcoret', 'Bayu', 'private');

        $rooms = $this->getJson('http://sambungcoret.jadidaring.test/api/game/rooms', [
            'X-Game-Master' => 'gamemasterjd',
        ])->assertOk()
            ->assertJsonCount(2, 'rooms')
            ->json('rooms');

        $codes = collect($rooms)->pluck('code');
        $this->assertTrue($codes->contains($public['code']));
        $this->assertTrue($codes->contains($private['code']));
    }

    public function test_gamemaster_list_stays_inside_the_game_subdomain(): void
    {
        $this->publish('coratcoret');
        $this->publish('sambungcoret');

        $corat = $this->openRoom('coratcoret', 'Ada', 'private');
        $sambung = $this->openRoom('sambungcoret', 'Bayu', 'private');

        $this->getJson('http://coratcoret.jadidaring.test/api/game/rooms?gm=gamemasterjd')
            ->assertOk()
            ->assertJsonCount(1, 'rooms')
            ->assertJsonPath('rooms.0.code', $corat['code'])
            ->assertJsonPath('rooms.0.visibility', 'private');

        $this->getJson('http://sambungcoret.jadidaring.test/api/game/rooms', [
            'X-Game-Master' => 'gamemasterjd',
        ])->assertOk()
            ->assertJsonCount(1, 'rooms')
            ->assertJsonPath('rooms.0.code', $sambung['code']);

        CompanyProfile::factory()->create(['subdomain' => 'hidden']);

        $this->getJson('http://hidden.jadidaring.test/api/game/rooms?gm=gamemasterjd')->assertNotFound();
        $this->getJson('http://admin.jadidaring.test/api/game/rooms?gm=gamemasterjd')->assertNotFound();
    }

    public function test_empty_gamemaster_setting_falls_back_to_the_env_default(): void
    {
        $this->publish('coratcoret');
        $public = $this->openRoom('coratcoret', 'Ada', 'public');
        $private = $this->openRoom('coratcoret', 'Bayu', 'private');

        Setting::query()->updateOrCreate(
            ['key' => 'gamemaster_secret'],
            ['value' => ''],
        );

        $this->assertSame('gamemasterjd', Setting::gamemasterSecret());

        $this->getJson('http://coratcoret.jadidaring.test/api/game/rooms?gm=gamemasterjd')
            ->assertOk()
            ->assertJsonCount(2, 'rooms');

        $this->getJson('http://coratcoret.jadidaring.test/api/game/rooms', [
            'X-Game-Master' => 'gamemasterjd',
        ])->assertOk()
            ->assertJsonCount(2, 'rooms');

        $this->assertDatabaseHas('game_rooms', [
            'code' => $public['code'],
            'visibility' => 'public',
        ]);
        $this->assertDatabaseHas('game_rooms', [
            'code' => $private['code'],
            'visibility' => 'private',
        ]);
    }

    public function test_admin_saved_gamemaster_secret_overrides_the_env_default(): void
    {
        $this->publish('coratcoret');
        $public = $this->openRoom('coratcoret', 'Ada', 'public');
        $private = $this->openRoom('coratcoret', 'Bayu', 'private');

        Setting::query()->updateOrCreate(
            ['key' => 'gamemaster_secret'],
            ['value' => 'new-gm-key'],
        );

        $this->assertSame('new-gm-key', Setting::gamemasterSecret());

        $this->getJson('http://coratcoret.jadidaring.test/api/game/rooms?gm=gamemasterjd')
            ->assertOk()
            ->assertJsonCount(1, 'rooms')
            ->assertJsonPath('rooms.0.code', $public['code']);

        $rooms = $this->getJson('http://coratcoret.jadidaring.test/api/game/rooms?gm=new-gm-key')
            ->assertOk()
            ->assertJsonCount(2, 'rooms')
            ->json('rooms');

        $codes = collect($rooms)->pluck('code');
        $this->assertTrue($codes->contains($public['code']));
        $this->assertTrue($codes->contains($private['code']));

        $this->getJson('http://coratcoret.jadidaring.test/api/game/rooms', [
            'X-Game-Master' => 'new-gm-key',
        ])->assertOk()
            ->assertJsonCount(2, 'rooms');
    }

    /**
     * @return array<string, mixed>
     */
    private function openRoom(string $subdomain, string $displayName, string $visibility): array
    {
        return $this->postJson('http://'.$subdomain.'.jadidaring.test/api/game/rooms', [
            'displayName' => $displayName,
            'visibility' => $visibility,
        ])->assertOk()->json();
    }

    /**
     * @param  array<string, mixed>  $room
     */
    private function setStatus(string $subdomain, array $room, string $status): void
    {
        $this->withToken($room['playerToken'])
            ->postJson('http://'.$subdomain.'.jadidaring.test/api/game/rooms/'.$room['code'].'/actions', [
                'type' => 'status',
                'expectedVersion' => $room['version'],
                'status' => $status,
            ])->assertOk();
    }

    /**
     * @param  array<string, mixed>  $room
     */
    private function closeRoom(string $subdomain, array $room): void
    {
        $this->withToken($room['playerToken'])
            ->postJson('http://'.$subdomain.'.jadidaring.test/api/game/rooms/'.$room['code'].'/leave')
            ->assertOk();
    }

    private function publish(string $subdomain): void
    {
        CompanyProfile::factory()->published()->create([
            'subdomain' => $subdomain,
            'html' => '<html><body>'.$subdomain.'</body></html>',
        ]);
    }
}
