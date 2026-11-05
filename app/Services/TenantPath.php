<?php

namespace App\Services;

use Illuminate\Http\Request;

/**
 * Public path URLs for game tenants and the 301 target for a retired subdomain.
 *
 * Pages live on the same host as GameHub (www.{base_domain}, or GAMEHUB_HOST).
 * A live game whose subdomain is "drawquarium" is
 * {scheme}://{host}/drawquarium/... The old host
 * drawquarium.{base_domain}/... answers 301 and keeps the path suffix and query.
 * The apex domain is the marketing site and is not a tenant host.
 *
 * Reverb stays at {host}/app and {host}/apps. Nginx proxies those prefixes
 * before PHP, so they are not moved under the game path.
 */
class TenantPath
{
    public static function host(): string
    {
        return GameHub::host();
    }

    /**
     * Path segment for a stored subdomain. The hub label uses the GameHub path.
     */
    public static function segment(string $subdomain): string
    {
        $subdomain = strtolower(trim($subdomain));

        if (GameHub::isSubdomain($subdomain)) {
            return GameHub::path();
        }

        return $subdomain;
    }

    public static function publicUrl(string $subdomain): string
    {
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

        return $scheme.'://'.self::host().'/'.self::segment($subdomain);
    }

    /**
     * Host plus path, without a scheme. Safe to show in the admin.
     */
    public static function label(string $subdomain): string
    {
        return self::host().'/'.self::segment($subdomain);
    }

    public static function homeUrl(Request $request, string $subdomain): string
    {
        return $request->getScheme().'://'.self::host().'/'.self::segment($subdomain).'/';
    }

    public static function termsUrl(Request $request, string $subdomain): string
    {
        return $request->getScheme().'://'.self::host().'/'.self::segment($subdomain).'/syarat-ketentuan';
    }

    public static function legacyHost(string $subdomain): string
    {
        return strtolower($subdomain).'.'.GameHub::baseDomain();
    }

    /**
     * Map a legacy-host or alias-path request onto the canonical path.
     *
     * On the retired subdomain the request URI is the suffix. On the public
     * host the first path segment is the alias and is replaced.
     */
    public static function redirectTarget(Request $request, string $subdomain): string
    {
        $prefix = $request->getScheme().'://'.self::host().'/'.self::segment($subdomain);
        $suffix = self::requestSuffix($request);

        if ($suffix === '' || $suffix === '/') {
            return $prefix.'/';
        }

        return $prefix.$suffix;
    }

    /**
     * Point stored HTML at path URLs. System hosts (admin, www, reverb, …)
     * are left alone. Websocket /app and /apps move to the public host root.
     * The shared hub JPEG stays at the host root. Root-relative /api/game and
     * terms links are prefixed with this game's path.
     */
    public static function rewriteHtml(string $html, string $subdomain): string
    {
        $html = self::rewriteAbsoluteUrls($html);

        return self::rewriteRootPaths($html, $subdomain);
    }

    public static function isHostLabel(string $label): bool
    {
        return preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', strtolower($label)) === 1;
    }

    public static function isBlockedLabel(string $label): bool
    {
        return in_array(strtolower($label), self::blockedPathLabels(), true);
    }

    /**
     * DNS label constraint for a {tenant} path segment or retired host label.
     *
     * Reserved labels other than gamehub are excluded. The boundary is the
     * next character, not `$` alone: on a domain the label is followed by
     * ".{base}", and on a path by "/" or the end of the URI. That keeps
     * GET /up on the public host on the health route, and keeps
     * www.{base} from matching the retired-host group. gamehub stays
     * matchable so /GameHub and gamehub.{base} can 301; the literal
     * /gamehub routes are static and win for the canonical path.
     */
    public static function routePattern(): string
    {
        $blocked = array_values(array_filter(
            self::blockedPathLabels(),
            static fn (string $label): bool => ! GameHub::isSubdomain($label),
        ));

        $alternation = implode('|', array_map(
            static fn (string $label): string => preg_quote($label, '#'),
            $blocked,
        ));

        return '(?!(?i:'.$alternation.')(?:/|$|\.))[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?';
    }

