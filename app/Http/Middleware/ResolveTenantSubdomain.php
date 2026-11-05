<?php

namespace App\Http\Middleware;

use App\Models\CompanyProfile;
use App\Models\CompanyProfileAlias;
use App\Services\GameHub;
use App\Services\TenantPath;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantSubdomain
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $baseDomain = strtolower((string) config('singlepage.base_domain'));
        $adminDomain = strtolower((string) config('singlepage.admin_domain'));
        $host = strtolower(rtrim($request->getHost(), '.'));

        if ($host === $adminDomain || $host === $baseDomain || ! str_ends_with($host, '.'.$baseDomain)) {
            abort(404);
        }

        $subdomain = substr($host, 0, -strlen('.'.$baseDomain));

        if ($subdomain === '' || str_contains($subdomain, '.') || ! TenantPath::isHostLabel($subdomain)) {
            abort(404);
        }

        // The retired GameHub host always moves to the path, including when
        // the profile is unpublished, so old bookmarks do not 404 in place.
        if (GameHub::isSubdomain($subdomain)) {
            return redirect()->away(TenantPath::redirectTarget($request, $subdomain), 301);
        }

        // www, admin, api, reverb, and the other reserved labels are not games.
        if (TenantPath::isBlockedLabel($subdomain)) {
            abort(404);
        }

        $profile = CompanyProfile::query()
            ->live()
            ->where('subdomain', $subdomain)
            ->first();

        if ($profile instanceof CompanyProfile) {
            return redirect()->away(TenantPath::redirectTarget($request, $profile->subdomain), 301);
        }

        // Alias hosts 301 to the primary path. The follow-up request is what
        // counts as a page view. Draft and expired aliases stay 404.
        $alias = CompanyProfileAlias::query()
            ->with('companyProfile')
            ->where('subdomain', $subdomain)
            ->whereHas('companyProfile', fn ($query) => $query->live())
            ->first();

        if ($alias instanceof CompanyProfileAlias && $alias->companyProfile instanceof CompanyProfile) {
            return redirect()->away(TenantPath::redirectTarget($request, $alias->companyProfile->subdomain), 301);
        }

        abort(404);
    }
}
