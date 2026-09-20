<?php

namespace Tests\Unit;

use App\Http\Controllers\TenantController;
use App\Models\CompanyProfile;
use App\Services\LegalNoticeInjector;
use Illuminate\Http\Request;
use Tests\TestCase;

class TenantLegalNoticeTest extends TestCase
{
    public function test_tenant_response_includes_terms_footer_for_any_user_html(): void
    {
        $profile = new CompanyProfile([
            'subdomain' => 'acme',
            'html' => '<html><body>Acme public site</body></html>',
        ]);

        $request = Request::create('http://acme.jadidaring.test/', 'GET');
        $request->attributes->set('tenantProfile', $profile);

        $response = (new TenantController)->show($request, new LegalNoticeInjector);

        $html = $response->getContent();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Acme public site', $html);
        $this->assertStringContainsString('data-jadidaring-legal-notice', $html);
        $this->assertStringContainsString('Syarat &amp; Ketentuan', $html);
        $this->assertStringContainsString('https://jadidaring.com/', $html);
        $this->assertStringContainsString('>Jadidaring</a>', $html);
        $this->assertStringNotContainsString('bukan tanggung jawab', $html);
        $this->assertStringContainsString('http://acme.jadidaring.test/syarat-ketentuan', $html);
    }

    public function test_hidden_footer_profile_is_served_without_the_platform_notice(): void
    {
        $profile = new CompanyProfile([
            'subdomain' => 'acme',
            'html' => '<html><body>Acme public site</body></html>',
            'hide_footer' => true,
        ]);

        $request = Request::create('http://acme.jadidaring.test/', 'GET');
        $request->attributes->set('tenantProfile', $profile);

        $html = (new TenantController)->show($request, new LegalNoticeInjector)->getContent();

        $this->assertSame('<html><body>Acme public site</body></html>', $html);
        $this->assertStringNotContainsString('data-jadidaring-legal-notice', $html);
        $this->assertStringNotContainsString('Syarat &amp; Ketentuan', $html);
    }

    public function test_terms_page_is_rendered_from_the_tenant_controller(): void
    {
        $profile = new CompanyProfile([
            'subdomain' => 'acme',
            'html' => '<html><body>Acme public site</body></html>',
        ]);

        $request = Request::create('http://acme.jadidaring.test/syarat-ketentuan', 'GET');
        $request->attributes->set('tenantProfile', $profile);

        $html = (new TenantController)->terms($request)->render();

        $this->assertStringContainsString('Syarat &amp; Ketentuan', $html);
        $this->assertStringContainsString('hanya menyediakan sarana teknis', $html);
        $this->assertStringContainsString('acme.jadidaring.test', $html);
    }

    public function test_terms_view_states_that_jadidaring_is_only_the_provider(): void
    {
        $html = view('tenant.terms', [
            'platformName' => 'Jadidaring',
            'updatedOn' => '20 September 2026',
            'siteHost' => 'acme.jadidaring.test',
            'homeUrl' => 'http://acme.jadidaring.test/',
        ])->render();

        $this->assertStringContainsString('hanya menyediakan sarana teknis', $html);
        $this->assertStringContainsString('tidak bertanggung jawab', $html);
        $this->assertStringContainsString('penipuan', $html);
        $this->assertStringContainsString('acme.jadidaring.test', $html);
        $this->assertStringContainsString('Kembali ke website', $html);
        $this->assertStringContainsString('data-theme-toggle', $html);
        $this->assertStringContainsString('js/theme.js', $html);
    }
}
