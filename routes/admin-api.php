<?php

use App\Http\Controllers\Api\Admin\ClientController;
use Illuminate\Support\Facades\Route;

Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');

Route::patch('/clients/{companyProfile}', [ClientController::class, 'update'])
    ->whereNumber('companyProfile')
    ->name('clients.update');

Route::patch('/clients/subdomain/{subdomain}', [ClientController::class, 'updateBySubdomain'])
    ->where('subdomain', '[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?')
    ->name('clients.update-by-subdomain');
