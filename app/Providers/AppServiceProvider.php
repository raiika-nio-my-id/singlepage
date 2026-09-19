<?php

namespace App\Providers;

use App\Models\CompanyProfile;
use App\Models\User;
use App\Policies\CompanyProfilePolicy;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Host-only admin cookies. Never Domain=.jadidaring.com / .jadidaring.test.
        Config::set('session.domain', null);
        Config::set('session.http_only', true);
        Config::set('session.same_site', 'strict');

        Gate::policy(CompanyProfile::class, CompanyProfilePolicy::class);

        Gate::define('manage-settings', function (User $user): bool {
            return $user->can('manageSettings', CompanyProfile::class);
        });
    }
}
