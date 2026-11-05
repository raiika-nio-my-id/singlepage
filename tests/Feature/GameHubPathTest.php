<?php

namespace Tests\Feature;

use App\Events\GameRoomUpdated;
use App\Models\CompanyProfile;
use App\Models\CompanyProfileAlias;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class GameHubPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_path_serves_the_hub_and_rewrites_its_canonical_url(): void
    {
        $this->hub('<html><head><link rel="canonical" href="https://gamehub.jadidaring.test/"></head><body>JaRing hub <a href="https://drawquarium.jadidaring.test/">Draw</a><a href="/syarat-ketentuan">Terms</a></body></html>');

        $this->get('http://www.jadidaring.test/gamehub')
            ->assertOk()
            ->assertSee('JaRing hub', false)
            ->assertSee('href="https://www.jadidaring.test/gamehub/"', false)
            ->assertSee('href="https://drawquarium.jadidaring.test/"', false)
            ->assertSee('href="/gamehub/syarat-ketentuan"', false)
            ->assertSee('http://www.jadidaring.test/gamehub/syarat-ketentuan', false)
            ->assertDontSee('gamehub.jadidaring.test', false);

        $this->get('http://www.jadidaring.test/gamehub/')
            ->assertOk()
            ->assertSee('JaRing hub', false);
    }

    public function test_old_subdomain_redirects_permanently_and_keeps_the_suffix(): void
    {
        $this->hub('<html><body>JaRing hub</body></html>');

        $this->get('http://gamehub.jadidaring.test/')
            ->assertStatus(301)
            ->assertRedirect('http://www.jadidaring.test/gamehub/');

        $this->get('http://gamehub.jadidaring.test/syarat-ketentuan?from=bookmark')
            ->assertStatus(301)
            ->assertRedirect('http://www.jadidaring.test/gamehub/syarat-ketentuan?from=bookmark');

        $this->get('http://gamehub.jadidaring.test/sitemap.xml')
            ->assertStatus(301)
            ->assertRedirect('http://www.jadidaring.test/gamehub/sitemap.xml');

        $this->get('http://GAMEHUB.jadidaring.test/robots.txt')
            ->assertStatus(301)
            ->assertRedirect('http://www.jadidaring.test/gamehub/robots.txt');
    }

    public function test_old_subdomain_redirects_even_when_the_hub_profile_is_unpublished(): void
    {
        CompanyProfile::factory()->create([
            'subdomain' => 'gamehub',
            'html' => '<html><body>Hidden hub</body></html>',
        ]);

        $this->get('http://gamehub.jadidaring.test/play?room=1')
            ->assertStatus(301)
            ->assertRedirect('http://www.jadidaring.test/gamehub/play?room=1');

        $this->get('http://www.jadidaring.test/gamehub')->assertNotFound();
    }

    public function test_other_game_subdomains_stay_on_their_own_host(): void
    {
        $this->hub('<html><body>JaRing hub</body></html>');
        CompanyProfile::factory()->published()->create([
            'subdomain' => 'drawquarium',
            'html' => '<html><body>Aquarium</body></html>',
        ]);

        $this->get('http://drawquarium.jadidaring.test/')
            ->assertOk()
            ->assertSee('Aquarium', false)
            ->assertDontSee('JaRing hub', false)
            ->assertDontSee('og-gamehub.jpg', false);

        $this->get('http://drawquarium.jadidaring.test/gamehub')->assertNotFound();
        $this->get('http://www.jadidaring.test/')->assertNotFound();
        $this->get('http://jadidaring.test/gamehub')->assertNotFound();
        $this->get('http://admin.jadidaring.test/gamehub')->assertNotFound();
    }

    public function test_hub_robots_and_sitemap_list_the_path_and_live_games(): void
    {
        $this->hub('<html><body>JaRing hub</body></html>');
        CompanyProfile::factory()->published()->create(['subdomain' => 'drawquarium']);
        CompanyProfile::factory()->create(['subdomain' => 'draft-game']);

        $this->get('http://www.jadidaring.test/gamehub/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
            ->assertSee('Allow: /gamehub/', false)
            ->assertSee('Disallow: /gamehub/api/', false)
            ->assertSee('Sitemap: https://www.jadidaring.test/gamehub/sitemap.xml', false);

        $this->get('http://www.jadidaring.test/gamehub/sitemap.xml')
            ->assertOk()
            ->assertSee('http://www.jadidaring.test/gamehub/', false)
            ->assertSee('http://drawquarium.jadidaring.test/', false)
            ->assertDontSee('gamehub.jadidaring.test', false)
            ->assertDontSee('draft-game', false);

        $this->get('http://drawquarium.jadidaring.test/sitemap.xml')
            ->assertOk()
            ->assertSee('http://drawquarium.jadidaring.test/', false)
            ->assertDontSee('gamehub', false);
    }

    public function test_hub_terms_page_links_back_to_the_path(): void
    {
        $this->hub('<html><body>JaRing hub</body></html>');

        $this->get('http://www.jadidaring.test/gamehub/syarat-ketentuan')
            ->assertOk()
            ->assertSee('www.jadidaring.test/gamehub', false)
            ->assertSee('http://www.jadidaring.test/gamehub/', false);

        $this->get('http://www.jadidaring.test/gamehub/terms')
            ->assertRedirect('http://www.jadidaring.test/gamehub/syarat-ketentuan');
    }

    public function test_alias_of_the_hub_profile_redirects_to_the_path(): void
    {
        $profile = $this->hub('<html><body>JaRing hub</body></html>');
        CompanyProfileAlias::factory()->for($profile)->create(['subdomain' => 'oldhub']);

        $this->get('http://oldhub.jadidaring.test/faq?x=1')
            ->assertStatus(301)
            ->assertRedirect('http://www.jadidaring.test/gamehub/faq?x=1');
    }

    public function test_hub_api_follows_the_path_and_other_games_keep_their_api(): void
    {
        Event::fake([GameRoomUpdated::class]);

        $this->hub('<html><body>JaRing hub</body></html>');
        CompanyProfile::factory()->published()->create(['subdomain' => 'drawquarium']);

        $this->get('http://gamehub.jadidaring.test/api/game/config')
            ->assertStatus(301)
            ->assertRedirect('http://www.jadidaring.test/gamehub/api/game/config');

        $config = $this->getJson('http://www.jadidaring.test/gamehub/api/game/config')
            ->assertOk()
            ->assertJsonPath('game', 'gamehub')
            ->assertJsonPath('api', '/gamehub/api/game')
            ->assertJsonPath('host', 'www.jadidaring.test');

        $this->assertStringContainsString(
            'ws://www.jadidaring.test/app/test-reverb-key',
            (string) $config->json('websocket.url'),
        );

        $created = $this->postJson('http://www.jadidaring.test/gamehub/api/game/rooms', [
            'displayName' => 'Ada',
            'visibility' => 'public',
            'state' => ['ok' => true],
        ])->assertOk()->json();

        $this->getJson('http://www.jadidaring.test/gamehub/api/game/rooms/'.$created['code'], [
            'Authorization' => 'Bearer '.$created['playerToken'],
        ])->assertOk()->assertJsonPath('code', $created['code']);

        $this->getJson('http://drawquarium.jadidaring.test/api/game/rooms/'.$created['code'], [
            'Authorization' => 'Bearer '.$created['playerToken'],
        ])->assertNotFound();

        $this->getJson('http://drawquarium.jadidaring.test/api/game/config')
            ->assertOk()
            ->assertJsonPath('game', 'drawquarium')
            ->assertJsonPath('api', '/api/game');
    }

    public function test_gamehub_name_stays_reserved_for_the_existing_profile(): void
    {
        $admin = User::factory()->admin()->create();
        $profile = $this->hub('<html><body>JaRing hub</body></html>');

        $this->actingAs($admin)
            ->from('http://admin.jadidaring.test/clients/create')
            ->post('http://admin.jadidaring.test/clients', [
                'name' => 'Second Hub',
                'email' => 'second-hub@example.com',
                'subdomain' => 'gamehub',
                'html' => '<html><body>Nope</body></html>',
                'expires_at' => now()->addYear()->toDateString(),
            ])
            ->assertSessionHasErrors('subdomain');

        $this->actingAs($admin)
            ->from('http://admin.jadidaring.test/clients/'.$profile->id.'/edit')
            ->put('http://admin.jadidaring.test/clients/'.$profile->id, [
                'subdomain' => 'renamed-hub',
                'name' => $profile->user->name,
                'email' => $profile->user->email,
                'html' => '<html><body>JaRing hub</body></html>',
                'expires_at' => now()->addYear()->toDateString(),
            ])
            ->assertSessionHasErrors('subdomain');

        $this->actingAs($admin)
            ->from('http://admin.jadidaring.test/clients/'.$profile->id.'/edit')
            ->put('http://admin.jadidaring.test/clients/'.$profile->id, [
                'subdomain' => 'gamehub',
                'name' => $profile->user->name,
                'email' => $profile->user->email,
                'html' => '<html><body>Updated hub</body></html>',
                'expires_at' => now()->addYear()->toDateString(),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame('gamehub', $profile->fresh()->subdomain);
        $this->assertSame('<html><body>Updated hub</body></html>', $profile->fresh()->html);

        $other = CompanyProfile::factory()->create(['subdomain' => 'drawquarium']);

        $this->actingAs($admin)
            ->from('http://admin.jadidaring.test/clients/'.$other->id.'/edit')
            ->post('http://admin.jadidaring.test/clients/'.$other->id.'/aliases', [
                'alias' => 'gamehub',
            ])
            ->assertSessionHasErrors('alias');
    }

    public function test_admin_links_use_the_path_url(): void
    {
        $admin = User::factory()->admin()->create();
        $profile = $this->hub('<html><body>JaRing hub</body></html>');

        $this->actingAs($admin)
            ->get('http://admin.jadidaring.test/clients')
            ->assertOk()
            ->assertSee('href="http://www.jadidaring.test/gamehub"', false)
            ->assertSee('www.jadidaring.test/gamehub', false)
            ->assertDontSee('gamehub.jadidaring.test', false);

        $this->actingAs($admin)
            ->get('http://admin.jadidaring.test/clients/'.$profile->id.'/edit')
            ->assertOk()
            ->assertSee('href="http://www.jadidaring.test/gamehub"', false)
            ->assertSee('www.jadidaring.test/gamehub', false);
    }

    public function test_deploy_path_files_do_not_special_case_gamehub(): void
    {
        foreach ([
            'scripts/deploy.sh',
            'docker-compose.green.yml',
            '.deploy/nginx-upstream.conf',
            'docker-config/nginx/default.conf',
        ] as $path) {
            $contents = file_get_contents(base_path($path));

            $this->assertIsString($contents);
            $this->assertStringNotContainsString('gamehub', strtolower($contents), $path);
        }

        $deploy = (string) file_get_contents(base_path('scripts/deploy.sh'));
        $this->assertStringContainsString('singlepage-app-green', $deploy);
        $this->assertStringContainsString('nginx -s reload', $deploy);
        $this->assertStringContainsString('.deploy/nginx-upstream.conf', $deploy);

        $upstream = (string) file_get_contents(base_path('.deploy/nginx-upstream.conf'));
        $this->assertStringContainsString('set $php_upstream singlepage-app:9000;', $upstream);
        $this->assertStringContainsString('set $reverb_upstream singlepage-reverb:8080;', $upstream);

        $nginx = (string) file_get_contents(base_path('docker-config/nginx/default.conf'));
        $this->assertStringContainsString('include /var/www/.deploy/nginx-upstream.conf;', $nginx);
    }

    private function hub(string $html): CompanyProfile
    {
        return CompanyProfile::factory()->published()->create([
            'subdomain' => 'gamehub',
            'html' => $html,
        ]);
    }
}
