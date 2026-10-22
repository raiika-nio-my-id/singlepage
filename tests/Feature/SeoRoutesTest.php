<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoRoutesTest extends TestCase
{
    use RefreshDatabase;

    // ── robots.txt ─────────────────────────────────────────────────────────

    public function test_tenant_robots_txt_allows_crawling_and_disallows_api_and_boost(): void
    {
        CompanyProfile::factory()->published()->create(['subdomain' => 'gamehub']);

        $response = $this->get('http://gamehub.jadidaring.test/robots.txt');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=utf-8');
        $response->assertSee('User-agent: *', false);
        $response->assertSee('Allow: /', false);
        $response->assertSee('Disallow: /api/', false);
        $response->assertSee('Disallow: /_boost/', false);
        $response->assertSee('Sitemap: https://gamehub.jadidaring.test/sitemap.xml', false);
    }

    public function test_tenant_robots_txt_includes_correct_host_in_sitemap_url(): void
    {
        CompanyProfile::factory()->published()->create(['subdomain' => 'ulartangga']);

        $response = $this->get('http://ulartangga.jadidaring.test/robots.txt');

        $response->assertOk();
        $response->assertSee('Sitemap: https://ulartangga.jadidaring.test/sitemap.xml', false);
        $response->assertDontSee('gamehub', false);
    }

    public function test_admin_robots_txt_disallows_all_crawling(): void
    {
        $response = $this->get('http://admin.jadidaring.test/robots.txt');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=utf-8');
        $response->assertSee('Disallow: /', false);
    }

    public function test_tenant_robots_txt_requires_live_profile(): void
    {
        CompanyProfile::factory()->create(['subdomain' => 'draft-site']);

        $this->get('http://draft-site.jadidaring.test/robots.txt')->assertNotFound();
    }

    // ── sitemap.xml ────────────────────────────────────────────────────────

    public function test_gamehub_sitemap_lists_all_live_profiles(): void
    {
        CompanyProfile::factory()->published()->create(['subdomain' => 'gamehub']);
        CompanyProfile::factory()->published()->create(['subdomain' => 'ulartangga']);
        CompanyProfile::factory()->published()->create(['subdomain' => 'drawguess']);
        CompanyProfile::factory()->create(['subdomain' => 'draft-game']); // draft – must be excluded

        $response = $this->get('http://gamehub.jadidaring.test/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=utf-8');
        $response->assertSee('http://gamehub.jadidaring.test/', false);
        $response->assertSee('http://ulartangga.jadidaring.test/', false);
        $response->assertSee('http://drawguess.jadidaring.test/', false);
        $response->assertDontSee('draft-game', false);
    }

    public function test_gamehub_sitemap_excludes_expired_profiles(): void
    {
        CompanyProfile::factory()->published()->create(['subdomain' => 'gamehub']);
        CompanyProfile::factory()->published()->expired()->create(['subdomain' => 'old-game']);

        $response = $this->get('http://gamehub.jadidaring.test/sitemap.xml');

        $response->assertOk();
        $response->assertDontSee('old-game', false);
    }

    public function test_non_gamehub_sitemap_lists_only_its_own_url(): void
    {
        CompanyProfile::factory()->published()->create(['subdomain' => 'gamehub']);
        CompanyProfile::factory()->published()->create(['subdomain' => 'ulartangga']);

        $response = $this->get('http://ulartangga.jadidaring.test/sitemap.xml');

        $response->assertOk();
        $response->assertSee('http://ulartangga.jadidaring.test/', false);
        $response->assertDontSee('gamehub', false);
    }

    public function test_sitemap_is_valid_xml(): void
    {
        CompanyProfile::factory()->published()->create(['subdomain' => 'gamehub']);
        CompanyProfile::factory()->published()->create(['subdomain' => 'ulartangga']);

        $response = $this->get('http://gamehub.jadidaring.test/sitemap.xml');

        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml, 'Response body is not valid XML');
    }

    public function test_tenant_sitemap_requires_live_profile(): void
    {
        CompanyProfile::factory()->create(['subdomain' => 'draft-site']);

        $this->get('http://draft-site.jadidaring.test/sitemap.xml')->assertNotFound();
    }

    // ── SeoInjector integration ────────────────────────────────────────────

    public function test_tenant_show_injects_canonical_link(): void
    {
        CompanyProfile::factory()->published()->create([
            'subdomain' => 'ulartangga',
            'html' => '<html><head><title>Game</title></head><body>Play</body></html>',
        ]);

        $this->get('http://ulartangga.jadidaring.test/')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="http://ulartangga.jadidaring.test/">', false);
    }

    public function test_tenant_show_injects_favicon_links(): void
    {
        CompanyProfile::factory()->published()->create([
            'subdomain' => 'ulartangga',
            'html' => '<html><head><title>Game</title></head><body>Play</body></html>',
        ]);

        $this->get('http://ulartangga.jadidaring.test/')
            ->assertOk()
            ->assertSee('<link rel="icon" href="/favicon.ico" sizes="any">', false)
            ->assertSee('<link rel="apple-touch-icon" href="/apple-touch-icon.png">', false);
    }

    public function test_gamehub_show_injects_og_image(): void
    {
        CompanyProfile::factory()->published()->create([
            'subdomain' => 'gamehub',
            'html' => '<html><head><title>Hub</title></head><body>Hub</body></html>',
        ]);

        $this->get('http://gamehub.jadidaring.test/')
            ->assertOk()
            ->assertSee('og-gamehub.jpg', false)
            ->assertSee('summary_large_image', false);
    }

    public function test_non_gamehub_show_does_not_inject_og_image(): void
    {
        CompanyProfile::factory()->published()->create([
            'subdomain' => 'ulartangga',
            'html' => '<html><head><title>Game</title></head><body>Play</body></html>',
        ]);

        $this->get('http://ulartangga.jadidaring.test/')
            ->assertOk()
            ->assertDontSee('og-gamehub.jpg', false);
    }
}
