<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAdminApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('singlepage.admin_api.secret');
        $provided = (string) $request->header((string) config('singlepage.admin_api.header'));

        if ($secret === '' || $provided === '' || ! hash_equals($secret, $provided)) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 401);
        }

        return $next($request);
    }
}
