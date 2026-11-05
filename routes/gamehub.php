<?php

use App\Http\Controllers\TenantController;
use App\Services\GameHub;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

Route::get('/gamehub', [TenantController::class, 'show'])->name('home');
Route::get('/gamehub/', [TenantController::class, 'show']);
Route::get('/gamehub/syarat-ketentuan', [TenantController::class, 'terms'])->name('terms');
Route::redirect('/gamehub/terms', '/'.GameHub::path().'/syarat-ketentuan');

Route::get('/gamehub/robots.txt', function (): Response {
    return response(GameHub::robotsTxt())->header('Content-Type', 'text/plain; charset=utf-8');
})->name('robots');

Route::get('/gamehub/sitemap.xml', function (): Response {
    return response(GameHub::sitemapXml())->header('Content-Type', 'application/xml; charset=utf-8');
})->name('sitemap');

// Unknown hub paths 404. api/ is excluded so /gamehub/api/game keeps its own routes.
Route::any('/gamehub/{path}', fn () => abort(404))->where('path', '(?!api\/).*');
