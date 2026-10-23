<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProfileStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCompanyProfileRequest;
use App\Http\Requests\Admin\UpdateCompanyProfileRequest;
use App\Models\CompanyProfile;
use App\Models\CompanyProfileAlias;
use App\Models\User;
use App\Rules\Subdomain;
use App\Services\SubdomainService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

        // Resolve sort: query-string overrides session; session persists last choice.
        // name_asc / name_desc combine direction into a single value for clean select option.
        $validSorts = ['custom', 'name_asc', 'name_desc', 'views', 'expired', 'newest'];
        if ($request->has('sort')) {
            $sort = in_array($request->input('sort'), $validSorts, true)
                ? $request->input('sort') : 'custom';
            $request->session()->put('admin_sort', $sort);
        } else {
            $sort = $request->session()->get('admin_sort', 'custom');
        }

        $monthAgo = now()->subDays(29)->toDateString();

        $query = CompanyProfile::query()
            ->select('company_profiles.*')
            ->with(['user', 'aliases'])
            ->orderByDesc('is_pinned'); // pinned always first within any sort

        match ($sort) {
            'name_asc', 'name_desc' => $query->orderByRaw(
                '(SELECT name FROM users WHERE id = company_profiles.user_id) '
                .($sort === 'name_asc' ? 'ASC' : 'DESC')
            ),
            // LEFT JOIN to an aggregate subquery: avoids correlated scalar in ORDER BY
            // (which can be unreliable with paginate()'s count-query clone on SQLite).
            // 1:1 or 1:0 join keeps row-count identical, so paginate COUNT is correct.
            'views' => $query->leftJoinSub(
                DB::table('page_view_records')
                    ->selectRaw('company_profile_id, COUNT(*) AS views_count')
                    ->where('viewed_on', '>=', $monthAgo)
                    ->groupBy('company_profile_id'),
                'pvr_agg',
                'pvr_agg.company_profile_id', '=', 'company_profiles.id'
            )->orderByDesc(DB::raw('COALESCE(pvr_agg.views_count, 0)')),
            'expired' => $query
                ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('expires_at'),
            'newest' => $query->orderByDesc('company_profiles.created_at'),
            default  => $query->orderBy('sort_order')->orderBy('company_profiles.id'),
        };

        $profiles  = $query->paginate(25)->withQueryString();
        $viewStats = $this->viewStats($profiles->pluck('id')->all());

        return view('admin.dashboard', compact('profiles', 'viewStats', 'sort'));
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
                'password' => $request->filled('password')
                    ? $request->validated('password')
                    : Str::password(24),
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

        $companyProfile->loadMissing(['user', 'aliases']);

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

    public function reorder(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $this->authorize('viewAny', CompanyProfile::class);

        $validated = $request->validate([
            'ids'    => ['required', 'array'],
            'ids.*'  => ['required', 'integer'],
            'offset' => ['sometimes', 'integer', 'min:0'],
        ]);

        // Offset allows page-aware reordering: items on page 2 (offset=25)
        // get sort_order values 25, 26, 27, … instead of 0, 1, 2, …
        $offset = (int) ($validated['offset'] ?? 0);

        foreach ($validated['ids'] as $position => $id) {
            CompanyProfile::where('id', (int) $id)
                ->update(['sort_order' => $offset + $position]);
        }

        return response()->json(['ok' => true]);
    }

    public function togglePin(Request $request, CompanyProfile $companyProfile): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $this->authorize('update', $companyProfile);

        $companyProfile->update(['is_pinned' => ! $companyProfile->is_pinned]);

        if ($request->expectsJson()) {
            return response()->json(['is_pinned' => (bool) $companyProfile->is_pinned]);
        }

        return back();
    }

    public function storeAlias(
        Request $request,
        CompanyProfile $companyProfile,
        SubdomainService $subdomains,
    ): RedirectResponse {
        abort_unless($request->user()?->isAdmin(), 403);
        $this->authorize('update', $companyProfile);

        $raw = $request->validate(['alias' => ['required', 'string', 'max:63', new Subdomain]])['alias'];
        $subdomain = $subdomains->validateAvailableAlias($raw, $companyProfile);

        $companyProfile->aliases()->create(['subdomain' => $subdomain]);

        return back()->with('success', "Alias \"{$subdomain}\" added.");
    }

    public function destroyAlias(
        Request $request,
        CompanyProfile $companyProfile,
        CompanyProfileAlias $companyProfileAlias,
    ): RedirectResponse {
        abort_unless($request->user()?->isAdmin(), 403);
        $this->authorize('update', $companyProfile);
        abort_unless($companyProfileAlias->company_profile_id === $companyProfile->id, 403);

        $subdomain = $companyProfileAlias->subdomain;
        $companyProfileAlias->delete();

        return back()->with('success', "Alias \"{$subdomain}\" removed.");
    }

    /**
     * @param  int[]  $profileIds
     * @return array<int, object>  keyed by company_profile_id
     */
    private function viewStats(array $profileIds): array
    {
        if (empty($profileIds)) {
            return [];
        }

        $today = now()->toDateString();
        $weekAgo = now()->subDays(6)->toDateString();
        $monthAgo = now()->subDays(29)->toDateString();

        $rows = DB::table('page_view_records')
            ->selectRaw(
                'company_profile_id,'
                .' SUM(CASE WHEN viewed_on = ? THEN 1 ELSE 0 END) AS today,'
                .' SUM(CASE WHEN viewed_on >= ? THEN 1 ELSE 0 END) AS last7,'
                .' COUNT(*) AS last30',
                [$today, $weekAgo],
            )
            ->whereIn('company_profile_id', $profileIds)
            ->where('viewed_on', '>=', $monthAgo)
            ->groupBy('company_profile_id')
            ->get()
            ->keyBy('company_profile_id');

        return $rows->all();
    }
}
