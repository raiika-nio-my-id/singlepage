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

        $this->get('http://www.jadidaring.test/acme/')
            ->assertOk()
            ->assertSee('Acme public site', false)
            ->assertSee('data-jadidaring-legal-notice', false)
            ->assertSee('Syarat &amp; Ketentuan', false)
            ->assertSee('https://jadidaring.com/', false)
            ->assertDontSee('bukan tanggung jawab', false)
            ->assertSee('http://www.jadidaring.test/acme/syarat-ketentuan', false)
            ->assertDontSee('data-jdng-name-bridge', false)
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(self), geolocation=()');
    }

    public function test_a_game_that_asks_for_a_name_receives_the_shared_name_bridge(): void
    {
        CompanyProfile::factory()->published()->create([
            'subdomain' => 'ulartangga',
            'html' => '<html><head><title>Ular Tangga</title></head><body><!-- jd-namegate --></body></html>',
        ]);

        $html = $this->get('http://www.jadidaring.test/ulartangga/')->assertOk()->getContent();
        $bridgeAt = strpos($html, 'data-jdng-name-bridge');
        $titleAt = strpos($html, '<title>');

        $this->assertIsInt($bridgeAt);
        $this->assertIsInt($titleAt);
        $this->assertLessThan($titleAt, $bridgeAt);
        $this->assertStringContainsString("'jdng.name'", $html);
    }

    public function test_hidden_footer_profile_does_not_include_the_platform_notice(): void
    {
        CompanyProfile::factory()->published()->hiddenFooter()->create([
            'subdomain' => 'vip',
            'html' => '<html><body>White label site</body></html>',
        ]);

        $this->get('http://www.jadidaring.test/vip/')
            ->assertOk()
            ->assertSee('White label site', false)
            ->assertDontSee('data-jdng-name-bridge', false)
            ->assertDontSee('data-jadidaring-legal-notice', false)
            ->assertDontSee('Syarat &amp; Ketentuan', false);
    }

    public function test_terms_page_explains_that_jadidaring_is_only_a_provider(): void
    {
        CompanyProfile::factory()->published()->create([
            'subdomain' => 'acme',
            'html' => '<html><body>Acme public site</body></html>',
        ]);

        $this->get('http://www.jadidaring.test/acme/syarat-ketentuan')
            ->assertOk()
            ->assertSee('Syarat &amp; Ketentuan', false)
            ->assertSee('hanya menyediakan sarana teknis', false)
            ->assertSee('tidak bertanggung jawab', false)
            ->assertSee('penipuan', false)
            ->assertSee('www.jadidaring.test/acme');

        $this->get('http://www.jadidaring.test/acme/terms')
            ->assertRedirect('http://www.jadidaring.test/acme/syarat-ketentuan');
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
