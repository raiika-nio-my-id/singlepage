<?php

use App\Http\Middleware\AuthenticateAdminApiKey;
use App\Http\Middleware\ResolveGameHub;
use App\Http\Middleware\ResolveTenantPath;
use App\Http\Middleware\ResolveTenantSubdomain;
use App\Services\GameHub;
use App\Services\MarketingSite;
use App\Services\TenantPath;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function (): void {
            // Exact GET / (plus root robots/sitemap) on apex and www. Not
            // registered on the admin host. Asset files live in public/marketing.
            foreach (MarketingSite::hosts() as $host) {
                Route::middleware('web')
                    ->domain($host)
                    ->group(base_path('routes/marketing.php'));
            }

            // Registered before {tenant} so www.{base}/gamehub is the hub, not a game path.
            Route::middleware(['web', ResolveGameHub::class])
                ->domain(GameHub::host())
                ->name('gamehub.')
                ->group(base_path('routes/gamehub.php'));

            Route::middleware([ResolveGameHub::class])
                ->domain(GameHub::host())
                ->prefix(GameHub::path().'/api/game')
                ->name('gamehub.game.')
                ->group(base_path('routes/game.php'));

            Route::middleware(['web', ResolveTenantPath::class])
                ->domain(TenantPath::host())
                ->name('path.')
                ->group(base_path('routes/tenant-path.php'));

            Route::middleware([ResolveTenantPath::class])
                ->domain(TenantPath::host())
                ->prefix('{tenant}/api/game')
                ->where(['tenant' => TenantPath::routePattern()])
                ->name('path.game.')
                ->group(base_path('routes/game.php'));

            Route::middleware('web')
                ->domain((string) config('singlepage.admin_domain'))
                ->group(base_path('routes/admin.php'));

            // Retired game hosts. Live profiles 301 to /{subdomain} on the public host.
            // www and the other reserved labels are not part of this domain,
            // so the public host keeps /up and its path routes.
            Route::middleware(ResolveTenantSubdomain::class)
                ->domain('{tenant}.'.config('singlepage.base_domain'))
                ->where(['tenant' => TenantPath::routePattern()])
                ->group(base_path('routes/tenant.php'));

            Route::middleware(['api', 'admin.api', 'throttle:admin-api'])
                ->domain((string) config('singlepage.admin_domain'))
                ->prefix('api/admin')
                ->name('api.admin.')
                ->group(base_path('routes/admin-api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('home'));

        $middleware->alias([
            'admin.api' => AuthenticateAdminApiKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if ($request->is('api/admin/*')) {
                return response()->json(['message' => 'Client not found.'], 404);
            }
        });
    })->create();
