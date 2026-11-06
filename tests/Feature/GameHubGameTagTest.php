<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\User;
use App\Services\GameHubGameBackfill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameHubGameTagTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_tags_known_game_slugs_and_leaves_sites_untagged(): void
    {
        $this->assertContains('cukurkekinian', GameHubGameBackfill::SUBDOMAINS);
        $this->assertContains('mudikmacet', GameHubGameBackfill::SUBDOMAINS);
        $this->assertContains('pawanghujan', GameHubGameBackfill::SUBDOMAINS);
        $this->assertContains('poconglari', GameHubGameBackfill::SUBDOMAINS);
        $this->assertContains('hantukampung', GameHubGameBackfill::SUBDOMAINS);
        $this->assertContains('rumahkontrakan', GameHubGameBackfill::SUBDOMAINS);
        $this->assertNotContains('cukur-kekinian', GameHubGameBackfill::SUBDOMAINS);
        $this->assertNotContains('mudik-macet', GameHubGameBackfill::SUBDOMAINS);
        $this->assertNotContains('pawang-hujan', GameHubGameBackfill::SUBDOMAINS);
        $this->assertNotContains('showcase', GameHubGameBackfill::SUBDOMAINS);
        $this->assertNotContains('gamehub', GameHubGameBackfill::SUBDOMAINS);
        $this->assertNotContains('demo', GameHubGameBackfill::SUBDOMAINS);
        $this->assertNotContains('demo1', GameHubGameBackfill::SUBDOMAINS);
        $this->assertSame(
            GameHubGameBackfill::SUBDOMAINS,
            array_values(array_unique(GameHubGameBackfill::SUBDOMAINS)),
        );

        foreach (GameHubGameBackfill::SUBDOMAINS as $subdomain) {
            CompanyProfile::factory()->create([
                'subdomain' => $subdomain,
                'is_game' => false,
            ]);
        }

        foreach (['gamehub', 'showcase', 'demo', 'demo1', 'demo12', 'cukur-kekinian', 'mudik-macet', 'pawang-hujan'] as $subdomain) {
            CompanyProfile::factory()->create([
                'subdomain' => $subdomain,
                'is_game' => null,
            ]);
        }

        GameHubGameBackfill::apply();

        foreach (GameHubGameBackfill::SUBDOMAINS as $subdomain) {
            $this->assertTrue(
                CompanyProfile::query()->where('subdomain', $subdomain)->firstOrFail()->is_game,
                $subdomain,
            );
        }

        foreach (['gamehub', 'showcase', 'demo', 'demo1', 'demo12', 'cukur-kekinian', 'mudik-macet', 'pawang-hujan'] as $subdomain) {
            $this->assertFalse(
                CompanyProfile::query()->where('subdomain', $subdomain)->firstOrFail()->is_game,
                $subdomain,
            );
        }
    }

    public function test_empty_catalog_when_only_company_sites_are_published(): void
    {
        $this->hub('<html><body>JaRing hub</body></html>');
        CompanyProfile::factory()->published()->create([
            'subdomain' => 'showcase',
            'html' => $this->page('JaRing Showcase', 'Bukan game.'),
        ]);

        $this->get('http://www.jadidaring.test/gamehub/')
            ->assertOk()
            ->assertSee('Belum ada game.', false)
            ->assertDontSee('data-game="showcase"', false)
            ->assertDontSee('JaRing Showcase', false);
    }

    public function test_admin_toggle_adds_and_removes_a_profile_from_gamehub(): void
    {
        $admin = User::factory()->admin()->create();
        $this->hub('<html><body>JaRing hub</body></html>');
        $profile = CompanyProfile::factory()->published()->create([
            'subdomain' => 'kopisenja',
            'html' => $this->page('Kopi Senja', 'Ngopi bareng.'),
            'is_game' => false,
        ]);

        $this->actingAs($admin)
            ->get('http://admin.jadidaring.test/clients/'.$profile->id.'/edit')
            ->assertOk()
            ->assertSee('name="is_game"', false)
            ->assertSee('Lists this site on GameHub.', false);

        $this->actingAs($admin)
            ->get('http://admin.jadidaring.test/clients')
            ->assertOk()
            ->assertSee('id="game-filter"', false)
            ->assertSee('Games only', false)
            ->assertSee('kopisenja', false);

        $this->actingAs($admin)
            ->get('http://admin.jadidaring.test/clients?game=1')
            ->assertOk()
            ->assertDontSee('kopisenja', false);

        $this->save($admin, $profile, true);

        $this->assertTrue($profile->fresh()->is_game);
        $this->get('http://www.jadidaring.test/gamehub/')
            ->assertOk()
            ->assertSee('data-game="kopisenja"', false)
            ->assertSee('Ngopi bareng.', false);

        $this->actingAs($admin)
            ->get('http://admin.jadidaring.test/clients?game=1')
            ->assertOk()
            ->assertSee('kopisenja', false)
            ->assertSee('badge badge-published">game', false);

        $this->save($admin, $profile, false);

        $this->assertFalse($profile->fresh()->is_game);
        $this->get('http://www.jadidaring.test/gamehub/')
            ->assertOk()
            ->assertDontSee('data-game="kopisenja"', false)
            ->assertDontSee('Ngopi bareng.', false);
    }

    public function test_new_client_form_defaults_to_not_a_game(): void
    {
        $admin = User::factory()->admin()->create();
        $this->hub('<html><body>JaRing hub</body></html>');

        $this->actingAs($admin)
            ->get('http://admin.jadidaring.test/clients/create')
            ->assertOk()
            ->assertSee('name="is_game"', false);

        $this->actingAs($admin)
            ->post('http://admin.jadidaring.test/clients', [
                'name' => 'Demo Co',
                'email' => 'demo@example.com',
                'subdomain' => 'democo',
                'status' => 'published',
                'html' => $this->page('Demo Co', 'Situs perusahaan.'),
                'expires_at' => now()->addYear()->toDateString(),
            ])
            ->assertRedirect();

        $profile = CompanyProfile::query()->where('subdomain', 'democo')->firstOrFail();
        $this->assertFalse($profile->is_game);

        $this->get('http://www.jadidaring.test/gamehub/')
            ->assertOk()
            ->assertDontSee('data-game="democo"', false)
            ->assertDontSee('Situs perusahaan.', false);
    }

    public function test_admin_api_accepts_is_game_and_omitting_it_stays_off_the_hub(): void
    {
        $this->hub('<html><body>JaRing hub</body></html>');

        $created = $this->postJson('http://admin.jadidaring.test/api/admin/clients', [
            'name' => 'Acme',
            'email' => 'acme@example.com',
            'subdomain' => 'acme',
            'status' => 'published',
            'html' => $this->page('Acme Corp', 'Profil perusahaan.'),
            'expires_at' => now()->addYear()->toDateString(),
        ], ['X-Admin-Api-Key' => 'testing-admin-api-secret'])->assertCreated()
            ->assertJsonPath('data.is_game', false);

        $this->get('http://www.jadidaring.test/gamehub/')
            ->assertOk()
            ->assertDontSee('data-game="acme"', false);

        $this->patchJson('http://admin.jadidaring.test/api/admin/clients/'.$created->json('data.id'), [
            'is_game' => true,
        ], ['X-Admin-Api-Key' => 'testing-admin-api-secret'])
            ->assertOk()
            ->assertJsonPath('data.is_game', true);

        $this->get('http://www.jadidaring.test/gamehub/')
            ->assertOk()
            ->assertSee('data-game="acme"', false)
            ->assertSee('Profil perusahaan.', false);

        $this->patchJson('http://admin.jadidaring.test/api/admin/clients/subdomain/acme', [
            'html' => $this->page('Acme Corp', 'Profil baru.'),
        ], ['X-Admin-Api-Key' => 'testing-admin-api-secret'])
            ->assertOk()
            ->assertJsonPath('data.is_game', true);

        $this->patchJson('http://admin.jadidaring.test/api/admin/clients/subdomain/acme', [
            'is_game' => false,
        ], ['X-Admin-Api-Key' => 'testing-admin-api-secret'])
            ->assertOk()
            ->assertJsonPath('data.is_game', false);

        $this->get('http://www.jadidaring.test/gamehub/')
            ->assertOk()
            ->assertDontSee('data-game="acme"', false);
    }

    private function hub(string $html): CompanyProfile
    {
        return CompanyProfile::factory()->published()->create([
            'subdomain' => 'gamehub',
            'html' => $html,
        ]);
    }

    private function page(string $title, string $description): string
    {
        return '<!doctype html><html><head><title>'.e($title).'</title>'
            .'<meta name="description" content="'.e($description).'">'
            .'</head><body></body></html>';
    }

    private function save(User $admin, CompanyProfile $profile, bool $isGame): void
    {
        $payload = [
            'subdomain' => $profile->subdomain,
            'name' => $profile->user->name,
            'email' => $profile->user->email,
            'html' => $profile->html,
            'status' => 'published',
            'expires_at' => $profile->expires_at?->toDateString() ?? now()->addYear()->toDateString(),
        ];

        if ($isGame) {
            $payload['is_game'] = '1';
        }

        $this->actingAs($admin)
            ->from('http://admin.jadidaring.test/clients/'.$profile->id.'/edit')
            ->put('http://admin.jadidaring.test/clients/'.$profile->id, $payload)
            ->assertRedirect();
    }
}
