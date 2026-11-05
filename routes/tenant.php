<?php

use Illuminate\Support\Facades\Route;

// Retired {subdomain}.{base_domain} hosts. ResolveTenantSubdomain 301s a live
// game to www.{base}/{subdomain}{uri} before these actions run. Drafts,
// expired profiles, reserved labels, and unknown names 404 here.
Route::any('/', fn () => abort(404));
Route::any('/{path}', fn () => abort(404))->where('path', '.*');
