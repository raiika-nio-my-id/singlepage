<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\CompanyProfileAlias;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubdomainAliasTest extends TestCase
{
    use RefreshDatabase;

    // ── 301 redirect behaviour ─────────────────────────────────────────────

    public function test_alias_subdomain_redirects_to_primary(): void
    {
        $profile = CompanyProfile::factory()->published()->create(['subdomain' => 'primary']);
        CompanyProfileAlias::factory()->for($profile)->create(['subdomain' => 'alias1']);

        $this->get('http://alias1.jadidaring.test/')
            ->assertRedirect('http://primary.jadidaring.test/')
            ->assertStatus(301);
    }

    public function test_alias_redirect_preserves_path_and_query(): void
    {
        $profile = CompanyProfile::factory()->published()->create(['subdomain' => 'main']);
        CompanyProfileAlias::factory()->for($profile)->create(['subdomain' => 'alt']);

        $this->get('http://alt.jadidaring.test/some/path?foo=bar')
            ->assertRedirect('http://main.jadidaring.test/some/path?foo=bar')
            ->assertStatus(301);
    }

    public function test_alias_of_draft_profile_returns_404(): void
    {
        // Profile is draft – not live – so alias should not redirect.
        $profile = CompanyProfile::factory()->create(['subdomain' => 'draftsite']);
        CompanyProfileAlias::factory()->for($profile)->create(['subdomain' => 'draftalias']);

        $this->get('http://draftalias.jadidaring.test/')->assertNotFound();
    }

    public function test_alias_of_expired_profile_returns_404(): void
    {
        $profile = CompanyProfile::factory()->published()->expired()->create(['subdomain' => 'expiredsite']);
        CompanyProfileAlias::factory()->for($profile)->create(['subdomain' => 'expiredalias']);

        $this->get('http://expiredalias.jadidaring.test/')->assertNotFound();
    }

    public function test_alias_robots_txt_redirects_to_primary(): void
    {
        $profile = CompanyProfile::factory()->published()->create(['subdomain' => 'canonical']);
        CompanyProfileAlias::factory()->for($profile)->create(['subdomain' => 'altdomain']);

        $this->get('http://altdomain.jadidaring.test/robots.txt')
            ->assertRedirect('http://canonical.jadidaring.test/robots.txt')
            ->assertStatus(301);
    }

    public function test_alias_sitemap_redirects_to_primary(): void
    {
        $profile = CompanyProfile::factory()->published()->create(['subdomain' => 'canonical']);
        CompanyProfileAlias::factory()->for($profile)->create(['subdomain' => 'altdomain2']);

        $this->get('http://altdomain2.jadidaring.test/sitemap.xml')
            ->assertRedirect('http://canonical.jadidaring.test/sitemap.xml')
            ->assertStatus(301);
    }

    // ── Admin management ───────────────────────────────────────────────────

    public function test_admin_can_add_alias(): void
    {
        $admin = User::factory()->admin()->create();
        $profile = CompanyProfile::factory()->create(['subdomain' => 'mysite']);

        $this->actingAs($admin)
            ->from('http://admin.jadidaring.test/clients/'.$profile->id.'/edit')
            ->post('http://admin.jadidaring.test/clients/'.$profile->id.'/aliases', [
                'alias' => 'mysite-alias',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('company_profile_aliases', [
            'company_profile_id' => $profile->id,
            'subdomain' => 'mysite-alias',
        ]);
    }

    public function test_admin_can_remove_alias(): void
    {
        $admin = User::factory()->admin()->create();
        $profile = CompanyProfile::factory()->create(['subdomain' => 'removeme']);
        $alias = CompanyProfileAlias::factory()->for($profile)->create(['subdomain' => 'oldname']);

        $this->actingAs($admin)
            ->from('http://admin.jadidaring.test/clients/'.$profile->id.'/edit')
            ->delete('http://admin.jadidaring.test/clients/'.$profile->id.'/aliases/'.$alias->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('company_profile_aliases', ['id' => $alias->id]);
    }

    public function test_client_cannot_add_alias(): void
    {
        $client = User::factory()->client()->create();
        $profile = CompanyProfile::factory()->for($client)->create(['subdomain' => 'clientsite']);

        $this->actingAs($client)
            ->post('http://admin.jadidaring.test/clients/'.$profile->id.'/aliases', [
                'alias' => 'stolen-alias',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('company_profile_aliases', ['subdomain' => 'stolen-alias']);
    }

    public function test_client_cannot_remove_alias(): void
    {
        $client = User::factory()->client()->create();
        $profile = CompanyProfile::factory()->for($client)->create(['subdomain' => 'clientsite2']);
        $alias = CompanyProfileAlias::factory()->for($profile)->create(['subdomain' => 'protected']);

        $this->actingAs($client)
            ->delete('http://admin.jadidaring.test/clients/'.$profile->id.'/aliases/'.$alias->id)
            ->assertForbidden();

        $this->assertDatabaseHas('company_profile_aliases', ['id' => $alias->id]);
    }

    // ── Uniqueness validation ──────────────────────────────────────────────

    public function test_alias_cannot_duplicate_a_primary_subdomain(): void
    {
        $admin = User::factory()->admin()->create();
        $profile = CompanyProfile::factory()->create(['subdomain' => 'existing']);
        $other = CompanyProfile::factory()->create(['subdomain' => 'taken']);

        $this->actingAs($admin)
            ->from('http://admin.jadidaring.test/clients/'.$profile->id.'/edit')
            ->post('http://admin.jadidaring.test/clients/'.$profile->id.'/aliases', [
                'alias' => 'taken',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('alias');
    }

    public function test_alias_cannot_duplicate_another_alias(): void
    {
        $admin = User::factory()->admin()->create();
        $profile1 = CompanyProfile::factory()->create(['subdomain' => 'site1']);
        $profile2 = CompanyProfile::factory()->create(['subdomain' => 'site2']);
        CompanyProfileAlias::factory()->for($profile1)->create(['subdomain' => 'shared-name']);

        $this->actingAs($admin)
            ->from('http://admin.jadidaring.test/clients/'.$profile2->id.'/edit')
            ->post('http://admin.jadidaring.test/clients/'.$profile2->id.'/aliases', [
                'alias' => 'shared-name',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('alias');
    }

    public function test_primary_subdomain_cannot_conflict_with_existing_alias(): void
    {
        $admin = User::factory()->admin()->create();
        $profile1 = CompanyProfile::factory()->create(['subdomain' => 'owner']);
        CompanyProfileAlias::factory()->for($profile1)->create(['subdomain' => 'claimedbyalias']);

        $profile2 = CompanyProfile::factory()->create(['subdomain' => 'other']);

        $this->actingAs($admin)
            ->from('http://admin.jadidaring.test/clients/'.$profile2->id.'/edit')
            ->put('http://admin.jadidaring.test/clients/'.$profile2->id, [
                'subdomain' => 'claimedbyalias',
                'name' => $profile2->user->name,
                'email' => $profile2->user->email,
                'html' => '<html><body>Hi</body></html>',
                'expires_at' => now()->addYear()->toDateString(),
            ])
            ->assertSessionHasErrors('subdomain');
    }

    // ── Dashboard display ──────────────────────────────────────────────────

    public function test_aliases_appear_in_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create();
        $profile = CompanyProfile::factory()->create(['subdomain' => 'visible']);
        CompanyProfileAlias::factory()->for($profile)->create(['subdomain' => 'visible-alt']);

        $this->actingAs($admin)
            ->get('http://admin.jadidaring.test/clients')
            ->assertOk()
            ->assertSee('visible-alt');
    }

    // ── Sitemap excludes aliases ───────────────────────────────────────────

    public function test_gamehub_sitemap_does_not_list_alias_subdomains(): void
    {
        CompanyProfile::factory()->published()->create(['subdomain' => 'gamehub']);
        $game = CompanyProfile::factory()->published()->create(['subdomain' => 'realgame']);
        CompanyProfileAlias::factory()->for($game)->create(['subdomain' => 'gamealias']);

        $this->get('http://gamehub.jadidaring.test/sitemap.xml')
            ->assertOk()
            ->assertSee('realgame.jadidaring.test', false)
            ->assertDontSee('gamealias', false);
    }
}
