<?php

namespace Tests\Feature;

use App\Models\DeployLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeployControllerTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_HOST = 'http://admin.jadidaring.test';

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function client(): User
    {
        return User::factory()->client()->create();
    }

    private function configureGitHub(): void
    {
        config([
            'deploy.github_token' => 'ghp_test_token',
            'deploy.github_repo'  => 'owner/repo',
            'deploy.workflow_file' => 'deploy.yml',
        ]);
    }

    // ── Authorization ─────────────────────────────────────────────────────────

    public function test_guest_is_redirected_from_deploy_page(): void
    {
        $this->get(self::ADMIN_HOST.'/deploy')
            ->assertRedirect(self::ADMIN_HOST.'/login');
    }

    public function test_client_cannot_view_deploy_page(): void
    {
        $this->actingAs($this->client())
            ->get(self::ADMIN_HOST.'/deploy')
            ->assertForbidden();
    }

    public function test_client_cannot_dispatch_deploy(): void
    {
        $this->configureGitHub();

        $this->actingAs($this->client())
            ->post(self::ADMIN_HOST.'/deploy', ['ref' => 'main'])
            ->assertForbidden();
    }

    public function test_client_cannot_fetch_runs(): void
    {
        $this->configureGitHub();

        $this->actingAs($this->client())
            ->get(self::ADMIN_HOST.'/deploy/runs')
            ->assertForbidden();
    }

    public function test_admin_can_view_deploy_page_when_configured(): void
    {
        $this->configureGitHub();

        $this->actingAs($this->admin())
            ->get(self::ADMIN_HOST.'/deploy')
            ->assertOk()
            ->assertSee('Deploy')
            ->assertSee('Branch or tag')
            ->assertDontSee('not configured');
    }

    // ── Unconfigured state ────────────────────────────────────────────────────

    public function test_admin_sees_disabled_state_when_token_is_missing(): void
    {
        config([
            'deploy.github_token' => null,
            'deploy.github_repo'  => 'owner/repo',
        ]);

        $this->actingAs($this->admin())
            ->get(self::ADMIN_HOST.'/deploy')
            ->assertOk()
            ->assertSee('not configured');
    }

    public function test_dispatch_returns_error_when_unconfigured(): void
    {
        config([
            'deploy.github_token' => null,
            'deploy.github_repo'  => 'owner/repo',
        ]);

        Http::fake();

        $this->actingAs($this->admin())
            ->post(self::ADMIN_HOST.'/deploy', ['ref' => 'main'])
            ->assertRedirect()
            ->assertSessionHasErrors('deploy');

        Http::assertNothingSent();
    }

    public function test_runs_endpoint_returns_configured_false_when_unconfigured(): void
    {
        config([
            'deploy.github_token' => null,
            'deploy.github_repo'  => 'owner/repo',
        ]);

        Http::fake();

        $response = $this->actingAs($this->admin())
            ->get(self::ADMIN_HOST.'/deploy/runs')
            ->assertOk()
            ->assertJsonFragment(['configured' => false]);

        Http::assertNothingSent();
    }

    // ── Successful dispatch ───────────────────────────────────────────────────

    public function test_admin_can_dispatch_deploy_and_log_is_recorded(): void
    {
        $this->configureGitHub();

        Http::fake([
            'api.github.com/repos/owner/repo/actions/workflows/deploy.yml/dispatches' => Http::response('', 204),
        ]);

        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(self::ADMIN_HOST.'/deploy', ['ref' => 'main'])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        Http::assertSentCount(1);
        Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($admin): bool {
            return str_contains($request->url(), 'dispatches')
                && $request['ref'] === 'main'
                && $request['inputs']['deployer_name'] === $admin->name;
        });

        $this->assertDatabaseHas('deploy_logs', [
            'user_id'   => $admin->id,
            'user_name' => $admin->name,
            'ref'       => 'main',
        ]);
    }

    public function test_dispatch_uses_main_when_ref_is_empty(): void
    {
        $this->configureGitHub();

        Http::fake([
            'api.github.com/repos/owner/repo/actions/workflows/deploy.yml/dispatches' => Http::response('', 204),
        ]);

        $this->actingAs($this->admin())
            ->post(self::ADMIN_HOST.'/deploy', ['ref' => ''])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            return $request['ref'] === 'main';
        });
    }

    public function test_dispatch_rejects_invalid_ref(): void
    {
        $this->configureGitHub();
        Http::fake();

        $this->actingAs($this->admin())
            ->post(self::ADMIN_HOST.'/deploy', ['ref' => '; rm -rf /'])
            ->assertRedirect()
            ->assertSessionHasErrors('ref');

        Http::assertNothingSent();
    }

    // ── GitHub API failure ────────────────────────────────────────────────────

    public function test_dispatch_shows_error_when_github_returns_non_204(): void
    {
        $this->configureGitHub();

        Http::fake([
            'api.github.com/repos/owner/repo/actions/workflows/deploy.yml/dispatches' => Http::response('Unauthorized', 401),
        ]);

        $this->actingAs($this->admin())
            ->post(self::ADMIN_HOST.'/deploy', ['ref' => 'main'])
            ->assertRedirect()
            ->assertSessionHasErrors('deploy');

        $this->assertDatabaseEmpty('deploy_logs');
    }

    // ── Runs endpoint ─────────────────────────────────────────────────────────

    public function test_runs_endpoint_returns_github_data(): void
    {
        $this->configureGitHub();

        $fakeRuns = [
            [
                'id'          => 1,
                'status'      => 'completed',
                'conclusion'  => 'success',
                'actor'       => ['login' => 'octocat'],
                'created_at'  => '2026-10-22T10:00:00Z',
                'html_url'    => 'https://github.com/owner/repo/actions/runs/1',
                'run_number'  => 42,
                'head_branch' => 'main',
            ],
        ];

        Http::fake([
            'api.github.com/repos/owner/repo/actions/workflows/deploy.yml/runs*' => Http::response([
                'workflow_runs' => $fakeRuns,
            ]),
        ]);

        $this->actingAs($this->admin())
            ->get(self::ADMIN_HOST.'/deploy/runs')
            ->assertOk()
            ->assertJsonFragment(['configured' => true])
            ->assertJsonPath('runs.0.status', 'completed')
            ->assertJsonPath('runs.0.actor', 'octocat');
    }

    public function test_runs_endpoint_returns_502_on_github_error(): void
    {
        $this->configureGitHub();

        Http::fake([
            'api.github.com/repos/owner/repo/actions/workflows/deploy.yml/runs*' => Http::response('Server Error', 500),
        ]);

        $this->actingAs($this->admin())
            ->get(self::ADMIN_HOST.'/deploy/runs')
            ->assertStatus(502);
    }
}
