<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TenantController extends Controller
{
    public function show(Request $request): Response
    {
        $profile = $request->attributes->get('tenantProfile');

        abort_unless($profile instanceof CompanyProfile, 404);

        return response($profile->html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        ]);
    }
}
