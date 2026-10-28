<?php

use App\Http\Middleware\AuthenticateAdminApiKey;
use App\Http\Middleware\ResolveTenantSubdomain;
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
            Route::middleware('web')
                ->domain((string) config('singlepage.admin_domain'))
                ->group(base_path('routes/admin.php'));

            Route::middleware(['web', ResolveTenantSubdomain::class])
                ->domain('{tenant}.'.config('singlepage.base_domain'))
                ->group(base_path('routes/tenant.php'));

            Route::middleware([ResolveTenantSubdomain::class])
                ->domain('{tenant}.'.config('singlepage.base_domain'))
                ->prefix('api/game')
                ->name('game.')
                ->group(base_path('routes/game.php'));

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
