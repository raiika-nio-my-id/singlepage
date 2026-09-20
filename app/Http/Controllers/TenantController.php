<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Services\LegalNoticeInjector;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class TenantController extends Controller
{
    public function show(Request $request, LegalNoticeInjector $injector): Response
    {
        $profile = $this->profile($request);

        return response($injector->inject($profile->html, $this->termsUrl($request)), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        ]);
    }

    public function terms(Request $request): View
    {
        $this->profile($request);

        return view('tenant.terms', [
            'platformName' => (string) config('singlepage.platform_name', 'Jadidaring'),
            'updatedOn' => Carbon::parse((string) config('singlepage.terms_updated_on'))
                ->locale('id')
                ->translatedFormat('d F Y'),
            'siteHost' => $request->getHost(),
            'homeUrl' => $request->getSchemeAndHttpHost().'/',
        ]);
    }

    private function profile(Request $request): CompanyProfile
    {
        $profile = $request->attributes->get('tenantProfile');

        abort_unless($profile instanceof CompanyProfile, 404);

        return $profile;
    }

    private function termsUrl(Request $request): string
    {
        return $request->getSchemeAndHttpHost().'/syarat-ketentuan';
    }
}
