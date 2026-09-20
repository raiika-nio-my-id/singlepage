<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CompanyProfileAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_lands_on_workspace_without_an_id_in_the_url(): void
    {
        $client = User::factory()->client()->create();
        $profile = CompanyProfile::factory()->for($client)->create([
            'subdomain' => 'owned',
        ]);

        $this->actingAs($client)
            ->get('http://admin.jadidaring.test/')
            ->assertOk()
            ->assertSee('Your site')
            ->assertSee('Published')
            ->assertSee('owned.jadidaring.test')
            ->assertSee('Expired')
            ->assertDontSee('name="expires_at"', false)
            ->assertDontSee('name="hide_footer"', false)
            ->assertDontSee('Hide platform footer')
            ->assertDontSee('/clients/'.$profile->id);

        $this->actingAs($client)
            ->get('http://admin.jadidaring.test/clients/'.$profile->id.'/edit')
            ->assertRedirect('http://admin.jadidaring.test');
    }

    public function test_client_cannot_open_the_admin_listing(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)
            ->get('http://admin.jadidaring.test/clients')
            ->assertForbidden();
    }

    public function test_client_cannot_edit_another_profile(): void
    {
        $owner = User::factory()->client()->create();
        $other = User::factory()->client()->create();
        $profile = CompanyProfile::factory()->for($owner)->create();

        $this->actingAs($other)
            ->get('http://admin.jadidaring.test/clients/'.$profile->id.'/edit')
            ->assertForbidden();
    }

    public function test_client_cannot_open_settings(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)
            ->get('http://admin.jadidaring.test/settings')
            ->assertForbidden();
    }

    public function test_admin_can_update_any_profile(): void
    {
        $admin = User::factory()->admin()->create();
        $profile = CompanyProfile::factory()->create([
            'subdomain' => 'oldname',
        ]);

        $this->actingAs($admin)
            ->from('http://admin.jadidaring.test/clients/'.$profile->id.'/edit')
            ->put('http://admin.jadidaring.test/clients/'.$profile->id, [
                'subdomain' => 'newname',
                'name' => $profile->user->name,
                'email' => $profile->user->email,
                'html' => '<html><body>Updated</body></html>',
                'status' => 'published',
                'expires_at' => now()->addYear()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('company_profiles', [
            'id' => $profile->id,
            'subdomain' => 'newname',
            'status' => 'published',
        ]);
    }

    public function test_admin_can_hide_the_platform_footer(): void
    {
        $admin = User::factory()->admin()->create();
        $profile = CompanyProfile::factory()->create([
            'subdomain' => 'premium',
            'hide_footer' => false,
        ]);

        $this->actingAs($admin)
            ->get('http://admin.jadidaring.test/clients/'.$profile->id.'/edit')
            ->assertOk()
            ->assertSee('name="hide_footer"', false)
            ->assertSee('Hide platform footer');

        $this->actingAs($admin)
            ->from('http://admin.jadidaring.test/clients/'.$profile->id.'/edit')
            ->put('http://admin.jadidaring.test/clients/'.$profile->id, [
                'subdomain' => 'premium',
                'name' => $profile->user->name,
                'email' => $profile->user->email,
                'html' => '<html><body>White label</body></html>',
                'expires_at' => now()->addYear()->toDateString(),
                'hide_footer' => '1',
            ])
            ->assertRedirect();

        $this->assertTrue($profile->fresh()->hide_footer);
    }

    public function test_admin_can_update_client_credentials(): void
    {
        $admin = User::factory()->admin()->create();
        $profile = CompanyProfile::factory()->create([
            'subdomain' => 'acme',
        ]);

        $this->actingAs($admin)
            ->from('http://admin.jadidaring.test/clients/'.$profile->id.'/edit')
            ->put('http://admin.jadidaring.test/clients/'.$profile->id, [
                'subdomain' => 'acme',
                'name' => 'Renamed Client',
                'email' => 'renamed@example.com',
                'password' => 'NewPassword123!',
                'html' => '<html><body>Same site</body></html>',
                'expires_at' => now()->addYear()->toDateString(),
            ])
            ->assertRedirect();

        $profile->user->refresh();

        $this->assertSame('Renamed Client', $profile->user->name);
        $this->assertSame('renamed@example.com', $profile->user->email);
        $this->assertTrue(Hash::check('NewPassword123!', $profile->user->password));
    }

    public function test_client_cannot_change_subdomain_or_credentials(): void
    {
        $client = User::factory()->client()->create([
            'name' => 'Original Client',
            'email' => 'owner@example.com',
        ]);
        $profile = CompanyProfile::factory()->for($client)->create([
            'subdomain' => 'owned',
            'html' => '<html><body>Old</body></html>',
            'expires_at' => now()->addYear()->toDateString(),
        ]);
        $originalExpiry = $profile->expires_at?->toDateString();

        $this->actingAs($client)
            ->from('http://admin.jadidaring.test/')
            ->put('http://admin.jadidaring.test/', [
                'subdomain' => 'hijacked',
                'name' => 'Hacked',
                'email' => 'hacked@example.com',
                'password' => 'StolenPass123!',
                'html' => '<html><body>Updated by owner</body></html>',
                'status' => 'published',
                'expires_at' => now()->addYears(5)->toDateString(),
                'hide_footer' => '1',
            ])
            ->assertRedirect();

        $profile->refresh();
        $client->refresh();

        $this->assertSame('owned', $profile->subdomain);
        $this->assertSame('Original Client', $client->name);
        $this->assertSame('owner@example.com', $client->email);
        $this->assertSame('<html><body>Updated by owner</body></html>', $profile->html);
        $this->assertSame($originalExpiry, $profile->expires_at?->toDateString());
        $this->assertFalse($profile->hide_footer);
    }

    public function test_admin_can_change_expiration(): void
    {
        $admin = User::factory()->admin()->create();
        $profile = CompanyProfile::factory()->create([
            'subdomain' => 'timed',
            'expires_at' => now()->addYear()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->from('http://admin.jadidaring.test/clients/'.$profile->id.'/edit')
            ->put('http://admin.jadidaring.test/clients/'.$profile->id, [
                'subdomain' => 'timed',
                'name' => $profile->user->name,
                'email' => $profile->user->email,
                'html' => '<html><body>Still here</body></html>',
                'expires_at' => now()->addYears(2)->toDateString(),
            ])
            ->assertRedirect();

        $this->assertSame(
            now()->addYears(2)->toDateString(),
            $profile->fresh()->expires_at?->toDateString(),
        );
    }

    public function test_reserved_subdomain_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $profile = CompanyProfile::factory()->create([
            'subdomain' => 'okshop',
        ]);

        $this->actingAs($admin)
            ->from('http://admin.jadidaring.test/clients/'.$profile->id.'/edit')
            ->put('http://admin.jadidaring.test/clients/'.$profile->id, [
                'subdomain' => 'admin',
                'name' => $profile->user->name,
                'email' => $profile->user->email,
                'html' => '<html><body>Nope</body></html>',
                'expires_at' => now()->addYear()->toDateString(),
            ])
            ->assertSessionHasErrors('subdomain');
    }
}
