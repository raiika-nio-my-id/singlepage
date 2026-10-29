<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_gamemaster_secret_field(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('http://admin.jadidaring.test/settings')
            ->assertOk()
            ->assertSee('Gamemaster secret')
            ->assertSee('name="gamemaster_secret"', false);
    }

    public function test_admin_can_save_the_gamemaster_secret(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from('http://admin.jadidaring.test/settings')
            ->put('http://admin.jadidaring.test/settings', [
                'reserved_subdomains' => 'www',
                'gamemaster_secret' => '  new-gm-key  ',
            ])
            ->assertRedirect('http://admin.jadidaring.test/settings')
            ->assertSessionHas('success', 'Settings saved.');

        $this->assertDatabaseHas('settings', [
            'key' => 'gamemaster_secret',
            'value' => 'new-gm-key',
        ]);
        $this->assertSame('new-gm-key', Setting::gamemasterSecret());
    }

    public function test_empty_gamemaster_secret_falls_back_to_the_env_default(): void
    {
        $admin = User::factory()->admin()->create();

        Setting::query()->updateOrCreate(
            ['key' => 'gamemaster_secret'],
            ['value' => 'temporary-key'],
        );

        $this->actingAs($admin)
            ->from('http://admin.jadidaring.test/settings')
            ->put('http://admin.jadidaring.test/settings', [
                'reserved_subdomains' => '',
                'gamemaster_secret' => '',
            ])
            ->assertRedirect('http://admin.jadidaring.test/settings');

        $this->assertDatabaseHas('settings', [
            'key' => 'gamemaster_secret',
            'value' => '',
        ]);
        $this->assertSame('gamemasterjd', Setting::gamemasterSecret());
    }

    public function test_whitespace_only_gamemaster_secret_is_stored_empty_and_falls_back(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from('http://admin.jadidaring.test/settings')
            ->put('http://admin.jadidaring.test/settings', [
                'reserved_subdomains' => '',
                'gamemaster_secret' => '   ',
            ])
            ->assertRedirect('http://admin.jadidaring.test/settings')
            ->assertSessionHas('success', 'Settings saved.');

        $this->assertDatabaseHas('settings', [
            'key' => 'gamemaster_secret',
            'value' => '',
        ]);
        $this->assertSame('gamemasterjd', Setting::gamemasterSecret());
    }

    public function test_gamemaster_secret_cannot_exceed_128_characters(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from('http://admin.jadidaring.test/settings')
            ->put('http://admin.jadidaring.test/settings', [
                'reserved_subdomains' => '',
                'gamemaster_secret' => str_repeat('a', 129),
            ])
            ->assertRedirect('http://admin.jadidaring.test/settings')
            ->assertSessionHasErrors('gamemaster_secret');
    }
}
