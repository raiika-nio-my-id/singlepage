<?php

namespace App\Services;

use App\Models\CompanyProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Public GameHub URL and the 301 target for the retired gamehub host.
 *
 * The hub document is the live company profile whose subdomain is "gamehub".
 * Stored HTML is the chrome. GameHubCatalog fills the directory on each
 * request from other live profiles tagged is_game. There is no catalog cache.
 * The gamehub label is reserved and is not a normal game path. GET / on the apex
 * and on www is the marketing homepage. The directory is served on
 * www.{base_domain} unless GAMEHUB_HOST overrides the host. Every other
 * game uses that same host under /{subdomain}; see TenantPath.
 */
class GameHub
{
    public static function subdomain(): string
    {
        return strtolower((string) config('singlepage.gamehub.subdomain', 'gamehub'));
    }

    public static function path(): string
    {
        $path = trim((string) config('singlepage.gamehub.path', 'gamehub'), '/');

        return $path !== '' ? $path : 'gamehub';
    }

    public static function baseDomain(): string
    {
        return strtolower((string) config('singlepage.base_domain'));
    }

    public static function host(): string
    {
        $configured = config('singlepage.gamehub.host');

        if (is_string($configured) && $configured !== '') {
            return strtolower($configured);
        }

        return 'www.'.self::baseDomain();
    }

    public static function legacyHost(): string
    {
        return self::subdomain().'.'.self::baseDomain();
    }

    public static function isSubdomain(string $subdomain): bool
    {
        return strtolower($subdomain) === self::subdomain();
    }

    public static function isProfile(CompanyProfile $profile): bool
    {
        return self::isSubdomain($profile->subdomain);
    }

    public static function publicUrl(): string
    {
        return TenantPath::publicUrl(self::subdomain());
    }

    public static function homeUrl(Request $request): string
    {
        return TenantPath::homeUrl($request, self::subdomain());
    }

    public static function termsUrl(Request $request): string
    {
        return TenantPath::termsUrl($request, self::subdomain());
    }

    public static function ogImageUrl(Request $request): string
    {
        return $request->getScheme().'://'.self::host().'/og-gamehub.jpg';
    }

    /**
     * Map a request on the retired host onto the hub path, keeping the suffix and query.
     */
    public static function redirectTarget(Request $request): string
    {
        return TenantPath::redirectTarget($request, self::subdomain());
    }

    /**
     * Point stored hub HTML at path URLs.
     *
     * The profile HTML lives in the database and already contains canonical,
     * Open Graph, and JSON-LD URLs for the old host. SeoInjector will not
     * replace tags that are already present, so the rewrite happens first.
     * Other game hosts move to their own paths. System hosts stay put. The
     * shared JPEG and the Reverb /app endpoint stay on the hub host root
     * because nginx serves both before PHP.
     */
    public static function rewriteHtml(string $html): string
    {
        return TenantPath::rewriteHtml($html, self::subdomain());
    }

    /**
     * @return list<string>
     */
    public static function sitemapLocs(): array
    {
        return CompanyProfile::query()
            ->live()
            ->where(function (Builder $query): void {
                $query->where('is_game', true)
                    ->orWhere('subdomain', self::subdomain());
            })
            ->orderBy('subdomain')
            ->pluck('subdomain')
            ->map(fn (string $subdomain): string => rtrim(CompanyProfile::publicUrlFor($subdomain), '/').'/')
            ->all();
    }

    public static function sitemapXml(): string
    {
        $today = now()->toDateString();
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach (self::sitemapLocs() as $loc) {
            $xml .= '<url><loc>'.e($loc).'</loc><lastmod>'.$today.'</lastmod></url>';
        }

        return $xml.'</urlset>';
    }

    public static function robotsTxt(): string
    {
        $path = self::path();
        $sitemap = 'https://'.self::host().'/'.$path.'/sitemap.xml';

        return "User-agent: *\nAllow: /{$path}/\nDisallow: /{$path}/api/\nDisallow: /_boost/\n\nSitemap: {$sitemap}\n";
    }
}
