<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class GitHubDeployService
{
    private const API_BASE = 'https://api.github.com';

    private string $token;
    private string $repo;
    private string $workflowFile;

    public function __construct()
    {
        $this->token = (string) config('deploy.github_token', '');
        $this->repo = (string) config('deploy.github_repo', '');
        $this->workflowFile = (string) config('deploy.workflow_file', 'deploy.yml');
    }

    public function isConfigured(): bool
    {
        return $this->token !== '' && $this->repo !== '';
    }

    /**
     * Trigger the deploy workflow via workflow_dispatch.
     *
     * @throws RuntimeException on API failure
     */
    public function dispatch(string $ref, string $deployerName): void
    {
        $this->guardConfigured();

        try {
            $response = $this->client()
                ->post(
                    "{$this->apiBase()}/dispatches",
                    [
                        'ref' => $ref,
                        'inputs' => [
                            'ref' => $ref,
                            'deployer_name' => $deployerName,
                        ],
                    ]
                );
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach GitHub API: '.$e->getMessage(), 0, $e);
        }

        if ($response->status() !== 204) {
            throw new RuntimeException(
                sprintf(
                    'GitHub API returned HTTP %d: %s',
                    $response->status(),
                    $response->body(),
                )
            );
        }
    }

    /**
     * Fetch the most recent workflow runs.
     *
     * @return array<int, array<string, mixed>>
     * @throws RuntimeException on API failure
     */
    public function recentRuns(int $perPage = 5): array
    {
        $this->guardConfigured();

        try {
            $response = $this->client()
                ->get("{$this->apiBase()}/runs", ['per_page' => $perPage])
                ->throw();
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach GitHub API: '.$e->getMessage(), 0, $e);
        } catch (RequestException $e) {
            throw new RuntimeException(
                sprintf('GitHub API error: HTTP %d', $e->response->status()),
                0,
                $e
            );
        }

        return collect($response->json('workflow_runs', []))
            ->map(fn (array $run): array => [
                'id'          => $run['id'],
                'status'      => $run['status'],
                'conclusion'  => $run['conclusion'],
                'actor'       => $run['actor']['login'] ?? 'unknown',
                'created_at'  => $run['created_at'],
                'html_url'    => $run['html_url'],
                'run_number'  => $run['run_number'],
                'display_ref' => $run['head_branch'] ?? '—',
            ])
            ->values()
            ->all();
    }

    private function apiBase(): string
    {
        return self::API_BASE."/repos/{$this->repo}/actions/workflows/{$this->workflowFile}";
    }

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken($this->token)
            ->withHeaders([
                'Accept'               => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->timeout(15);
    }

    private function guardConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('GitHub deploy integration is not configured.');
        }
    }
}
