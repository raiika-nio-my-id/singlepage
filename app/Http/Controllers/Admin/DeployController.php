<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeployLog;
use App\Services\GitHubDeployService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class DeployController extends Controller
{
    public function __construct(private readonly GitHubDeployService $github)
    {
    }

    public function index(): View
    {
        $this->authorizeAdmin();

        return view('admin.deploy', [
            'configured' => $this->github->isConfigured(),
            'recentLogs' => DeployLog::with('user')
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }

    /**
     * Dispatch the workflow via GitHub API.
     * CSRF-protected (web middleware). Rate-limited at the route level.
     */
    public function dispatch(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();

        if (! $this->github->isConfigured()) {
            return back()->withErrors(['deploy' => 'GitHub deploy integration is not configured.']);
        }

        $validated = $request->validate([
            'ref' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_.\/\-]+$/'],
        ]);

        $ref = $validated['ref'] ?: 'main';

        /** @var \App\Models\User $user */
        $user = $request->user();

        try {
            $this->github->dispatch($ref, $user->name);
        } catch (RuntimeException $e) {
            report($e);
            return back()->withErrors(['deploy' => 'Dispatch failed: '.$e->getMessage()]);
        }

        DeployLog::create([
            'user_id'   => $user->id,
            'user_name' => $user->name,
            'ref'       => $ref,
        ]);

        logger()->info('Deploy dispatched', [
            'user_id'   => $user->id,
            'user_name' => $user->name,
            'ref'       => $ref,
        ]);

        return back()->with('success', "Deploy of "{$ref}" dispatched. Watch progress on the GitHub Actions page.");
    }

    /**
     * Return the recent workflow runs as JSON (for the polling widget).
     */
    public function runs(): JsonResponse
    {
        $this->authorizeAdmin();

        if (! $this->github->isConfigured()) {
            return response()->json(['configured' => false]);
        }

        try {
            $runs = $this->github->recentRuns(5);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        return response()->json(['configured' => true, 'runs' => $runs]);
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }
}
