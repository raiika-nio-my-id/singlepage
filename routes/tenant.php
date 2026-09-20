<?php

use App\Http\Controllers\TenantController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TenantController::class, 'show'])->name('tenant.home');
Route::get('/syarat-ketentuan', [TenantController::class, 'terms'])->name('tenant.terms');
Route::redirect('/terms', '/syarat-ketentuan');
