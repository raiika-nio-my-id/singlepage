<?php

namespace Tests\Unit;

use App\Services\GameHub;
use Illuminate\Http\Request;
use Tests\TestCase;

class GameHubTest extends TestCase
{
    public function test_public_url_uses_the_www_path_and_app_scheme(): void
    {
        $this->assertSame('www.jadidaring.test', GameHub::host());
        $this->assertSame('http://www.jadidaring.test/gamehub', GameHub::publicUrl());
        $this->assertSame('gamehub.jadidaring.test', GameHub::legacyHost());
    }

    public function test_redirect_target_keeps_the_path_and_query_string(): void
    {
        $home = Request::create('http://gamehub.jadidaring.test/', 'GET');
        $terms = Request::create('http://gamehub.jadidaring.test/syarat-ketentuan?from=bookmark', 'GET');
        $api = Request::create('https://gamehub.jadidaring.test/api/game/config?fresh=1', 'GET');

        $this->assertSame('http://www.jadidaring.test/gamehub/', GameHub::redirectTarget($home));
        $this->assertSame(
            'http://www.jadidaring.test/gamehub/syarat-ketentuan?from=bookmark',
            GameHub::redirectTarget($terms),
        );
        $this->assertSame(
            'https://www.jadidaring.test/gamehub/api/game/config?fresh=1',
            GameHub::redirectTarget($api),
        );
    }

    public function test_rewrite_moves_hub_urls_and_leaves_other_games_alone(): void
    {
        $html = <<<'HTML'
<html><head>
<link rel="canonical" href="https://gamehub.jadidaring.test/">
<meta property="og:image" content="https://gamehub.jadidaring.test/og-gamehub.jpg">
</head><body>
<a href="https://gamehub.jadidaring.test/#faq">FAQ</a>
<a href="https://drawquarium.jadidaring.test/">Draw</a>
<a href="https://mygamehub.jadidaring.test/">Not the hub</a>
<a href="/syarat-ketentuan">Terms</a>
<script>fetch("/api/game/config"); new WebSocket("wss://gamehub.jadidaring.test/app/key");</script>
</body></html>
HTML;

        $result = GameHub::rewriteHtml($html);

        $this->assertStringContainsString('href="https://www.jadidaring.test/gamehub/"', $result);
        $this->assertStringContainsString('href="https://www.jadidaring.test/gamehub/#faq"', $result);
        $this->assertStringContainsString('content="https://www.jadidaring.test/og-gamehub.jpg"', $result);
        $this->assertStringContainsString('href="https://drawquarium.jadidaring.test/"', $result);
        $this->assertStringContainsString('href="https://mygamehub.jadidaring.test/"', $result);
        $this->assertStringContainsString('href="/gamehub/syarat-ketentuan"', $result);
        $this->assertStringContainsString('fetch("/gamehub/api/game/config")', $result);
        $this->assertStringContainsString('wss://www.jadidaring.test/app/key', $result);
        $this->assertStringNotContainsString('https://gamehub.jadidaring.test', $result);
        $this->assertStringNotContainsString('wss://gamehub.jadidaring.test', $result);
        $this->assertStringNotContainsString('/gamehub/og-gamehub.jpg', $result);
        $this->assertStringNotContainsString('/gamehub/app/', $result);
    }
}
