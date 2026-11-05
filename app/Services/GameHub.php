<?php

namespace App\Services;

use App\Models\CompanyProfile;
use Illuminate\Http\Request;

/**
 * Public GameHub URL and the 301 target for the retired gamehub host.
 *
 * The hub document is the live company profile whose subdomain is "gamehub".
 * That label is reserved and is no longer a tenant host. The apex domain is
 * the static marketing site, so the directory is served on www.{base_domain}
 * unless GAMEHUB_HOST overrides the host.
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
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

        return $scheme.'://'.self::host().'/'.self::path();
    }

    public static function homeUrl(Request $request): string
    {
        return $request->getScheme().'://'.self::host().'/'.self::path().'/';
    }

    public static function termsUrl(Request $request): string
    {
        return $request->getScheme().'://'.self::host().'/'.self::path().'/syarat-ketentuan';
    }

    public static function ogImageUrl(Request $request): string
    {
        return $request->getScheme().'://'.self::host().'/og-gamehub.jpg';
    }

    /**
     * Map a request on the retired host onto the path, keeping the suffix and query.
     */
    public static function redirectTarget(Request $request): string
    {
        $prefix = $request->getScheme().'://'.self::host().'/'.self::path();
        $uri = $request->getRequestUri();

        if ($uri === '' || $uri === '/') {
            return $prefix.'/';
        }

        return $prefix.$uri;
    }

    /**
     * Point stored hub HTML at the path URL without touching other game hosts.
     *
     * The profile HTML lives in the database and already contains canonical,
     * Open Graph, and JSON-LD URLs for the old host. SeoInjector will not
     * replace tags that are already present, so the rewrite happens first.
     * The shared JPEG and the Reverb /app endpoint stay on the hub host root
     * because nginx serves both before PHP.
     */
    public static function rewriteHtml(string $html): string
    {
        $legacyHost = self::legacyHost();
        $hubHost = self::host();
        $path = self::path();
        $boundary = '(?<![A-Za-z0-9-])';
        $tail = '(?![A-Za-z0-9.-])';

        $html = self::replace($html, $boundary.preg_quote($legacyHost.'/og-gamehub.jpg', '#').$tail, $hubHost.'/og-gamehub.jpg');
        $html = self::replace($html, $boundary.preg_quote($legacyHost.'/app', '#').$tail, $hubHost.'/app');
        $html = self::replace($html, $boundary.preg_quote($legacyHost, '#').$tail, $hubHost.'/'.$path);
        $html = self::replace(
            $html,
            '(?P<attr>\b(?:href|action)\s*=\s*[\'"])/(syarat-ketentuan|terms)(?![A-Za-z0-9-])',
            '$1/'.$path.'/$2',
        );
        $html = self::replace(
            $html,
            '(?P<quote>[\'"])/api/game(?![A-Za-z0-9-])',
            '$1/'.$path.'/api/game',
        );

        return $html;
    }

    /**
     * @return list<string>
     */
    public static function sitemapLocs(): array
    {
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';
        $baseDomain = self::baseDomain();
        $home = $scheme.'://'.self::host().'/'.self::path().'/';

        return CompanyProfile::query()
            ->live()
            ->orderBy('subdomain')
            ->pluck('subdomain')
            ->map(function (string $subdomain) use ($scheme, $baseDomain, $home): string {
                if (self::isSubdomain($subdomain)) {
                    return $home;
                }

                return "{$scheme}://{$subdomain}.{$baseDomain}/";
            })
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

    private static function replace(string $html, string $pattern, string $replacement): string
    {
        $replaced = preg_replace('#'.$pattern.'#i', $replacement, $html);

        return is_string($replaced) ? $replaced : $html;
    }
}
