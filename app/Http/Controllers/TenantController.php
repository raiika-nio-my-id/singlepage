<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Services\LegalNoticeInjector;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TenantController extends Controller
{
    /** User-agent fragments that indicate known bots / automated tools. */
    private const BOT_UA_PATTERNS = [
        'bot', 'crawler', 'spider', 'scraper', 'slurp', 'facebookexternalhit',
        'wget', 'curl/', 'python-requests', 'go-http-client', 'libwww', 'okhttp',
        'headlesschrome', 'phantomjs', 'selenium',
    ];

    public function show(Request $request, LegalNoticeInjector $injector): Response
    {
        $profile = $this->profile($request);
        $html = $profile->hide_footer
            ? $profile->html
            : $injector->inject($profile->html, $this->termsUrl($request));

        $this->trackVisit($profile, $request);

        return response($html, 200, [
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

    private function trackVisit(CompanyProfile $profile, Request $request): void
    {
        $ua = strtolower($request->userAgent() ?? '');

        foreach (self::BOT_UA_PATTERNS as $pattern) {
            if (str_contains($ua, $pattern)) {
                return;
            }
        }

        $hash = hash('sha256', implode('|', [
            $request->ip() ?? '',
            $request->userAgent() ?? '',
            now()->toDateString(),
            (string) config('app.key'),
        ]));

        $profileId = $profile->id;
        $today = now()->toDateString();

        // Defer the DB write until after the response is sent so it never
        // adds latency to page serving. Swallow all exceptions so that a
        // tracking failure can never break the tenant page.
        defer(function () use ($profileId, $today, $hash): void {
            try {
                DB::table('page_view_records')->insertOrIgnore([
                    'company_profile_id' => $profileId,
                    'viewed_on' => $today,
                    'visitor_hash' => $hash,
                    'created_at' => now(),
                ]);
            } catch (\Throwable) {
                // Intentionally silent — tracking must never break page delivery.
            }
        });
    }
}
