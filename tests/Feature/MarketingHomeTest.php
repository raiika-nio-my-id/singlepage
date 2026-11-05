<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingHomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_www_and_apex_serve_the_marketing_homepage(): void
    {
        foreach (['http://www.jadidaring.test/', 'http://jadidaring.test/'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('JadiDaring', false)
                ->assertSee('JaRing', false)
                ->assertSee('GameHub', false)
                ->assertSee('/marketing/images/favicon.svg', false)
                ->assertSee('/marketing/css/styles.css', false)
                ->assertSee('/marketing/js/main.js', false)
                ->assertSee('class="wordmark__mark"', false)
                ->assertSee('https://www.jadidaring.com/gamehub/', false)
                ->assertSee('https://www.jadidaring.com/marketing/images/logo.svg', false)
                ->assertDontSee('href="css/styles.css"', false)
                ->assertDontSee('jaring-icon', false)
                ->assertDontSee('og-image.png', false);
        }
    }

    public function test_marketing_root_does_not_steal_tenant_paths(): void
    {
        CompanyProfile::factory()->published()->create([
            'subdomain' => 'drawquarium',
            'html' => '<html><body>Aquarium</body></html>',
        ]);
        CompanyProfile::factory()->published()->create([
            'subdomain' => 'gamehub',
            'html' => '<html><body>JaRing hub</body></html>',
        ]);

        $this->get('http://www.jadidaring.test/')
            ->assertOk()
            ->assertSee('JadiDaring', false)
            ->assertDontSee('Aquarium', false)
            ->assertDontSee('JaRing hub', false);

        $this->get('http://www.jadidaring.test/drawquarium/')
            ->assertOk()
            ->assertSee('Aquarium', false)
            ->assertDontSee('JadiDaring — Studio Digital', false);

        $this->get('http://www.jadidaring.test/gamehub/')
            ->assertOk()
            ->assertSee('JaRing hub', false)
            ->assertDontSee('JadiDaring — Studio Digital', false);

        $this->get('http://jadidaring.test/drawquarium')->assertNotFound();
        $this->get('http://admin.jadidaring.test/')->assertRedirect();
    }

    public function test_generated_logo_assets_are_not_shipped(): void
    {
        $html = $this->get('http://www.jadidaring.test/')->assertOk()->getContent();

        foreach (['jaring-icon', 'jaring-mark', 'favicon-32', 'og-image.png', 'apple-touch-icon.png'] as $needle) {
            $this->assertStringNotContainsString($needle, $html);
        }

        $this->assertFileDoesNotExist(public_path('marketing/images/jaring-icon.png'));
        $this->assertFileDoesNotExist(public_path('marketing/images/og-image.png'));
        $this->assertDirectoryDoesNotExist(public_path('marketing/brand'));
        $this->assertFileExists(public_path('marketing/images/logo.svg'));
        $this->assertFileExists(public_path('marketing/images/favicon.svg'));
        $this->assertFileExists(public_path('marketing/images/buminata.webp'));
    }

    public function test_marketing_label_is_not_a_game_path(): void
    {
        CompanyProfile::factory()->published()->create([
            'subdomain' => 'marketing',
            'html' => '<html><body>reserved-marketing</body></html>',
        ]);

        $this->get('http://www.jadidaring.test/marketing')
            ->assertNotFound()
            ->assertDontSee('reserved-marketing', false);

        $this->get('http://www.jadidaring.test/marketing/')
            ->assertNotFound()
            ->assertDontSee('reserved-marketing', false);
    }

    public function test_root_robots_and_sitemap_do_not_replace_tenant_seo(): void
    {
        CompanyProfile::factory()->published()->create(['subdomain' => 'gamehub']);
        CompanyProfile::factory()->published()->create(['subdomain' => 'drawquarium']);

        $this->get('http://www.jadidaring.test/robots.txt')
            ->assertOk()
            ->assertSee('Allow: /', false)
            ->assertSee('Disallow: /_boost/', false)
            ->assertSee('Sitemap: https://www.jadidaring.com/sitemap.xml', false)
            ->assertSee('Sitemap: https://www.jadidaring.com/gamehub/sitemap.xml', false);

        $this->get('http://jadidaring.test/sitemap.xml')
            ->assertOk()
            ->assertSee('https://www.jadidaring.com/', false)
            ->assertDontSee('drawquarium', false);

        $this->get('http://www.jadidaring.test/gamehub/sitemap.xml')
            ->assertOk()
            ->assertSee('http://www.jadidaring.test/gamehub/', false)
            ->assertSee('http://www.jadidaring.test/drawquarium/', false);

        $this->get('http://www.jadidaring.test/drawquarium/robots.txt')
            ->assertOk()
            ->assertSee('Allow: /drawquarium/', false);

        $this->get('http://admin.jadidaring.test/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /', false)
            ->assertDontSee('Sitemap:', false);
    }
}
