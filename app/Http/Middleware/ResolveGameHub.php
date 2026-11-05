<?php

namespace App\Http\Middleware;

use App\Models\CompanyProfile;
use App\Services\GameHub;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Attach the live GameHub company profile for path routes on the hub host.
 */
class ResolveGameHub
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $profile = CompanyProfile::query()
            ->live()
            ->where('subdomain', GameHub::subdomain())
            ->first();

        if (! $profile instanceof CompanyProfile) {
            abort(404);
        }

        $request->attributes->set('tenantProfile', $profile);
        $this->exposeTenantParameter($request);

        return $next($request);
    }

    /**
     * Room actions take the domain parameter before {code}. Path routes have
     * no domain parameter, so supply tenant first or {code} lands in $tenant.
     */
    private function exposeTenantParameter(Request $request): void
    {
        $route = $request->route();

        if ($route === null || array_key_exists('tenant', $route->parameters())) {
            return;
        }

        $existing = $route->parameters();

        foreach (array_keys($existing) as $name) {
            $route->forgetParameter($name);
        }

        $route->setParameter('tenant', GameHub::subdomain());

        foreach ($existing as $name => $value) {
            $route->setParameter($name, $value);
        }
    }
}
