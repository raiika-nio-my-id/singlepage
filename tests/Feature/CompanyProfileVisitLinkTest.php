<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyProfileVisitLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_edit_page_has_a_button_to_the_public_website(): void
    {
        $admin = User::factory()->admin()->create();
        $profile = CompanyProfile::factory()->create([
            'subdomain' => 'acme',
        ]);

        $this->actingAs($admin)
            ->get('http://admin.jadidaring.test/clients/'.$profile->id.'/edit')
            ->assertOk()
            ->assertSee('Live preview')
            ->assertSee('Visit website')
            ->assertSee('href="http://acme.jadidaring.test"', false)
            ->assertSee('target="_blank"', false);
    }

    public function test_admin_listing_links_to_the_public_website(): void
    {
        $admin = User::factory()->admin()->create();
        CompanyProfile::factory()->create([
            'subdomain' => 'acme',
        ]);

        $this->actingAs($admin)
            ->get('http://admin.jadidaring.test/')
            ->assertOk()
            ->assertSee('Visit website')
            ->assertSee('href="http://acme.jadidaring.test"', false)
            ->assertSee('target="_blank"', false);
    }

    public function test_client_workspace_links_to_their_public_website(): void
    {
        $client = User::factory()->client()->create();
        CompanyProfile::factory()->for($client)->create([
            'subdomain' => 'owned',
        ]);

        $this->actingAs($client)
            ->get('http://admin.jadidaring.test/')
            ->assertOk()
            ->assertSee('Visit website')
            ->assertSee('href="http://owned.jadidaring.test"', false);
    }

    public function test_create_client_page_includes_a_visit_website_control(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('http://admin.jadidaring.test/clients/create')
            ->assertOk()
            ->assertSee('Visit website')
            ->assertSee('data-site-url-template="http://{subdomain}.jadidaring.test"', false)
            ->assertSee('aria-disabled="true"', false);
    }
}
