<?php

use App\Http\Controllers\MarketingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MarketingController::class, 'show']);
Route::get('/robots.txt', [MarketingController::class, 'robots']);
Route::get('/sitemap.xml', [MarketingController::class, 'sitemap']);