    /**
     * @return list<string>
     */
    public static function blockedPathLabels(): array
    {
        $labels = config('singlepage.reserved_subdomains', []);

        if (! is_array($labels)) {
            return [];
        }

        $normalized = [];

        foreach ($labels as $label) {
            if (! is_string($label)) {
                continue;
            }

            $label = strtolower(trim($label));

            if ($label !== '') {
                $normalized[] = $label;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Reserved hosts that must not be rewritten into a game path.
     * The gamehub label is not in this list; it becomes /gamehub.
     *
     * @return list<string>
     */
    public static function preservedHosts(): array
    {
        return array_values(array_filter(
            self::blockedPathLabels(),
            static fn (string $label): bool => ! GameHub::isSubdomain($label),
        ));
    }

    private static function requestSuffix(Request $request): string
    {
        $uri = $request->getRequestUri();
        $host = strtolower(rtrim($request->getHost(), '.'));

        if ($host !== self::host()) {
            return $uri;
        }

        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        $stripped = preg_replace('#^/[^/]+#', '', $path);
        $stripped = is_string($stripped) ? $stripped : '';
        $query = parse_url($uri, PHP_URL_QUERY);
        $pathPart = $stripped === '' ? '/' : $stripped;

        if (! is_string($query) || $query === '') {
            return $pathPart;
        }

        return $pathPart.'?'.$query;
    }

    private static function rewriteAbsoluteUrls(string $html): string
    {
        $base = preg_quote(GameHub::baseDomain(), '#');
        $host = self::host();
        $boundary = '(?<![A-Za-z0-9@.-])';
        $tail = '(?![A-Za-z0-9.-])';
        $label = '([a-z0-9](?:[a-z0-9-]*[a-z0-9])?)';
        $skip = self::preservedLookahead();

        $html = self::replaceCallback(
            $html,
            $boundary.preg_quote(GameHub::legacyHost().'/og-gamehub.jpg', '#').$tail,
            static fn (array $matches): string => $host.'/og-gamehub.jpg',
        );

        $html = self::replaceCallback(
            $html,
            $boundary.$skip.$label.'\.'.$base.'(/apps?(?![A-Za-z0-9-]))',
            static fn (array $matches): string => $host.$matches[2],
        );

        return self::replaceCallback(
            $html,
            $boundary.$skip.$label.'\.'.$base.$tail,
            static fn (array $matches): string => $host.'/'.strtolower($matches[1]),
        );
    }

    private static function rewriteRootPaths(string $html, string $subdomain): string
    {
        $path = self::segment($subdomain);

        $html = self::replace(
            $html,
            '(?P<attr>\b(?:href|action)\s*=\s*[\'"])/(syarat-ketentuan|terms)(?![A-Za-z0-9-])',
            '$1/'.$path.'/$2',
        );

        return self::replace(
            $html,
            '(?P<quote>[\'"])/api/game(?![A-Za-z0-9-])',
            '$1/'.$path.'/api/game',
        );
    }

    private static function preservedLookahead(): string
    {
        $labels = array_map(
            static fn (string $label): string => preg_quote($label, '#'),
            self::preservedHosts(),
        );

        if ($labels === []) {
            return '';
        }

        $alternatives = array_map(
            static fn (string $label): string => $label.'\.',
            $labels,
        );

        return '(?!'.implode('|', $alternatives).')';
    }

    private static function replace(string $html, string $pattern, string $replacement): string
    {
        $replaced = preg_replace('#'.$pattern.'#i', $replacement, $html);

        return is_string($replaced) ? $replaced : $html;
    }

    /**
     * @param  callable(array<int|string, string>): string  $callback
     */
    private static function replaceCallback(string $html, string $pattern, callable $callback): string
    {
        $replaced = preg_replace_callback('#'.$pattern.'#i', $callback, $html);

        return is_string($replaced) ? $replaced : $html;
    }
}
