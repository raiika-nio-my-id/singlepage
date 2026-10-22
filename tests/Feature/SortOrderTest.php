<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SortOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_lists_profiles_in_sort_order(): void
    {
        $admin = User::factory()->admin()->create();

        $first = CompanyProfile::factory()->create(['subdomain' => 'alpha', 'sort_order' => 0]);
        $second = CompanyProfile::factory()->create(['subdomain' => 'beta', 'sort_order' => 1]);
        $third = CompanyProfile::factory()->create(['subdomain' => 'gamma', 'sort_order' => 2]);

        $response = $this->actingAs($admin)
            ->get('http://admin.jadidaring.test/clients')
            ->assertOk();

        $body = $response->getContent();
        $posAlpha = strpos($body, 'alpha');
        $posBeta = strpos($body, 'beta');
        $posGamma = strpos($body, 'gamma');

        $this->assertLessThan($posBeta, $posAlpha, 'alpha should appear before beta');
        $this->assertLessThan($posGamma, $posBeta, 'beta should appear before gamma');
    }

    public function test_admin_can_reorder_profiles(): void
    {
        $admin = User::factory()->admin()->create();

        $a = CompanyProfile::factory()->create(['subdomain' => 'aaa', 'sort_order' => 0]);
        $b = CompanyProfile::factory()->create(['subdomain' => 'bbb', 'sort_order' => 1]);
        $c = CompanyProfile::factory()->create(['subdomain' => 'ccc', 'sort_order' => 2]);

        // Reverse the order: c, b, a
        $this->actingAs($admin)
            ->postJson('http://admin.jadidaring.test/clients/reorder', [
                'ids' => [$c->id, $b->id, $a->id],
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame(0, $c->fresh()->sort_order);
        $this->assertSame(1, $b->fresh()->sort_order);
        $this->assertSame(2, $a->fresh()->sort_order);
    }

    public function test_client_cannot_reorder_profiles(): void
    {
        $client = User::factory()->client()->create();
        CompanyProfile::factory()->for($client)->create(['subdomain' => 'mine', 'sort_order' => 0]);

        $this->actingAs($client)
            ->postJson('http://admin.jadidaring.test/clients/reorder', [
                'ids' => [1],
            ])
            ->assertForbidden();
    }
}
