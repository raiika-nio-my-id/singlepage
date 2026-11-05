<?php

namespace Tests\Unit;

use App\Services\GameHub;
use App\Services\TenantPath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class TenantPathTest extends TestCase
{
    public function test_public_url_uses_the_www_path_and_app_scheme(): void
    {
        $this->assertSame('www.jadidaring.test', TenantPath::host());
        $this->assertSame('http://www.jadidaring.test/drawquarium', TenantPath::publicUrl('drawquarium'));
        $this->assertSame('www.jadidaring.test/drawquarium', TenantPath::label('DrawQuarium'));
        $this->assertSame('http://www.jadidaring.test/gamehub', TenantPath::publicUrl('gamehub'));
        $this->assertSame(GameHub::publicUrl(), TenantPath::publicUrl('gamehub'));
        $this->assertSame('drawquarium.jadidaring.test', TenantPath::legacyHost('drawquarium'));
    }

    public function test_gamehub_host_override_is_the_public_host_for_every_game(): void
    {
        Config::set('singlepage.gamehub.host', 'play.jadidaring.test');

        $this->assertSame('play.jadidaring.test', TenantPath::host());
        $this->assertSame('http://play.jadidaring.test/langitbersama', TenantPath::publicUrl('langitbersama'));
        $this->assertSame('http://play.jadidaring.test/gamehub', GameHub::publicUrl());
    }

    public function test_redirect_target_keeps_the_suffix_from_a_retired_host(): void
    {
        $home = Request::create('http://drawquarium.jadidaring.test/', 'GET');
        $nested = Request::create('http://DRAWQUARIUM.jadidaring.test/play/room?x=1', 'GET');
        $api = Request::create('https://langitbersama.jadidaring.test/api/game/config?fresh=1', 'GET');
        $queryOnly = Request::create('http://drawquarium.jadidaring.test/?room=1', 'GET');

        $this->assertSame('http://www.jadidaring.test/drawquarium/', TenantPath::redirectTarget($home, 'drawquarium'));
        $this->assertSame(
            'http://www.jadidaring.test/drawquarium/play/room?x=1',
            TenantPath::redirectTarget($nested, 'drawquarium'),
        );
        $this->assertSame(
            'https://www.jadidaring.test/langitbersama/api/game/config?fresh=1',
            TenantPath::redirectTarget($api, 'langitbersama'),
        );
        $this->assertSame(
            'http://www.jadidaring.test/drawquarium/?room=1',
            TenantPath::redirectTarget($queryOnly, 'drawquarium'),
        );
    }

    public function test_redirect_target_replaces_an_alias_segment_on_the_public_host(): void
    {
        $alias = Request::create('http://www.jadidaring.test/oldname/faq?x=1', 'GET');
        $root = Request::create('https://www.jadidaring.test/oldname', 'GET');
        $hubAlias = Request::create('http://www.jadidaring.test/oldhub/play?room=1', 'GET');

        $this->assertSame(
            'http://www.jadidaring.test/drawquarium/faq?x=1',
            TenantPath::redirectTarget($alias, 'drawquarium'),
        );
        $this->assertSame(
            'https://www.jadidaring.test/langitbersama/',
            TenantPath::redirectTarget($root, 'langitbersama'),
        );
        $this->assertSame(
            'http://www.jadidaring.test/gamehub/play?room=1',
            TenantPath::redirectTarget($hubAlias, 'gamehub'),
        );
    }

    public function test_rewrite_moves_game_hosts_and_leaves_system_hosts_alone(): void
    {
        $html = <<<'HTML'
<html><head>
<link rel="canonical" href="https://drawquarium.jadidaring.test/">
</head><body>
<a href="https://drawquarium.jadidaring.test/play?room=1">Play</a>
<a href="https://langitbersama.jadidaring.test/#sky">Sky</a>
<a href="https://admin.jadidaring.test/login">Admin</a>
<a href="https://www.jadidaring.test/gamehub/">Hub</a>
<a href="mailto:ada@drawquarium.jadidaring.test">Mail</a>
<a href="/syarat-ketentuan">Terms</a>
<script>fetch("/api/game/config"); new WebSocket("wss://drawquarium.jadidaring.test/app/key");</script>
</body></html>
HTML;

        $result = TenantPath::rewriteHtml($html, 'drawquarium');

        $this->assertStringContainsString('href="https://www.jadidaring.test/drawquarium/"', $result);
        $this->assertStringContainsString('href="https://www.jadidaring.test/drawquarium/play?room=1"', $result);
        $this->assertStringContainsString('href="https://www.jadidaring.test/langitbersama/#sky"', $result);
        $this->assertStringContainsString('href="https://admin.jadidaring.test/login"', $result);
        $this->assertStringContainsString('href="https://www.jadidaring.test/gamehub/"', $result);
        $this->assertStringContainsString('mailto:ada@drawquarium.jadidaring.test', $result);
        $this->assertStringContainsString('href="/drawquarium/syarat-ketentuan"', $result);
        $this->assertStringContainsString('fetch("/drawquarium/api/game/config")', $result);
        $this->assertStringContainsString('wss://www.jadidaring.test/app/key', $result);
        $this->assertStringNotContainsString('drawquarium.jadidaring.test/', $result);
        $this->assertStringNotContainsString('/drawquarium/app/', $result);
    }

    public function test_game_subdomain_helper_uses_the_path_on_the_public_host(): void
    {
        $spaced = <<<'HTML'
<script>
function gameSubdomain(hostname) { const m = /^([a-z0-9-]+)\.jadidaring\.(?:com|test)$/i.exec(String(hostname || '')); return m ? m[1].toLowerCase() : null; }
</script>
HTML;
        $minified = <<<'HTML'
<script>function gameSubdomain(hostname){const m=/^([a-z0-9-]+)\.jadidaring\.(?:com|test)$/i.exec(String(hostname||''));return m?m[1].toLowerCase():null;}</script>
HTML;
        $exported = <<<'HTML'
<script type="text/plain">
export function gameSubdomain(hostname) {
 return /^([a-z0-9-]+)\.jadidaring\.(?:com|test)$/i.exec(hostname)?.[1].toLowerCase()||null;
}
</script>
HTML;

        foreach (['drawquarium' => $spaced, 'langitbersama' => $spaced, 'coratcoret' => $minified, 'mahjong' => $exported] as $game => $html) {
            $rewritten = TenantPath::rewriteHtml($html, $game);
            $again = TenantPath::rewriteHtml($rewritten, $game);

            $this->assertSame($rewritten, $again, $game);
            $this->assertGameIdentity($rewritten, $game);
        }

        $custom = '<script>function gameSubdomain(hostname) { return String(hostname || ""); }</script>';
        $this->assertSame($custom, TenantPath::rewriteHtml($custom, 'drawquarium'));
    }

    public function test_reserved_infrastructure_labels_are_blocked(): void
    {
        foreach (['www', 'admin', 'api', 'app', 'apps', 'reverb', 'up', 'gamehub', 'mail'] as $label) {
            $this->assertTrue(TenantPath::isBlockedLabel($label), $label);
        }

        $this->assertFalse(TenantPath::isBlockedLabel('drawquarium'));
        $this->assertTrue(TenantPath::isHostLabel('drawquarium'));
        $this->assertFalse(TenantPath::isHostLabel('not a label'));
        $this->assertFalse(TenantPath::isHostLabel('a-'));
    }
}
