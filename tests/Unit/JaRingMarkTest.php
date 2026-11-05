<?php

namespace Tests\Unit;

use App\Services\JaRingMark;
use App\Services\ShowcasePage;
use Tests\TestCase;

class JaRingMarkTest extends TestCase
{
    public function test_apply_inserts_the_homepage_monogram_and_retargets_the_favicon(): void
    {
        $html = <<<'HTML'
<html><head><link rel="icon" href="data:image/svg+xml,old"></head>
<body><a class="brand" href="/">JaRing</a></body></html>
HTML;

        $out = JaRingMark::apply($html);

        $this->assertStringContainsString('class="brand__mark"', $out);
        $this->assertStringContainsString('M1307 2788', $out);
        $this->assertStringContainsString('fill="#D4FF3A"', $out);
        $this->assertStringContainsString('href="/marketing/images/logo-mark.svg"', $out);
        $this->assertStringNotContainsString('data:image/svg+xml,old', $out);
        $this->assertSame(1, substr_count($out, 'class="brand__mark"'));
        $this->assertSame(1, substr_count(JaRingMark::apply($out), 'class="brand__mark"'));
    }

    public function test_unique_tab_markup_is_removed_without_leaving_a_tablist(): void
    {
        $html = <<<'HTML'
<div class="showcase-tabs" role="tablist" aria-label="Demo categories">
<button class="showcase-tab" data-tab="unique-web" role="tab">Common</button>
<button class="showcase-tab" data-tab="customs" role="tab">Unique</button>
</div>
<div class="demo-panel" id="uniqueWebPanel" role="tabpanel" aria-labelledby="uniqueWebTab"><div id="demoGrid"></div></div>
<div class="demo-panel" id="customsPanel" role="tabpanel" hidden><div id="customGrid"></div></div>
HTML;

        $out = ShowcasePage::withoutUniqueTab($html);

        $this->assertStringNotContainsString('Unique', $out);
        $this->assertStringNotContainsString('showcase-tabs', $out);
        $this->assertStringNotContainsString('role="tab"', $out);
        $this->assertStringNotContainsString('customsPanel', $out);
        $this->assertStringContainsString('id="demoGrid"', $out);
        $this->assertStringContainsString('id="uniqueWebPanel"', $out);
        $this->assertStringNotContainsString('tabpanel', $out);
    }
}
