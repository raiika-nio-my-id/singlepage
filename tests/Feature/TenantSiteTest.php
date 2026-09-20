<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_tenant_host_returns_not_found(): void
    {
        $this->get('http://missing.jadidaring.test/')->assertNotFound();
    }

    public function test_draft_profile_is_not_public(): void
    {
        CompanyProfile::factory()->create([
            'subdomain' => 'hidden',
        ]);

        $this->get('http://hidden.jadidaring.test/')->assertNotFound();
    }

    public function test_published_profile_returns_html_with_platform_terms_footer(): void
    {
        CompanyProfile::factory()->published()->create([
            'subdomain' => 'acme',
            'html' => '<html><body>Acme public site</body></html>',
        ]);

        $this->get('http://acme.jadidaring.test/')
            ->assertOk()
            ->assertSee('Acme public site', false)
            ->assertSee('data-jadidaring-legal-notice', false)
            ->assertSee('Syarat &amp; Ketentuan', false)
            ->assertSee('https://jadidaring.com/', false)
            ->assertDontSee('bukan tanggung jawab', false)
            ->assertSee('http://acme.jadidaring.test/syarat-ketentuan', false)
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_terms_page_explains_that_jadidaring_is_only_a_provider(): void
    {
        CompanyProfile::factory()->published()->create([
            'subdomain' => 'acme',
            'html' => '<html><body>Acme public site</body></html>',
        ]);

        $this->get('http://acme.jadidaring.test/syarat-ketentuan')
            ->assertOk()
            ->assertSee('Syarat &amp; Ketentuan', false)
            ->assertSee('hanya menyediakan sarana teknis', false)
            ->assertSee('tidak bertanggung jawab', false)
            ->assertSee('penipuan', false)
            ->assertSee('acme.jadidaring.test');

        $this->get('http://acme.jadidaring.test/terms')
            ->assertRedirect('http://acme.jadidaring.test/syarat-ketentuan');
    }

    public function test_draft_profile_terms_page_is_not_public(): void
    {
        CompanyProfile::factory()->create([
            'subdomain' => 'hidden',
        ]);

        $this->get('http://hidden.jadidaring.test/syarat-ketentuan')->assertNotFound();
    }

    public function test_expired_published_profile_is_not_public(): void
    {
        CompanyProfile::factory()->published()->expired()->create([
            'subdomain' => 'oldshop',
            'html' => '<html><body>Should be offline</body></html>',
        ]);

        $this->get('http://oldshop.jadidaring.test/')->assertNotFound();
    }

    public function test_admin_host_is_never_treated_as_a_tenant(): void
    {
        CompanyProfile::factory()->published()->create([
            'subdomain' => 'admin',
            'html' => '<html><body>should not publish</body></html>',
        ]);

        $this->get('http://admin.jadidaring.test/')
            ->assertRedirect('http://admin.jadidaring.test/login');
    }
}
