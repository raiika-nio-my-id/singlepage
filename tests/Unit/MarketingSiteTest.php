<?php

namespace Tests\Unit;

use App\Services\GameHub;
use App\Services\MarketingSite;
use Tests\TestCase;

class MarketingSiteTest extends TestCase
{
    public function test_hosts_are_www_and_the_apex(): void
    {
        $this->assertSame(
            ['www.jadidaring.test', 'jadidaring.test'],
            MarketingSite::hosts(),
        );
        $this->assertSame('www.jadidaring.test', GameHub::host());
    }

    public function test_rewrite_prefixes_relative_and_og_asset_urls(): void
    {
        $html = <<<'HTML'
<link rel="icon" href="images/favicon.svg" type="image/svg+xml" />
<link rel="stylesheet" href="css/styles.css" />
<source srcset="images/buminata.webp" type="image/webp" />
<script src="js/main.js"></script>
<script type="application/ld+json">{"logo":"https://www.jadidaring.com/images/logo.svg"}</script>
<a href="https://www.jadidaring.com/gamehub/">GameHub</a>
HTML;

        $rewritten = MarketingSite::rewriteHtml($html);

        $this->assertStringContainsString('/marketing/css/styles.css', $rewritten);
        $this->assertStringContainsString('/marketing/images/favicon.svg', $rewritten);
        $this->assertStringContainsString('/marketing/images/buminata.webp', $rewritten);
        $this->assertStringContainsString('/marketing/js/main.js', $rewritten);
        $this->assertStringContainsString(
            'https://www.jadidaring.com/marketing/images/logo.svg',
            $rewritten,
        );
        $this->assertStringContainsString('https://www.jadidaring.com/gamehub/', $rewritten);
        $this->assertStringNotContainsString('href="css/styles.css"', $rewritten);
        $this->assertStringNotContainsString('https://www.jadidaring.com/images/', $rewritten);
    }
}
