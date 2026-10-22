<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAliasTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_set_alias_on_create(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('http://admin.jadidaring.test/clients', [
                'name' => 'Client Name',
                'email' => 'alias@example.com',
                'admin_alias' => 'Pak Budi – Ulartangga',
                'subdomain' => 'aliasedsite',
                'html' => '<html><body>Hi</body></html>',
                'expires_at' => now()->addYear()->toDateString(),
            ])
            ->assertRedirect();

        $profile = CompanyProfile::where('subdomain', 'aliasedsite')->firstOrFail();
        $this->assertSame('Pak Budi – Ulartangga', $profile->admin_alias);
    }

    public function test_admin_can_update_alias_on_edit(): void
    {
        $admin = User::factory()->admin()->create();
        $profile = CompanyProfile::factory()->create(['subdomain' => 'somesite']);

        $this->actingAs($admin)
            ->from('http://admin.jadidaring.test/clients/'.$profile->id.'/edit')
            ->put('http://admin.jadidaring.test/clients/'.$profile->id, [
                'subdomain' => 'somesite',
                'name' => $profile->user->name,
                'email' => $profile->user->email,
                'admin_alias' => 'My Internal Label',
                'html' => '<html><body>Hi</body></html>',
                'expires_at' => now()->addYear()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertSame('My Internal Label', $profile->fresh()->admin_alias);
    }

    public function test_admin_can_clear_alias(): void
    {
        $admin = User::factory()->admin()->create();
        $profile = CompanyProfile::factory()->create([
            'subdomain' => 'clearme',
            'admin_alias' => 'Old Alias',
        ]);

        $this->actingAs($admin)
            ->from('http://admin.jadidaring.test/clients/'.$profile->id.'/edit')
            ->put('http://admin.jadidaring.test/clients/'.$profile->id, [
                'subdomain' => 'clearme',
                'name' => $profile->user->name,
                'email' => $profile->user->email,
                'admin_alias' => '',
                'html' => '<html><body>Hi</body></html>',
                'expires_at' => now()->addYear()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertNull($profile->fresh()->admin_alias);
    }

    public function test_alias_is_shown_in_admin_listing(): void
    {
        $admin = User::factory()->admin()->create();
        CompanyProfile::factory()->create([
            'subdomain' => 'visiblealias',
            'admin_alias' => 'VIP Client Label',
        ]);

        $this->actingAs($admin)
            ->get('http://admin.jadidaring.test/clients')
            ->assertOk()
            ->assertSee('VIP Client Label');
    }

    public function test_alias_does_not_appear_on_public_tenant_page(): void
    {
        CompanyProfile::factory()->published()->create([
            'subdomain' => 'secretalias',
            'html' => '<html><body>Public content</body></html>',
            'admin_alias' => 'Top Secret Internal Name',
        ]);

        $this->get('http://secretalias.jadidaring.test/')
            ->assertOk()
            ->assertDontSee('Top Secret Internal Name', false)
            ->assertSee('Public content', false);
    }

    public function test_client_cannot_set_alias_via_workspace_update(): void
    {
        $client = User::factory()->client()->create();
        $profile = CompanyProfile::factory()->for($client)->create([
            'subdomain' => 'clientsite',
            'admin_alias' => null,
        ]);

        $this->actingAs($client)
            ->from('http://admin.jadidaring.test/')
            ->put('http://admin.jadidaring.test/', [
                'html' => '<html><body>Updated</body></html>',
                'status' => 'published',
                'admin_alias' => 'Hacked Alias',
            ])
            ->assertRedirect();

        $this->assertNull($profile->fresh()->admin_alias, 'Client must not be able to set admin_alias.');
    }

    public function test_alias_field_is_shown_in_admin_edit_form(): void
    {
        $admin = User::factory()->admin()->create();
        $profile = CompanyProfile::factory()->create([
            'subdomain' => 'editform',
            'admin_alias' => 'Existing Alias',
        ]);

        $this->actingAs($admin)
            ->get('http://admin.jadidaring.test/clients/'.$profile->id.'/edit')
            ->assertOk()
            ->assertSee('name="admin_alias"', false)
            ->assertSee('Existing Alias');
    }

    public function test_alias_field_is_hidden_from_client_workspace(): void
    {
        $client = User::factory()->client()->create();
        CompanyProfile::factory()->for($client)->create([
            'subdomain' => 'workspaceclient',
            'admin_alias' => 'Internal Label',
        ]);

        $this->actingAs($client)
            ->get('http://admin.jadidaring.test/')
            ->assertOk()
            ->assertDontSee('name="admin_alias"', false)
            ->assertDontSee('Internal Label');
    }
}
