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

    'marketing_url' => env('PLATFORM_MARKETING_URL', 'https://jadidaring.com/'),

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

    /*
    |--------------------------------------------------------------------------
    | Machine-to-machine admin API
    |--------------------------------------------------------------------------
    |
    | POST/PATCH /api/admin/clients on the admin host. Callers must send
    | ADMIN_API_SECRET as the X-Admin-Api-Key header. An empty secret
    | rejects every request (fail closed).
    |
    */

    'admin_api' => [
        'secret' => env('ADMIN_API_SECRET'),
        'header' => 'X-Admin-Api-Key',
    ],

];
