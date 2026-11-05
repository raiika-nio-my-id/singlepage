<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Published profile base domain
    |--------------------------------------------------------------------------
    |
    | Game pages are https://www.{base_domain}/{subdomain}/. The stored label
    | is still the company subdomain. {subdomain}.{base_domain}/... answers
    | 301 to that path and keeps the suffix and query string. The admin host
    | is never a tenant. The apex domain is the marketing site, not this app.
    | Local default is jadidaring.test; production is jadidaring.com.
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

    /*
    |--------------------------------------------------------------------------
    | GameHub path
    |--------------------------------------------------------------------------
    |
    | The directory used to be the tenant host gamehub.{base_domain}. It is
    | a path on the host this app already serves, and every other game uses
    | that same host under /{subdomain}. The apex domain is the static
    | marketing site, not this Laravel app. www.{base_domain} is already
    | proxied here, so that is the public host unless GAMEHUB_HOST is set
    | (for example after a one-time proxy rule on the apex vhost).
    |
    | Hub HTML stays on the live CompanyProfile whose subdomain is gamehub.
    | That label is reserved. Dedicated /gamehub routes win over the generic
    | game path, so a profile named gamehub stays the directory.
    | gamehub.{base_domain}/... answers 301 to {host}/{path}/... and keeps
    | the path suffix and query string. Other games redirect the same way.
    |
    */

    'gamehub' => [
        'subdomain' => 'gamehub',
        'path' => 'gamehub',
        'host' => env('GAMEHUB_HOST'),
    ],

    /*
    | Labels that are not game paths. gamehub is the directory. app, apps,
    | and reverb belong to the websocket proxy (nginx claims /app and /apps
    | before PHP). up is the Laravel health route. www is the public host.
    | The apex domain has no label. Admin settings can add more names; the
    | built-in list is always reserved even when the saved setting omits it.
    */

    'reserved_subdomains' => [
        'admin',
        'www',
        'gamehub',
        'mail',
        'api',
        'app',
        'apps',
        'static',
        'assets',
        'reverb',
        'up',
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
