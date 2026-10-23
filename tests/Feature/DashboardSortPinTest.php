<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardSortPinTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────────────

    /** Return the position of a profile's row in the rendered HTML using its data-id attribute. */
    private function rowPos(string $html, CompanyProfile $profile): int
    {
        $pos = strpos($html, 'data-id="'.$profile->id.'"');
        $this->assertNotFalse($pos, "Profile {$profile->id} ({$profile->subdomain}) not found in page");

        return $pos;
    }

    // ── Sort: custom (sort_order) ─────────────────────────────────────────

    public function test_custom_sort_respects_sort_order(): void
    {
        $admin  = User::factory()->admin()->create();
        $first  = CompanyProfile::factory()->create(['subdomain' => 'first',  'sort_order' => 0]);
        $second = CompanyProfile::factory()->create(['subdomain' => 'second', 'sort_order' => 1]);
        $third  = CompanyProfile::factory()->create(['subdomain' => 'third',  'sort_order' => 2]);

        $body = $this->actingAs($admin)
            ->get('http://admin.jadidaring.test/clients?sort=custom')
            ->assertOk()
            ->getContent();

        $this->assertLessThan($this->rowPos($body, $second), $this->rowPos($body, $first));
        $this->assertLessThan($this->rowPos($body, $third),  $this->rowPos($body, $second));
    }

    // ── Sort: name ────────────────────────────────────────────────────────

    public function test_name_sort_orders_alphabetically(): void
    {
        $admin  = User::factory()->admin()->create();
        $zara   = CompanyProfile::factory()->for(User::factory()->state(['name' => 'Zara_User']))->create(['subdomain' => 'z-site']);
        $adam   = CompanyProfile::factory()->for(User::factory()->state(['name' => 'Adam_User']))->create(['subdomain' => 'a-site']);
        $mia    = CompanyProfile::factory()->for(User::factory()->state(['name' => 'Mia_User'])) ->create(['subdomain' => 'm-site']);

        $body = $this->actingAs($admin)
            ->get('http://admin.jadidaring.test/clients?sort=name_asc')
            ->assertOk()
            ->getContent();

        $this->assertLessThan($this->rowPos($body, $mia),  $this->rowPos($body, $adam));
        $this->assertLessThan($this->rowPos($body, $zara), $this->rowPos($body, $mia));
    }

    public function test_name_sort_direction_desc(): void
    {
        $admin = User::factory()->admin()->create();
        $zara  = CompanyProfile::factory()->for(User::factory()->state(['name' => 'Zara_User']))->create(['subdomain' => 'z-site']);
        $adam  = CompanyProfile::factory()->for(User::factory()->state(['name' => 'Adam_User']))->create(['subdomain' => 'a-site']);

        $body = $this->actingAs($admin)
            ->get('http://admin.jadidaring.test/clients?sort=name_desc')
            ->assertOk()
            ->getContent();

        // desc: Zara before Adam
        $this->assertLessThan($this->rowPos($body, $adam), $this->rowPos($body, $zara));
    }

    // ── Sort: views ───────────────────────────────────────────────────────

    public function test_views_sort_orders_by_visitor_count_desc(): void
    {
        $admin   = User::factory()->admin()->create();
        $popular = CompanyProfile::factory()->create(['subdomain' => 'xpopular']);
        $quiet   = CompanyProfile::factory()->create(['subdomain' => 'xquiet']);

        DB::table('page_view_records')->insert([
            ['company_profile_id' => $popular->id, 'viewed_on' => now()->toDateString(), 'visitor_hash' => 'h1', 'created_at' => now()],
            ['company_profile_id' => $popular->id, 'viewed_on' => now()->toDateString(), 'visitor_hash' => 'h2', 'created_at' => now()],
        ]);

        $body = $this->actingAs($admin)
            ->get('http://admin.jadidaring.test/clients?sort=views')
            ->assertOk()
            ->getContent();

        // Use data-id markers to avoid strpos false-positives from random user names.
        $this->assertLessThan($this->rowPos($body, $quiet), $this->rowPos($body, $popular),
            'Profile with more views should appear before profile with fewer views');
    }

    // ── Sort: expired ─────────────────────────────────────────────────────

    public function test_expired_sort_shows_soonest_expiring_first(): void
    {
        $admin = User::factory()->admin()->create();
        $soon  = CompanyProfile::factory()->create(['subdomain' => 'xsoon',  'expires_at' => now()->addDays(5)]);
        $later = CompanyProfile::factory()->create(['subdomain' => 'xlater', 'expires_at' => now()->addDays(100)]);

        $body = $this->actingAs($admin)
            ->get('http://admin.jadidaring.test/clients?sort=expired')
            ->assertOk()
            ->getContent();

        $this->assertLessThan($this->rowPos($body, $later), $this->rowPos($body, $soon));
    }

    public function test_expired_sort_puts_null_expiry_last(): void
    {
        $admin   = User::factory()->admin()->create();
        $noExp   = CompanyProfile::factory()->create(['subdomain' => 'xnoexp', 'expires_at' => null]);
        $hasExp  = CompanyProfile::factory()->create(['subdomain' => 'xhasexp', 'expires_at' => now()->addDays(10)]);

        $body = $this->actingAs($admin)
            ->get('http://admin.jadidaring.test/clients?sort=expired')
            ->assertOk()
            ->getContent();

        $this->assertLessThan($this->rowPos($body, $noExp), $this->rowPos($body, $hasExp));
    }

    // ── Sort: newest ──────────────────────────────────────────────────────

    public function test_newest_sort_shows_most_recently_created_first(): void
    {
        $admin = User::factory()->admin()->create();
        $old   = CompanyProfile::factory()->create(['subdomain' => 'xoldsite', 'created_at' => now()->subDays(30)]);
        $new   = CompanyProfile::factory()->create(['subdomain' => 'xnewsite', 'created_at' => now()]);

        $body = $this->actingAs($admin)
            ->get('http://admin.jadidaring.test/clients?sort=newest')
            ->assertOk()
            ->getContent();

        $this->assertLessThan($this->rowPos($body, $old), $this->rowPos($body, $new));
    }

    // ── Pin ───────────────────────────────────────────────────────────────

    public function test_pinned_profiles_appear_first_regardless_of_sort(): void
    {
        $admin   = User::factory()->admin()->create();
        $pinned  = CompanyProfile::factory()->for(User::factory()->state(['name' => 'Zzz_User']))
                       ->create(['subdomain' => 'xpinned', 'is_pinned' => true, 'sort_order' => 99]);
        $regular = CompanyProfile::factory()->for(User::factory()->state(['name' => 'Aaa_User']))
                       ->create(['subdomain' => 'xunpinned', 'is_pinned' => false, 'sort_order' => 0]);

        foreach (['custom', 'name_asc', 'newest'] as $sort) {
            $body = $this->actingAs($admin)
                ->get('http://admin.jadidaring.test/clients?sort='.$sort)
                ->assertOk()
                ->getContent();

            $this->assertLessThan(
                $this->rowPos($body, $regular),
                $this->rowPos($body, $pinned),
                "Pinned profile should appear before unpinned in '{$sort}' sort"
            );
        }
    }

    public function test_pin_button_has_pressed_state_and_tooltip(): void
    {
        $admin   = User::factory()->admin()->create();
        $pinned  = CompanyProfile::factory()->create(['is_pinned' => true]);
        $regular = CompanyProfile::factory()->create(['is_pinned' => false]);

        $body = $this->actingAs($admin)
            ->get('http://admin.jadidaring.test/clients')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('aria-pressed="true"', $body);
        $this->assertStringContainsString('aria-pressed="false"', $body);
        $this->assertStringContainsString('title="Unpin"', $body);
        $this->assertStringContainsString('title="Pin"', $body);
        $this->assertStringContainsString('row-pinned', $body);
        $this->assertStringContainsString('data-id="'.$pinned->id.'"', $body);
        $this->assertStringContainsString('data-id="'.$regular->id.'"', $body);
    }

    public function test_admin_can_pin_a_profile(): void
    {
        $admin   = User::factory()->admin()->create();
        $profile = CompanyProfile::factory()->create(['is_pinned' => false]);

        $this->actingAs($admin)
            ->from('http://admin.jadidaring.test/clients')
            ->post('http://admin.jadidaring.test/clients/'.$profile->id.'/pin')
            ->assertRedirect();

        $this->assertTrue($profile->fresh()->is_pinned);
    }

    public function test_pin_toggle_returns_json_when_requested(): void
    {
        $admin   = User::factory()->admin()->create();
        $profile = CompanyProfile::factory()->create(['is_pinned' => false]);

        $this->actingAs($admin)
            ->postJson('http://admin.jadidaring.test/clients/'.$profile->id.'/pin')
            ->assertOk()
            ->assertJson(['is_pinned' => true]);

        $this->assertTrue($profile->fresh()->is_pinned);
    }

    public function test_admin_can_unpin_a_profile(): void
    {
        $admin   = User::factory()->admin()->create();
        $profile = CompanyProfile::factory()->create(['is_pinned' => true]);

        $this->actingAs($admin)
            ->from('http://admin.jadidaring.test/clients')
            ->post('http://admin.jadidaring.test/clients/'.$profile->id.'/pin')
            ->assertRedirect();

        $this->assertFalse($profile->fresh()->is_pinned);
    }

    public function test_client_cannot_pin_profiles(): void
    {
        $client  = User::factory()->client()->create();
        $profile = CompanyProfile::factory()->for($client)->create(['is_pinned' => false]);

        $this->actingAs($client)
            ->post('http://admin.jadidaring.test/clients/'.$profile->id.'/pin')
            ->assertForbidden();

        $this->assertFalse($profile->fresh()->is_pinned);
    }

    // ── Pagination ────────────────────────────────────────────────────────

    public function test_dashboard_paginates_at_25_per_page(): void
    {
        $admin = User::factory()->admin()->create();
        CompanyProfile::factory()->count(30)->create();

        $body = $this->actingAs($admin)
            ->get('http://admin.jadidaring.test/clients')
            ->assertOk()
            ->getContent();

        // With 30 profiles and 25/page, pagination should appear.
        $this->assertStringContainsString('Next', $body);
        $this->assertStringContainsString('Page 1', $body);
    }

    public function test_pagination_preserves_sort_in_links(): void
    {
        $admin = User::factory()->admin()->create();
        CompanyProfile::factory()->count(30)->create();

        $body = $this->actingAs($admin)
            ->get('http://admin.jadidaring.test/clients?sort=newest')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('sort=newest', $body);
    }

    // ── Reorder with offset ───────────────────────────────────────────────

    public function test_reorder_applies_offset_to_sort_order(): void
    {
        $admin    = User::factory()->admin()->create();
        $profiles = CompanyProfile::factory()->count(3)->create();

        $this->actingAs($admin)
            ->postJson('http://admin.jadidaring.test/clients/reorder', [
                'ids'    => [$profiles[2]->id, $profiles[0]->id, $profiles[1]->id],
                'offset' => 25,
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame(25, $profiles[2]->fresh()->sort_order);
        $this->assertSame(26, $profiles[0]->fresh()->sort_order);
        $this->assertSame(27, $profiles[1]->fresh()->sort_order);
    }

    public function test_sort_choice_is_remembered_in_session(): void
    {
        $admin = User::factory()->admin()->create();
        CompanyProfile::factory()->create();

        // Set sort via query string…
        $this->actingAs($admin)->get('http://admin.jadidaring.test/clients?sort=newest')->assertOk();

        // …then visit without query string — 'newest' option should be selected in the dropdown.
        $body = $this->actingAs($admin)
            ->get('http://admin.jadidaring.test/clients')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/value="newest"\s+selected/', $body);
    }
}
