<?php

use App\Http\Controllers\TenantController;
use App\Models\CompanyProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

Route::get('/', [TenantController::class, 'show'])->name('tenant.home');
Route::get('/syarat-ketentuan', [TenantController::class, 'terms'])->name('tenant.terms');
Route::redirect('/terms', '/syarat-ketentuan');

Route::get('/robots.txt', function (Request $request): Response {
    $host = $request->getHost();

    $body = "User-agent: *\nAllow: /\nDisallow: /api/\nDisallow: /_boost/\n\nSitemap: https://{$host}/sitemap.xml\n";

    return response($body)->header('Content-Type', 'text/plain; charset=utf-8');
});

Route::get('/sitemap.xml', function (Request $request): Response {
    $host = $request->getHost();
    $baseDomain = (string) config('singlepage.base_domain');
    $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';
    $subdomain = substr($host, 0, -strlen('.'.$baseDomain));
    $today = now()->toDateString();

    if ($subdomain === 'gamehub') {
        $locs = CompanyProfile::query()
            ->live()
            ->orderBy('subdomain')
            ->pluck('subdomain')
            ->map(fn (string $sub): string => "{$scheme}://{$sub}.{$baseDomain}/")
            ->all();
    } else {
        $locs = ["{$scheme}://{$host}/"];
    }

    $xml = '<?xml version="1.0" encoding="UTF-8"?>'
        .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

    foreach ($locs as $loc) {
        $xml .= '<url><loc>'.e($loc).'</loc><lastmod>'.$today.'</lastmod></url>';
    }

    $xml .= '</urlset>';

    return response($xml)->header('Content-Type', 'application/xml; charset=utf-8');
});

// Catch-all: ensures ResolveTenantSubdomain middleware fires for any web path on tenant
// domains so alias subdomains can be redirected. Named routes above take precedence;
// this only fires for unrecognised paths. The api/ prefix is excluded so game API
// routes (registered separately with prefix('api/game')) keep their own middleware.
Route::any('/{path?}', fn () => abort(404))->where('path', '(?!api\/).*');
