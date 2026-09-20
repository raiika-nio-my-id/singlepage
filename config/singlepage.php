<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Published profile base domain
    |--------------------------------------------------------------------------
    |
    | Tenant sites are {subdomain}.{base_domain}. The admin host is never a
    | tenant. Local default is jadidaring.test; production is jadidaring.com.
    |
    */

    'base_domain' => env('APP_BASE_DOMAIN', 'jadidaring.test'),

    /*
    |--------------------------------------------------------------------------
    | Admin CMS host
    |--------------------------------------------------------------------------
    |
    | Must be a single host (not a wildcard). Session cookies are host-only
    | and must never be issued with Domain=.jadidaring.com / .jadidaring.test.
    |
    */

    'admin_domain' => env('ADMIN_DOMAIN', 'admin.jadidaring.test'),

    'subscription_years' => (int) env('SUBSCRIPTION_YEARS', 1),

    /*
    |--------------------------------------------------------------------------
    | Platform legal notice
    |--------------------------------------------------------------------------
    |
    | Injected on every live tenant site. Users can change their HTML, but
    | they cannot remove this provider disclaimer from the public response.
    |
    */

    'platform_name' => env('PLATFORM_NAME', 'Jadidaring'),

    'terms_updated_on' => '2026-09-20',

    'reserved_subdomains' => [
        'admin',
        'www',
        'mail',
        'api',
        'app',
        'static',
        'assets',
    ],

];
