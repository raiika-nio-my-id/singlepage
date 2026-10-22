<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PageViewTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_visiting_a_tenant_page_records_a_view(): void
    {
        CompanyProfile::factory()->published()->create(['subdomain' => 'tracked']);

        $this->get('http://tracked.jadidaring.test/')->assertOk();

        $count = DB::table('page_view_records')
            ->join('company_profiles', 'company_profiles.id', '=', 'page_view_records.company_profile_id')
            ->where('company_profiles.subdomain', 'tracked')
            ->count();

        $this->assertSame(1, $count, 'Expected one view record after a single visit.');
    }

    public function test_same_visitor_is_only_counted_once_per_day(): void
    {
        CompanyProfile::factory()->published()->create(['subdomain' => 'deduped']);

        // Three requests from the same IP + user-agent combination should
        // produce exactly one unique-visitor row for today.
        $this->get('http://deduped.jadidaring.test/')->assertOk();
        $this->get('http://deduped.jadidaring.test/')->assertOk();
        $this->get('http://deduped.jadidaring.test/')->assertOk();

        $count = DB::table('page_view_records')
            ->join('company_profiles', 'company_profiles.id', '=', 'page_view_records.company_profile_id')
            ->where('company_profiles.subdomain', 'deduped')
            ->count();

        $this->assertSame(1, $count, 'Repeated visits from the same visitor must not create duplicate rows.');
    }

    public function test_bot_user_agent_is_not_tracked(): void
    {
        CompanyProfile::factory()->published()->create(['subdomain' => 'nobots']);

        $this->withHeaders(['User-Agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)'])
            ->get('http://nobots.jadidaring.test/')
            ->assertOk();

        $count = DB::table('page_view_records')
            ->join('company_profiles', 'company_profiles.id', '=', 'page_view_records.company_profile_id')
            ->where('company_profiles.subdomain', 'nobots')
            ->count();

        $this->assertSame(0, $count, 'Bot visits must not be recorded.');
    }

    public function test_admin_dashboard_shows_view_counts(): void
    {
        $admin = User::factory()->admin()->create();
        $profile = CompanyProfile::factory()->published()->create(['subdomain' => 'statsgame']);

        // Seed a view record directly so we don't depend on defer() completing.
        DB::table('page_view_records')->insert([
            'company_profile_id' => $profile->id,
            'viewed_on' => now()->toDateString(),
            'visitor_hash' => hash('sha256', 'test-visitor-today'),
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('http://admin.jadidaring.test/clients')
            ->assertOk()
            ->assertSee('Views', false);
    }
}
