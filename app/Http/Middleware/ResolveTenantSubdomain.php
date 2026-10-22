<?php

namespace App\Http\Middleware;

use App\Models\CompanyProfile;
use App\Models\CompanyProfileAlias;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantSubdomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $baseDomain = strtolower((string) config('singlepage.base_domain'));
        $adminDomain = strtolower((string) config('singlepage.admin_domain'));
        $host = strtolower(rtrim($request->getHost(), '.'));

        if ($host === $adminDomain || $host === $baseDomain || ! str_ends_with($host, '.'.$baseDomain)) {
            abort(404);
        }

        $subdomain = substr($host, 0, -strlen('.'.$baseDomain));

        if ($subdomain === '' || str_contains($subdomain, '.')) {
            abort(404);
        }

        // Primary subdomain match — proceed normally.
        $profile = CompanyProfile::query()
            ->live()
            ->where('subdomain', $subdomain)
            ->first();

        if ($profile) {
            $request->attributes->set('tenantProfile', $profile);

            return $next($request);
        }

        // Alias subdomain match — 301 permanent redirect to the primary host,
        // preserving the full path and query string for SEO canonicalisation.
        // Alias visits are intentionally NOT counted as page views; the
        // subsequent request to the primary host will be tracked instead.
        $alias = CompanyProfileAlias::query()
            ->with('companyProfile')
            ->where('subdomain', $subdomain)
            ->whereHas('companyProfile', fn ($q) => $q->live())
            ->first();

        if ($alias) {
            $primaryHost = $alias->companyProfile->subdomain.'.'.$baseDomain;
            $target = $request->getScheme().'://'.$primaryHost.$request->getRequestUri();

            return redirect()->away($target, 301);
        }

        abort(404);
    }
}
