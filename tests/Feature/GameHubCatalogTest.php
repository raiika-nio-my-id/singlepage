<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GameHubCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_hub_lists_live_games_with_path_urls_and_replaces_the_static_list(): void
    {
        $this->hub($this->showcaseHtml());
        $this->game('drawquarium', 'Drawquarium · Gambar ikan', 'Akuarium gambar bersama.', now()->subDays(2));
        $this->game('langitbersama', 'Langit Bersama', 'Langit yang sama untuk semua.', now()->subDay());
        CompanyProfile::factory()->create([
            'subdomain' => 'draftgame',
            'html' => $this->page('Draft Game', 'Jangan tampil.'),
        ]);
        CompanyProfile::factory()->published()->expired()->create([
            'subdomain' => 'oldgame',
            'html' => $this->page('Old Game', 'Sudah kedaluwarsa.'),
        ]);

        $response = $this->get('http://www.jadidaring.test/gamehub/')
            ->assertOk()
            ->assertSee('data-game="drawquarium"', false)
            ->assertSee('data-game="langitbersama"', false)
            ->assertSee('Drawquarium', false)
            ->assertSee('Akuarium gambar bersama.', false)
            ->assertSee('Langit Bersama', false)
            ->assertSee('href="http://www.jadidaring.test/drawquarium/"', false)
            ->assertSee('href="http://www.jadidaring.test/langitbersama/"', false)
            ->assertSee('www.jadidaring.test/drawquarium', false)
            ->assertSee('data:image/png;base64,abcd', false)
            ->assertSee('"tags":["multiplayer"]', false)
            ->assertSee('class="sticker new">Baru', false)
            ->assertSee('host: g.hostLabel || u.host', false)
            ->assertSee('https?:\\/\\/', false)
            ->assertSee('href="/gamehub/?sort=new" aria-current="page"', false)
            ->assertSee('href="/gamehub/?sort=views"', false)
            ->assertSee('Terbaru', false)
            ->assertSee('Paling dilihat', false)
            ->assertSeeInOrder([
                'data-game="langitbersama"',
                'data-game="drawquarium"',
            ], false);

        $response->assertDontSee('Ghost Game', false);
        $response->assertDontSee('Old Draw', false);
        $response->assertDontSee('Old desc', false);
        $response->assertDontSee('base64,qqqq', false);
        $response->assertDontSee('Gambar ikan', false);
        $response->assertDontSee('data-game="draftgame"', false);
        $response->assertDontSee('data-game="oldgame"', false);
        $response->assertDontSee('data-game="gamehub"', false);
        $response->assertDontSee('drawquarium.jadidaring.test', false);
        $response->assertDontSee('langitbersama.jadidaring.test', false);

        $this->get('http://www.jadidaring.test/drawquarium/')
            ->assertOk()
            ->assertDontSee('id="hub-sort"', false)
            ->assertDontSee('data-gamehub-catalog', false)
            ->assertDontSee('Langit Bersama', false);
    }

    public function test_published_company_sites_stay_out_of_the_catalog(): void
    {
        $this->hub('<html><body>JaRing hub</body></html>');
        $this->game('drawquarium', 'Drawquarium', 'Akuarium.', now()->subDay());
        $showcase = CompanyProfile::factory()->published()->create([
            'subdomain' => 'showcase',
            'html' => $this->page('JaRing Showcase', 'Bukan game.'),
        ]);
        CompanyProfile::factory()->published()->create([
            'subdomain' => 'demo1',
            'html' => $this->page('Demo Company', 'Situs klien.'),
            'is_game' => null,
        ]);
        CompanyProfile::factory()->game()->create([
            'subdomain' => 'draftgame',
            'html' => $this->page('Draft Game', 'Masih draf.'),
        ]);
        $this->views($showcase, 12);

        $response = $this->get('http://www.jadidaring.test/gamehub/?sort=views')
            ->assertOk()
            ->assertSee('data-game="drawquarium"', false)
            ->assertDontSee('data-game="showcase"', false)
            ->assertDontSee('data-game="demo1"', false)
            ->assertDontSee('data-game="draftgame"', false)
            ->assertDontSee('data-game="gamehub"', false)
            ->assertDontSee('JaRing Showcase', false)
            ->assertDontSee('Bukan game.', false)
            ->assertDontSee('Demo Company', false)
            ->assertDontSee('"subdomain":"showcase"', false)
            ->assertDontSee('"subdomain":"demo1"', false);

        $this->assertStringNotContainsString('Belum ada game.', $response->getContent());
    }

    public function test_most_viewed_uses_page_view_records_and_opening_a_game_counts(): void
    {
        $this->hub('<html><body>JaRing hub</body></html>');
        $newer = $this->game('langitbersama', 'Langit Bersama', 'Baru.', now()->subDay());
        $popular = $this->game('drawquarium', 'Drawquarium', 'Lama.', now()->subDays(10));
        $this->views($popular, 4);

        $this->get('http://www.jadidaring.test/gamehub/')
            ->assertOk()
            ->assertSeeInOrder([
                'data-game="langitbersama"',
                'data-game="drawquarium"',
            ], false);

        $this->assertSame(0, $this->viewCount($newer));
        $this->assertSame(4, $this->viewCount($popular));

        $this->get('http://www.jadidaring.test/langitbersama/')->assertOk();
        $this->get('http://www.jadidaring.test/gamehub/')->assertOk();

        $this->assertSame(1, $this->viewCount($newer));
        $this->assertSame(4, $this->viewCount($popular));

        $this->get('http://www.jadidaring.test/gamehub/?sort=views')
            ->assertOk()
            ->assertSee('href="/gamehub/?sort=views" aria-current="page"', false)
            ->assertDontSee('href="/gamehub/?sort=new" aria-current="page"', false)
            ->assertSeeInOrder([
                'data-game="drawquarium"',
                'data-game="langitbersama"',
            ], false);
    }

    public function test_admin_api_create_and_update_show_up_on_the_next_refresh(): void
    {
        $this->hub('<html><body>JaRing hub</body></html>');

        $created = $this->postJson('http://admin.jadidaring.test/api/admin/clients', [
            'name' => 'Drawquarium',
            'email' => 'draw@example.com',
            'subdomain' => 'drawquarium',
            'status' => 'published',
            'is_game' => true,
            'expires_at' => now()->addYear()->toDateString(),
            'html' => $this->page(
                'Drawquarium · Gambar ikan',
                'Akuarium gambar bersama.',
                'https://cdn.example.test/draw.webp',
            ),
        ], ['X-Admin-Api-Key' => 'testing-admin-api-secret'])->assertCreated();

        $this->get('http://www.jadidaring.test/gamehub/')
            ->assertOk()
            ->assertSee('data-game="drawquarium"', false)
            ->assertSee('>Drawquarium<', false)
            ->assertSee('Akuarium gambar bersama.', false)
            ->assertSee('https://cdn.example.test/draw.webp', false)
            ->assertSee('href="http://www.jadidaring.test/drawquarium/"', false)
            ->assertDontSee('Gambar ikan', false)
            ->assertDontSee('drawquarium.jadidaring.test', false);

        $this->patchJson('http://admin.jadidaring.test/api/admin/clients/'.$created->json('data.id'), [
            'html' => $this->page('Langit Baru', 'Deskripsi baru.', 'https://cdn.example.test/langit.webp'),
        ], ['X-Admin-Api-Key' => 'testing-admin-api-secret'])->assertOk();

        $this->get('http://www.jadidaring.test/gamehub/')
            ->assertOk()
            ->assertSee('Langit Baru', false)
            ->assertSee('Deskripsi baru.', false)
            ->assertSee('https://cdn.example.test/langit.webp', false)
            ->assertSee('href="http://www.jadidaring.test/drawquarium/"', false)
            ->assertDontSee('Akuarium gambar bersama.', false)
            ->assertDontSee('>Drawquarium<', false);
    }

    public function test_admin_form_publish_shows_the_game_on_the_next_refresh(): void
    {
        $this->hub('<html><body>JaRing hub</body></html>');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('http://admin.jadidaring.test/clients', [
                'name' => 'Kopi Senja',
                'email' => 'kopi@example.com',
                'subdomain' => 'kopisenja',
                'status' => 'published',
                'is_game' => '1',
                'html' => $this->page('Kopi Senja · Malam', 'Ngopi bareng.'),
                'expires_at' => now()->addYear()->toDateString(),
            ])
            ->assertRedirect();

        $this->get('http://www.jadidaring.test/gamehub/')
            ->assertOk()
            ->assertSee('data-game="kopisenja"', false)
            ->assertSee('>Kopi Senja<', false)
            ->assertSee('Ngopi bareng.', false)
            ->assertSee('href="http://www.jadidaring.test/kopisenja/"', false)
            ->assertSee('www.jadidaring.test/kopisenja', false)
            ->assertDontSee('kopisenja.jadidaring.test', false)
            ->assertDontSee('Malam', false);
    }

    private function hub(string $html): CompanyProfile
    {
        return CompanyProfile::factory()->published()->create([
            'subdomain' => 'gamehub',
            'html' => $html,
        ]);
    }

    private function game(string $subdomain, string $title, string $description, Carbon $createdAt): CompanyProfile
    {
        $profile = CompanyProfile::factory()->published()->game()->create([
            'subdomain' => $subdomain,
            'html' => $this->page($title, $description),
        ]);
        $profile->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $profile;
    }

    private function page(string $title, string $description, ?string $image = null): string
    {
        $imageTag = $image === null
            ? ''
            : '<meta property="og:image" content="'.e($image).'">';

        return '<!doctype html><html><head><title>'.e($title).'</title>'
            .'<meta name="description" content="'.e($description).'">'
            .$imageTag
            .'</head><body></body></html>';
    }

    private function views(CompanyProfile $profile, int $count): void
    {
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'company_profile_id' => $profile->id,
                'viewed_on' => now()->subDays($i)->toDateString(),
                'visitor_hash' => hash('sha256', $profile->subdomain.'-'.$i),
                'created_at' => now(),
            ];
        }

        DB::table('page_view_records')->insert($rows);
    }

    private function viewCount(CompanyProfile $profile): int
    {
        return DB::table('page_view_records')
            ->where('company_profile_id', $profile->id)
            ->count();
    }

    private function showcaseHtml(): string
    {
        $config = json_encode([
            'site' => ['name' => 'JaRing'],
            'games' => [
                [
                    'subdomain' => 'ghost',
                    'title' => 'Ghost Game',
                    'description' => 'Should vanish',
                    'url' => 'https://ghost.jadidaring.test/',
                    'snapshot' => 'data:image/png;base64,qqqq',
                ],
                [
                    'subdomain' => 'drawquarium',
                    'title' => 'Old Draw',
                    'description' => 'Old desc',
                    'url' => 'https://drawquarium.jadidaring.test/',
                    'tags' => ['multiplayer'],
                    'badge' => 'Baru',
                    'snapshot' => 'data:image/png;base64,abcd',
                ],
            ],
        ], JSON_UNESCAPED_SLASHES);

        return <<<HTML
        <html><head>
        <script type="application/json" id="website-config">{$config}</script>
        <script type="application/ld+json" id="seo-jsonld">{"@context":"https://schema.org","@graph":[{"@type":"ItemList","name":"Semua Game","numberOfItems":1,"itemListElement":[{"@type":"ListItem","position":1,"item":{"@type":"VideoGame","name":"Ghost Game","url":"https://ghost.jadidaring.test/"}}]}]}</script>
        <script>
        url: u.href, host: u.host,
        snapshot: /^data:image\\/(webp|png|jpeg);base64,/.test(g.snapshot || "") ? g.snapshot : "",
        s.textContent = g.host.split(".")[0];
        </script>
        </head><body>
        <div class="toolbar" id="toolbar"></div>
        <div class="filters" id="filters"><!-- prerender:filters -->Ghost filter<!-- /prerender:filters --></div>
        <div class="grid" id="grid"><!-- prerender:cards -->Ghost Game lives here<!-- /prerender:cards --></div>
        </body></html>
        HTML;
    }
}
