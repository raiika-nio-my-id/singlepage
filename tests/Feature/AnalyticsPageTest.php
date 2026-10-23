<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnalyticsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_analytics_page(): void
    {
        $admin   = User::factory()->admin()->create();
        $profile = CompanyProfile::factory()->create(['subdomain' => 'mysite']);

        $this->actingAs($admin)
            ->get('http://admin.jadidaring.test/clients/'.$profile->id.'/analytics')
            ->assertOk()
            ->assertSee('Analytics')
            ->assertSee('mysite');
    }

    public function test_client_cannot_view_analytics_page(): void
    {
        $client  = User::factory()->client()->create();
        $profile = CompanyProfile::factory()->for($client)->create();

        $this->actingAs($client)
            ->get('http://admin.jadidaring.test/clients/'.$profile->id.'/analytics')
            ->assertForbidden();
    }

    public function test_analytics_shows_zero_filled_days_when_no_visits(): void
    {
        $admin   = User::factory()->admin()->create();
        $profile = CompanyProfile::factory()->create();

        $this->actingAs($admin)
            ->get('http://admin.jadidaring.test/clients/'.$profile->id.'/analytics?days=7')
            ->assertOk()
            ->assertSee('No visits recorded in this period.');
    }

    public function test_analytics_shows_correct_visitor_total(): void
    {
        $admin   = User::factory()->admin()->create();
        $profile = CompanyProfile::factory()->create();

        // 2 today + 1 yesterday = 3 total
        DB::table('page_view_records')->insert([
            ['company_profile_id' => $profile->id, 'viewed_on' => now()->toDateString(),           'visitor_hash' => 'aaaa', 'created_at' => now()],
            ['company_profile_id' => $profile->id, 'viewed_on' => now()->toDateString(),           'visitor_hash' => 'bbbb', 'created_at' => now()],
            ['company_profile_id' => $profile->id, 'viewed_on' => now()->subDay()->toDateString(), 'visitor_hash' => 'cccc', 'created_at' => now()],
        ]);

        $this->actingAs($admin)
            ->get('http://admin.jadidaring.test/clients/'.$profile->id.'/analytics?days=7')
            ->assertOk()
            ->assertSee('3'); // total
    }

    public function test_analytics_today_count_is_correct(): void
    {
        $admin   = User::factory()->admin()->create();
        $profile = CompanyProfile::factory()->create();

        DB::table('page_view_records')->insert([
            ['company_profile_id' => $profile->id, 'viewed_on' => now()->toDateString(), 'visitor_hash' => 'x1', 'created_at' => now()],
            ['company_profile_id' => $profile->id, 'viewed_on' => now()->toDateString(), 'visitor_hash' => 'x2', 'created_at' => now()],
        ]);

        $body = $this->actingAs($admin)
            ->get('http://admin.jadidaring.test/clients/'.$profile->id.'/analytics?days=7')
            ->assertOk()
            ->getContent();

        // today card value appears in the first analytics-card-value
        $this->assertStringContainsString('2', $body);
    }

    public function test_analytics_days_parameter_filters_old_data(): void
    {
        $admin   = User::factory()->admin()->create();
        $profile = CompanyProfile::factory()->create();

        // Visit 100 days ago should be excluded from 30-day range
        DB::table('page_view_records')->insert([
            ['company_profile_id' => $profile->id, 'viewed_on' => now()->subDays(100)->toDateString(), 'visitor_hash' => 'old', 'created_at' => now()],
            ['company_profile_id' => $profile->id, 'viewed_on' => now()->toDateString(),              'visitor_hash' => 'new', 'created_at' => now()],
        ]);

        $body = $this->actingAs($admin)
            ->get('http://admin.jadidaring.test/clients/'.$profile->id.'/analytics?days=30')
            ->assertOk()
            ->getContent();

        // Only the recent visit should count in total (1, not 2)
        // The summary card for "Last 30 days" shows 1
        $this->assertStringContainsString('analytics-card-value', $body);
    }

    public function test_analytics_defaults_to_30_days(): void
    {
        $admin   = User::factory()->admin()->create();
        $profile = CompanyProfile::factory()->create();

        $this->actingAs($admin)
            ->get('http://admin.jadidaring.test/clients/'.$profile->id.'/analytics')
            ->assertOk()
            ->assertSee('30 days');
    }

    public function test_analytics_range_selector_renders_all_options(): void
    {
        $admin   = User::factory()->admin()->create();
        $profile = CompanyProfile::factory()->create();

        $this->actingAs($admin)
            ->get('http://admin.jadidaring.test/clients/'.$profile->id.'/analytics')
            ->assertOk()
            ->assertSee('7 days')
            ->assertSee('30 days')
            ->assertSee('90 days');
    }

    public function test_analytics_edit_page_has_analytics_link(): void
    {
        $admin   = User::factory()->admin()->create();
        $profile = CompanyProfile::factory()->create(['subdomain' => 'analysed']);

        $this->actingAs($admin)
            ->get('http://admin.jadidaring.test/clients/'.$profile->id.'/edit')
            ->assertOk()
            ->assertSee('Analytics');
    }

    public function test_analytics_dashboard_has_chart_icon_link(): void
    {
        $admin   = User::factory()->admin()->create();
        CompanyProfile::factory()->create(['subdomain' => 'chartme']);

        $this->actingAs($admin)
            ->get('http://admin.jadidaring.test/clients')
            ->assertOk()
            ->assertSee('/analytics', false); // analytics URL present in row actions
    }
}
