<?php

use App\Http\Controllers\TenantController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TenantController::class, 'show'])->name('tenant.home');
