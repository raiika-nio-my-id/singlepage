<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProfileStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCompanyProfileRequest;
use App\Http\Requests\Admin\UpdateCompanyProfileRequest;
use App\Models\CompanyProfile;
use App\Models\User;
use App\Services\SubdomainService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CompanyProfileController extends Controller
{
    public function home(Request $request): View
    {
        if ($request->user()?->isClient()) {
            return $this->workspace($request);
        }

        return $this->index($request);
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $this->authorize('viewAny', CompanyProfile::class);

        $profiles = CompanyProfile::query()
            ->with('user')
            ->latest()
            ->simplePaginate(20)
            ->withQueryString();

        return view('admin.dashboard', compact('profiles'));
    }

    public function workspace(Request $request): View
    {
        abort_unless($request->user()?->isClient(), 403);

        $companyProfile = $request->user()->companyProfile;

        if (! $companyProfile) {
            return view('admin.missing-profile');
        }

        $this->authorize('update', $companyProfile);
        $companyProfile->loadMissing('user');

        return view('admin.company-edit', [
            'companyProfile' => $companyProfile,
            'workspace' => true,
        ]);
    }

    public function updateOwn(
        UpdateCompanyProfileRequest $request,
        SubdomainService $subdomains,
    ): RedirectResponse {
        abort_unless($request->user()?->isClient(), 403);

        $companyProfile = $request->user()->companyProfile;
        abort_unless($companyProfile, 404);

        return $this->update($request, $companyProfile, $subdomains);
    }

    public function create(): View
    {
        $this->authorize('create', CompanyProfile::class);

        return view('admin.company-create', [
            'defaultExpiresAt' => now()->addYears((int) config('singlepage.subscription_years', 1))->toDateString(),
        ]);
    }

    public function store(StoreCompanyProfileRequest $request, SubdomainService $subdomains): RedirectResponse
    {
        $subdomain = $subdomains->validateAvailable($request->validated('subdomain'));

        $profile = DB::transaction(function () use ($request, $subdomain): CompanyProfile {
            $user = User::query()->create([
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'password' => $request->validated('password'),
                'role' => UserRole::Client,
            ]);

            return $user->companyProfile()->create([
                'subdomain' => $subdomain,
                'html' => $request->validated('html'),
                'status' => $request->published() ? ProfileStatus::Published : ProfileStatus::Draft,
                'expires_at' => $request->validated('expires_at'),
                'hide_footer' => $request->boolean('hide_footer'),
            ]);
        });

        return redirect()
            ->route('admin.clients.edit', $profile)
            ->with('success', 'Client and company profile created.');
    }

    public function edit(Request $request, CompanyProfile $companyProfile): View|RedirectResponse
    {
        $this->authorize('update', $companyProfile);

        if ($request->user()?->isClient()) {
            return redirect()->route('home');
        }

        $companyProfile->loadMissing('user');

        return view('admin.company-edit', [
            'companyProfile' => $companyProfile,
            'workspace' => false,
        ]);
    }

    public function update(
        UpdateCompanyProfileRequest $request,
        CompanyProfile $companyProfile,
        SubdomainService $subdomains,
    ): RedirectResponse {
        $companyProfile->loadMissing('user');

        DB::transaction(function () use ($request, $companyProfile, $subdomains): void {
            $payload = [
                'html' => $request->validated('html'),
                'status' => $request->published() ? ProfileStatus::Published : ProfileStatus::Draft,
            ];

            if ($request->user()?->can('updateSubdomain', $companyProfile)) {
                $payload['subdomain'] = $subdomains->validateAvailable(
                    $request->validated('subdomain'),
                    $companyProfile->id,
                );
            }

            if ($request->user()?->can('updateExpiration', $companyProfile)) {
                $payload['expires_at'] = $request->validated('expires_at');
            }

            if ($request->user()?->can('updateFooterVisibility', $companyProfile)) {
                $payload['hide_footer'] = $request->boolean('hide_footer');
            }

            $companyProfile->update($payload);

            if ($request->user()?->can('updateCredentials', $companyProfile) && $companyProfile->user) {
                $credentials = [
                    'name' => $request->validated('name'),
                    'email' => $request->validated('email'),
                ];

                if ($request->filled('password')) {
                    $credentials['password'] = $request->validated('password');
                }

                $companyProfile->user->update($credentials);
            }
        });

        return back()->with('success', 'Company profile saved.');
    }
}
