<?php

namespace App\Services;

/**
 * Static JadiDaring marketing homepage served at GET / on the apex and www hosts.
 *
 * The HTML lives in resources/marketing. CSS, JS, and images live under
 * public/marketing so they never collide with /{tenant} game paths. Relative
 * asset hrefs/src and production /images/ URLs are rewritten to that prefix
 * when the document is served. Generated JaRing/logo bitmaps are not shipped.
 */
class MarketingSite
{
    public static function path(): string
    {
        return 'marketing';
    }

    /**
     * Hosts that serve the homepage. Tenants stay on the www host only.
     *
     * @return list<string>
     */
    public static function hosts(): array
    {
        return array_values(array_unique([
            GameHub::host(),
            GameHub::baseDomain(),
        ]));
    }

    public static function documentPath(): string
    {
        return resource_path('marketing/index.html');
    }

    public static function html(): string
    {
        $path = self::documentPath();
        abort_unless(is_file($path), 404);

        $html = file_get_contents($path);
        abort_unless(is_string($html) && $html !== '', 404);

        return self::rewriteHtml($html);
    }

    public static function rewriteHtml(string $html): string
    {
        $prefix = '/'.self::path();

        $rewritten = preg_replace(
            '#(?<=["\'])(?:\./)?(css|js|images)/#',
            $prefix.'/$1/',
            $html,
        );

        $html = is_string($rewritten) ? $rewritten : $html;

        return str_replace(
            'https://www.jadidaring.com/images/',
            'https://www.jadidaring.com'.$prefix.'/images/',
            $html,
        );
    }

    public static function robotsTxt(): string
    {
        $host = 'https://www.jadidaring.com';

        return "User-agent: *\nAllow: /\nDisallow: /_boost/\n\n"
            ."Sitemap: {$host}/sitemap.xml\n"
            ."Sitemap: {$host}/gamehub/sitemap.xml\n";
    }

    public static function sitemapXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            .'<url><loc>https://www.jadidaring.com/</loc><changefreq>monthly</changefreq><priority>1.0</priority></url>'
            .'</urlset>';
    }
}
