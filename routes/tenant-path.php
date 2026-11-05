<?php

use App\Http\Controllers\TenantController;
use App\Models\CompanyProfile;
use App\Services\TenantPath;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

Route::where(['tenant' => TenantPath::routePattern()])->group(function (): void {
    Route::get('/{tenant}', [TenantController::class, 'show'])->name('home');
    Route::get('/{tenant}/', [TenantController::class, 'show']);
    Route::get('/{tenant}/syarat-ketentuan', [TenantController::class, 'terms'])->name('terms');

    Route::get('/{tenant}/terms', function (Request $request, string $tenant) {
        $location = '/'.strtolower($tenant).'/syarat-ketentuan';
        $query = $request->getQueryString();

        if (is_string($query) && $query !== '') {
            $location .= '?'.$query;
        }

        return redirect($location);
    });

    Route::get('/{tenant}/robots.txt', function (Request $request): Response {
        $profile = $request->attributes->get('tenantProfile');
        abort_unless($profile instanceof CompanyProfile, 404);

        $segment = TenantPath::segment($profile->subdomain);
        $sitemap = 'https://'.TenantPath::host().'/'.$segment.'/sitemap.xml';
        $body = "User-agent: *\nAllow: /{$segment}/\nDisallow: /{$segment}/api/\nDisallow: /_boost/\n\nSitemap: {$sitemap}\n";

        return response($body)->header('Content-Type', 'text/plain; charset=utf-8');
    });

    Route::get('/{tenant}/sitemap.xml', function (Request $request): Response {
        $profile = $request->attributes->get('tenantProfile');
        abort_unless($profile instanceof CompanyProfile, 404);

        $loc = rtrim($profile->publicUrl(), '/').'/';
        $today = now()->toDateString();
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            .'<url><loc>'.e($loc).'</loc><lastmod>'.$today.'</lastmod></url>'
            .'</urlset>';

        return response($xml)->header('Content-Type', 'application/xml; charset=utf-8');
    });

    // Unknown game paths 404. api/ is excluded so /{tenant}/api/game keeps its own routes.
    Route::any('/{tenant}/{path}', fn () => abort(404))->where('path', '(?!api\/).*');
});
