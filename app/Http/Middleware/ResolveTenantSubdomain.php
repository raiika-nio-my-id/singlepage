<?php

namespace App\Http\Middleware;

use App\Models\CompanyProfile;
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

        $profile = CompanyProfile::query()
            ->live()
            ->where('subdomain', $subdomain)
            ->firstOrFail();

        $request->attributes->set('tenantProfile', $profile);

        return $next($request);
    }
}
