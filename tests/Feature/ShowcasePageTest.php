<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowcasePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_apex_and_www_serve_showcase_without_the_unique_tab(): void
    {
        foreach ([
            'http://www.jadidaring.test/showcase',
            'http://www.jadidaring.test/showcase/',
            'http://jadidaring.test/showcase/',
        ] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('Selected Demos', false)
                ->assertSee('id="demoGrid"', false)
                ->assertSee('"Event"', false)
                ->assertSee('class="brand__mark"', false)
                ->assertSee('M1307 2788', false)
                ->assertSee('/marketing/images/logo-mark.svg', false)
                ->assertDontSee('Unique', false)
                ->assertDontSee('role="tab"', false)
                ->assertDontSee('data-tab="customs"', false);
        }
    }

    public function test_a_stored_showcase_profile_loses_the_unique_tab_and_gains_the_mark(): void
    {
        CompanyProfile::factory()->published()->create([
            'subdomain' => 'showcase',
            'html' => <<<'HTML'
<html><head><link rel="icon" href="/favicon.ico"></head><body>
<a class="brand" href="/">JaRing</a>
<div class="showcase-tabs" role="tablist"><button data-tab="unique-web" role="tab">Common</button><button data-tab="customs" role="tab">Unique</button></div>
<div id="customsPanel" role="tabpanel"><div id="customGrid"></div></div>
<p>PROFILE-SHOWCASE</p>
</body></html>
HTML,
        ]);

        $this->get('http://jadidaring.test/showcase/')
            ->assertOk()
            ->assertSee('PROFILE-SHOWCASE', false)
            ->assertSee('class="brand__mark"', false)
            ->assertSee('href="/marketing/images/logo-mark.svg"', false)
            ->assertDontSee('Unique', false)
            ->assertDontSee('role="tab"', false)
            ->assertDontSee('showcase-tabs', false)
            ->assertDontSee('customsPanel', false);
    }

    public function test_homepage_showcase_links_go_to_the_showcase_path(): void
    {
        $this->get('http://www.jadidaring.test/')
            ->assertOk()
            ->assertSee('href="/showcase/"', false)
            ->assertDontSee('href="#showcase"', false);
    }
}
