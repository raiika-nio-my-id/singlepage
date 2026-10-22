<?php

use App\Http\Controllers\Admin\CompanyProfileController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/admin/login', '/login');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/', [CompanyProfileController::class, 'home'])->name('home');
    Route::put('/', [CompanyProfileController::class, 'updateOwn'])->name('client.workspace.update');
    Route::get('/clients', [CompanyProfileController::class, 'index'])->name('admin.dashboard');
    Route::get('/clients/create', [CompanyProfileController::class, 'create'])->name('admin.clients.create');
    Route::post('/clients', [CompanyProfileController::class, 'store'])->name('admin.clients.store');
    Route::post('/clients/reorder', [CompanyProfileController::class, 'reorder'])->name('admin.clients.reorder');
    Route::get('/clients/{companyProfile}/edit', [CompanyProfileController::class, 'edit'])->name('admin.clients.edit');
    Route::put('/clients/{companyProfile}', [CompanyProfileController::class, 'update'])->name('admin.clients.update');
    Route::middleware('can:manage-settings')->group(function (): void {
        Route::get('/settings', [SettingsController::class, 'edit'])->name('admin.settings');
        Route::put('/settings', [SettingsController::class, 'update'])->name('admin.settings.update');
    });
});
