<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\CompanyProfileAlias;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_drawquarium_and_langitbersama_are_served_on_the_www_path(): void
    {
        $this->publish('drawquarium', '<html><head></head><body>Aquarium</body></html>');
        $this->publish('langitbersama', '<html><head></head><body>Sky</body></html>');

        $this->get('http://www.jadidaring.test/drawquarium')
            ->assertOk()
            ->assertSee('Aquarium', false)
            ->assertDontSee('Sky', false);

        $this->get('http://www.jadidaring.test/drawquarium/')
            ->assertOk()
            ->assertSee('Aquarium', false);

        $this->get('http://www.jadidaring.test/DrawQuarium/')
            ->assertOk()
            ->assertSee('Aquarium', false);

        $this->get('http://www.jadidaring.test/langitbersama/')
            ->assertOk()
            ->assertSee('Sky', false)
            ->assertDontSee('Aquarium', false);
    }

    public function test_old_subdomain_redirects_permanently_and_keeps_the_suffix(): void
    {
        $this->publish('drawquarium', '<html><body>Aquarium</body></html>');
        $this->publish('langitbersama', '<html><body>Sky</body></html>');

        $this->get('http://drawquarium.jadidaring.test/')
            ->assertStatus(301)
            ->assertRedirect('http://www.jadidaring.test/drawquarium/');

        $this->get('http://drawquarium.jadidaring.test/?room=1')
            ->assertStatus(301)
            ->assertRedirect('http://www.jadidaring.test/drawquarium/?room=1');

        $this->get('http://DRAWQUARIUM.jadidaring.test/play/nested?from=bookmark')
            ->assertStatus(301)
            ->assertRedirect('http://www.jadidaring.test/drawquarium/play/nested?from=bookmark');

        $this->get('https://langitbersama.jadidaring.test/api/game/config?fresh=1')
            ->assertStatus(301)
            ->assertRedirect('https://www.jadidaring.test/langitbersama/api/game/config?fresh=1');

        $this->post('http://drawquarium.jadidaring.test/api/game/rooms', ['displayName' => 'Ada'])
            ->assertStatus(301)
            ->assertRedirect('http://www.jadidaring.test/drawquarium/api/game/rooms');
    }

    public function test_draft_and_expired_profiles_stay_not_found(): void
    {
        CompanyProfile::factory()->create([
            'subdomain' => 'hidden',
            'html' => '<html><body>Hidden</body></html>',
        ]);
        CompanyProfile::factory()->published()->expired()->create([
            'subdomain' => 'oldshop',
            'html' => '<html><body>Offline</body></html>',
        ]);

        $this->get('http://hidden.jadidaring.test/')->assertNotFound();
        $this->get('http://www.jadidaring.test/hidden')->assertNotFound();
        $this->get('http://oldshop.jadidaring.test/play')->assertNotFound();
        $this->get('http://www.jadidaring.test/oldshop/')->assertNotFound();
        $this->get('http://missing.jadidaring.test/')->assertNotFound();
        $this->get('http://www.jadidaring.test/missing')->assertNotFound();
    }

    public function test_aliases_redirect_to_the_primary_path(): void
    {
        $profile = $this->publish('drawquarium', '<html><body>Aquarium</body></html>');
        CompanyProfileAlias::factory()->for($profile)->create(['subdomain' => 'aquarium-old']);

        $this->get('http://aquarium-old.jadidaring.test/faq?x=1')
            ->assertStatus(301)
            ->assertRedirect('http://www.jadidaring.test/drawquarium/faq?x=1');

        $this->get('http://www.jadidaring.test/aquarium-old/faq?x=1')
            ->assertStatus(301)
            ->assertRedirect('http://www.jadidaring.test/drawquarium/faq?x=1');

        $draft = CompanyProfile::factory()->create(['subdomain' => 'draftsite']);
        CompanyProfileAlias::factory()->for($draft)->create(['subdomain' => 'draftalias']);

        $this->get('http://draftalias.jadidaring.test/')->assertNotFound();
        $this->get('http://www.jadidaring.test/draftalias')->assertNotFound();
    }

    public function test_reserved_labels_and_other_hosts_are_not_game_paths(): void
    {
        foreach (['admin', 'api', 'app', 'apps', 'reverb', 'up', 'mail', 'www', 'marketing'] as $label) {
            CompanyProfile::factory()->published()->create([
                'subdomain' => $label,
                'html' => '<html><body>reserved-'.$label.'</body></html>',
            ]);
        }

        $this->get('http://www.jadidaring.test/admin')->assertNotFound();
        $this->get('http://www.jadidaring.test/reverb')->assertNotFound();
        $this->get('http://www.jadidaring.test/app')->assertNotFound();
        $this->get('http://www.jadidaring.test/marketing')->assertNotFound();
        $this->get('http://reverb.jadidaring.test/')->assertNotFound();
        $this->get('http://api.jadidaring.test/play')->assertNotFound();
        $this->get('http://www.jadidaring.test/')
            ->assertOk()
            ->assertSee('JadiDaring', false)
            ->assertDontSee('reserved-admin', false)
            ->assertDontSee('reserved-marketing', false);
        $this->get('http://jadidaring.test/drawquarium')->assertNotFound();
        $this->get('http://admin.jadidaring.test/drawquarium')->assertNotFound();

        $this->get('http://www.jadidaring.test/up')
            ->assertOk()
            ->assertDontSee('reserved-up', false);
    }

    public function test_gamehub_path_still_serves_the_hub(): void
    {
        CompanyProfile::factory()->published()->create([
            'subdomain' => 'gamehub',
            'html' => '<html><body>JaRing hub</body></html>',
        ]);
        $this->publish('drawquarium', '<html><body>Aquarium</body></html>');

        $this->get('http://www.jadidaring.test/gamehub')
            ->assertOk()
            ->assertSee('JaRing hub', false)
            ->assertDontSee('Aquarium', false);

        $this->get('http://www.jadidaring.test/gamehub/')
            ->assertOk()
            ->assertSee('JaRing hub', false);

        $this->get('http://gamehub.jadidaring.test/play?room=1')
            ->assertStatus(301)
            ->assertRedirect('http://www.jadidaring.test/gamehub/play?room=1');

        $this->get('http://www.jadidaring.test/GameHub/play')
            ->assertStatus(301)
            ->assertRedirect('http://www.jadidaring.test/gamehub/play');
    }

    public function test_served_html_rewrites_api_terms_and_websocket_links(): void
    {
        $this->publish('drawquarium', <<<'HTML'
<html><head><link rel="canonical" href="https://drawquarium.jadidaring.test/"></head><body>
<a href="/syarat-ketentuan">Terms</a>
<a href="https://langitbersama.jadidaring.test/play">Sky</a>
<a href="https://admin.jadidaring.test/login">Admin</a>
<script>fetch("/api/game/config"); new WebSocket("wss://drawquarium.jadidaring.test/app/key");</script>
</body></html>
HTML);

        $this->get('http://www.jadidaring.test/drawquarium')
            ->assertOk()
            ->assertSee('href="https://www.jadidaring.test/drawquarium/"', false)
            ->assertSee('href="/drawquarium/syarat-ketentuan"', false)
            ->assertSee('href="https://www.jadidaring.test/langitbersama/play"', false)
            ->assertSee('href="https://admin.jadidaring.test/login"', false)
            ->assertSee('fetch("/drawquarium/api/game/config")', false)
            ->assertSee('wss://www.jadidaring.test/app/key', false)
            ->assertDontSee('drawquarium.jadidaring.test/', false)
            ->assertSee('http://www.jadidaring.test/drawquarium/syarat-ketentuan', false);
    }

    public function test_path_api_config_rooms_and_scores_stay_isolated(): void
    {
        $this->publish('drawquarium', '<html><body>Aquarium</body></html>');
        $this->publish('langitbersama', '<html><body>Sky</body></html>');

        $config = $this->getJson('http://www.jadidaring.test/drawquarium/api/game/config')
            ->assertOk()
            ->assertJsonPath('game', 'drawquarium')
            ->assertJsonPath('api', '/drawquarium/api/game')
            ->assertJsonPath('host', 'www.jadidaring.test')
            ->assertJsonPath('websocket.channelPrefix', 'game.drawquarium')
            ->assertJsonPath('database.isolatedBy', 'subdomain');

        $this->assertStringContainsString(
            'ws://www.jadidaring.test/app/test-reverb-key',
            (string) $config->json('websocket.url'),
        );
        $this->assertStringNotContainsString('drawquarium.jadidaring.test', (string) $config->getContent());

        $created = $this->postJson('http://www.jadidaring.test/drawquarium/api/game/rooms', [
            'displayName' => 'Ada',
            'visibility' => 'public',
            'state' => ['ok' => true],
        ])->assertOk()->json();

        $this->getJson('http://www.jadidaring.test/drawquarium/api/game/rooms/'.$created['code'], [
            'Authorization' => 'Bearer '.$created['playerToken'],
        ])->assertOk()->assertJsonPath('code', $created['code']);

        $this->getJson('http://www.jadidaring.test/langitbersama/api/game/rooms/'.$created['code'], [
            'Authorization' => 'Bearer '.$created['playerToken'],
        ])->assertNotFound();

        $this->postJson('http://www.jadidaring.test/drawquarium/api/game/scores', [
            'name' => 'Ada',
            'score' => 12,
        ])->assertOk();

        $this->getJson('http://www.jadidaring.test/langitbersama/api/game/scores')
            ->assertOk()
            ->assertExactJson(['scores' => []]);

        $this->getJson('http://www.jadidaring.test/drawquarium/api/game/scores')
            ->assertOk()
            ->assertJsonPath('scores.0.name', 'Ada')
            ->assertJsonPath('scores.0.score', 12);
    }

    public function test_a_path_visit_is_counted_and_the_old_host_is_not(): void
    {
        $this->publish('drawquarium', '<html><body>Aquarium</body></html>');

        $this->get('http://drawquarium.jadidaring.test/')->assertStatus(301);
        $this->assertSame(0, $this->views('drawquarium'));

        $this->get('http://www.jadidaring.test/drawquarium/')->assertOk();
        $this->assertSame(1, $this->views('drawquarium'));
    }

    public function test_served_html_passes_the_connection_identity_check_on_www(): void
    {
        $source = <<<'HTML'
<!doctype html><html><head><title>Tank</title></head><body>
<script id="dq-net">
function gameSubdomain(hostname) { const m = /^([a-z0-9-]+)\.jadidaring\.(?:com|test)$/i.exec(String(hostname || '')); return m ? m[1].toLowerCase() : null; }
</script>
</body></html>
HTML;

        $this->publish('drawquarium', $source);
        $this->publish('langitbersama', str_replace('Tank', 'Sky', $source));

        $draw = $this->get('http://www.jadidaring.test/drawquarium/');
        $draw->assertOk();
        $this->assertGameIdentity($draw->getContent(), 'drawquarium', '/drawquarium/');

        $sky = $this->get('http://www.jadidaring.test/langitbersama/');
        $sky->assertOk();
        $this->assertGameIdentity($sky->getContent(), 'langitbersama', '/langitbersama/');
    }

    public function test_terms_robots_and_sitemap_use_the_path(): void
    {
        $this->publish('langitbersama', '<html><body>Sky</body></html>');

        $this->get('http://www.jadidaring.test/langitbersama/syarat-ketentuan')
            ->assertOk()
            ->assertSee('www.jadidaring.test/langitbersama', false)
            ->assertSee('http://www.jadidaring.test/langitbersama/', false);

        $this->get('http://www.jadidaring.test/langitbersama/terms?from=footer')
            ->assertRedirect('http://www.jadidaring.test/langitbersama/syarat-ketentuan?from=footer');

        $this->get('http://www.jadidaring.test/langitbersama/robots.txt')
            ->assertOk()
            ->assertSee('Allow: /langitbersama/', false)
            ->assertSee('Disallow: /langitbersama/api/', false)
            ->assertSee('Sitemap: https://www.jadidaring.test/langitbersama/sitemap.xml', false);

        $this->get('http://www.jadidaring.test/langitbersama/sitemap.xml')
            ->assertOk()
            ->assertSee('http://www.jadidaring.test/langitbersama/', false)
            ->assertDontSee('langitbersama.jadidaring.test', false);
    }

    private function publish(string $subdomain, string $html): CompanyProfile
    {
        return CompanyProfile::factory()->published()->create([
            'subdomain' => $subdomain,
            'html' => $html,
        ]);
    }

    private function views(string $subdomain): int
    {
        return DB::table('page_view_records')
            ->join('company_profiles', 'company_profiles.id', '=', 'page_view_records.company_profile_id')
            ->where('company_profiles.subdomain', $subdomain)
            ->count();
    }
}
