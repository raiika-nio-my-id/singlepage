<?php

namespace App\Services;

use App\Models\CompanyProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use JsonException;

/**
 * Live GameHub directory.
 *
 * Cards come from published, unexpired company profiles tagged is_game.
 * Profiles default to not a game, so company sites and demos stay out until
 * an admin marks them. The stored hub HTML keeps the chrome (hero, FAQ,
 * styles). Its hand-written games list, prerendered cards, and ItemList are
 * replaced on each request.
 *
 * "Paling dilihat" counts rows in page_view_records. TenantController writes
 * one row per visitor per day when that game's path is opened. Bots are
 * skipped there, and opening GameHub itself does not count as a play.
 */
class GameHubCatalog
{
    public const SORT_NEW = 'new';

    public const SORT_VIEWS = 'views';

    public static function normalizeSort(mixed $sort): string
    {
        if (! is_string($sort)) {
            return self::SORT_NEW;
        }

        return strtolower($sort) === self::SORT_VIEWS ? self::SORT_VIEWS : self::SORT_NEW;
    }

    /**
     * Title, description, and cover taken from a game page.
     *
     * The title prefers og:title, then the document title. A SEO tail after
     * " | ", " · ", or " • " is dropped. An em dash stays, so
     * "Jade Room — Mahjong, together" is kept whole. Description prefers
     * the meta description, then og:description. The cover is og:image, then
     * twitter:image. The shared hub JPEG is not a game cover.
     *
     * @return array{title: string, description: string, image: string}
     */
    public static function pageMetadata(string $html, string $scheme = 'https'): array
    {
        $scheme = strtolower($scheme) === 'http' ? 'http' : 'https';
        $title = self::metaContent($html, 'property', 'og:title');

        if ($title === '') {
            $title = self::documentTitle($html);
        }

        $description = self::metaContent($html, 'name', 'description');

        if ($description === '') {
            $description = self::metaContent($html, 'property', 'og:description');
        }

        $image = '';

        foreach ([['property', 'og:image'], ['name', 'twitter:image']] as [$attribute, $key]) {
            $candidate = self::absoluteImage(self::metaContent($html, $attribute, $key), $scheme);

            if ($candidate !== null) {
                $image = $candidate;
                break;
            }
        }

        return [
            'title' => self::limit(self::shortenTitle($title), 140),
            'description' => self::limit($description, 600),
            'image' => $image,
        ];
    }

    public function apply(string $html, Request $request): string
    {
        $sort = self::normalizeSort($request->query('sort'));
        $configScript = $this->readJsonScript($html, 'website-config');
        $jsonLdScript = $this->readJsonScript($html, 'seo-jsonld');
        $games = $this->listings($request, $sort, $this->previousGames($configScript['value'] ?? null));

        if ($configScript !== null) {
            $configScript['value']['games'] = $this->configGames($games);
        }

        if ($jsonLdScript !== null) {
            $jsonLdScript['value'] = $this->rewriteJsonLdNode($jsonLdScript['value'], $games);
        }

        $writes = array_values(array_filter(
            [$configScript, $jsonLdScript],
            static fn (?array $script): bool => $script !== null,
        ));
        usort($writes, static fn (array $left, array $right): int => $right['start'] <=> $left['start']);

        foreach ($writes as $script) {
            $html = $this->writeJsonScript($html, $script['start'], $script['end'], $script['value']);
        }

        $html = $this->patchShowcaseScript($html);
        $html = $this->injectStyle($html);

        $cards = view('tenant.gamehub-cards', ['games' => $games])->render();
        $sortNav = view('tenant.gamehub-sort', [
            'sort' => $sort,
            'urls' => [
                self::SORT_NEW => $this->sortUrl($request, self::SORT_NEW),
                self::SORT_VIEWS => $this->sortUrl($request, self::SORT_VIEWS),
            ],
        ])->render();

        if ($this->hasPrerenderCards($html)) {
            return $this->insertSortNav($this->replacePrerenderCards($html, $cards), $sortNav);
        }

        return $this->appendSection($html, $sortNav, $cards);
    }

    /**
     * @param  array<string, array<mixed>>  $previousBySubdomain
     * @return list<array{
     *     subdomain: string,
     *     title: string,
     *     description: string,
     *     url: string,
     *     hostLabel: string,
     *     image: string,
     *     views: int,
     *     number: string,
     *     initials: string,
     *     tags: list<string>,
     *     genre: list<string>,
     *     playMode: list<string>,
     *     badge: string,
     *     lang: string,
     *     schemaType: string,
     *     snapshotSize: array{0: int, 1: int}
     * }>
     */
    private function listings(Request $request, string $sort, array $previousBySubdomain): array
    {
        $views = DB::table('page_view_records')
            ->selectRaw('company_profile_id, COUNT(*) as views_count')
            ->groupBy('company_profile_id');

        $query = CompanyProfile::query()
            ->live()
            ->games()
            ->with('user:id,name')
            ->select('company_profiles.*')
            ->selectRaw('COALESCE(game_views.views_count, 0) as views_count')
            ->leftJoinSub($views, 'game_views', 'game_views.company_profile_id', '=', 'company_profiles.id')
            ->where('company_profiles.subdomain', '!=', GameHub::subdomain());

        if ($sort === self::SORT_VIEWS) {
            $query->orderByRaw('COALESCE(game_views.views_count, 0) DESC');
        }

        $profiles = $query
            ->orderByDesc('company_profiles.created_at')
            ->orderByDesc('company_profiles.id')
            ->get();

        return $profiles->values()->map(function (CompanyProfile $profile, int $index) use ($request, $previousBySubdomain): array {
            $subdomain = strtolower($profile->subdomain);
            $previous = $previousBySubdomain[$subdomain] ?? [];
            $meta = self::pageMetadata((string) $profile->html, $request->getScheme());
            $title = $meta['title'];

            if ($title === '') {
                $title = self::plainText((string) ($previous['title'] ?? ''));
            }

            if ($title === '') {
                $title = self::plainText((string) ($profile->user?->name ?? ''));
            }

            if ($title === '') {
                $title = $subdomain;
            }

            $title = self::limit($title, 140);
            $description = $meta['description'] !== ''
                ? $meta['description']
                : self::limit(self::plainText((string) ($previous['description'] ?? '')), 600);
            $image = $meta['image'];
            $snapshotSize = self::snapshotSize($previous['snapshotSize'] ?? null);

            if ($image === '') {
                $snapshot = $previous['snapshot'] ?? '';

                if (is_string($snapshot) && preg_match('#\Adata:image/(webp|png|jpeg);base64,[A-Za-z0-9+/=]+\z#', $snapshot) === 1) {
                    $image = $snapshot;
                }
            }

            return [
                'subdomain' => $subdomain,
                'title' => $title,
                'description' => $description,
                'url' => TenantPath::homeUrl($request, $subdomain),
                'hostLabel' => TenantPath::label($subdomain),
                'image' => $image,
                'views' => (int) $profile->getAttribute('views_count'),
                'number' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                'initials' => self::initials($title),
                'tags' => self::stringList($previous['tags'] ?? []),
                'genre' => self::stringList($previous['genre'] ?? []),
                'playMode' => self::stringList($previous['playMode'] ?? []),
                'badge' => self::limit(self::plainText((string) ($previous['badge'] ?? '')), 40),
                'lang' => self::language($previous),
                'schemaType' => self::schemaType($previous),
                'snapshotSize' => $snapshotSize,
            ];
        })->all();
    }

    /**
     * @param  array<mixed>|null  $config
     * @return array<string, array<mixed>>
     */
    private function previousGames(?array $config): array
    {
        if ($config === null || ! isset($config['games']) || ! is_array($config['games'])) {
            return [];
        }

        $previous = [];

        foreach ($config['games'] as $game) {
            if (! is_array($game)) {
                continue;
            }

            $subdomain = strtolower(trim((string) ($game['subdomain'] ?? '')));

            if ($subdomain === '' || ! TenantPath::isHostLabel($subdomain) || GameHub::isSubdomain($subdomain)) {
                continue;
            }

            $previous[$subdomain] = $game;
        }

        return $previous;
    }

    /**
     * @param  list<array<string, mixed>>  $games
     * @return list<array<string, mixed>>
     */
    private function configGames(array $games): array
    {
        return array_map(function (array $game): array {
            $entry = [
                'subdomain' => $game['subdomain'],
                'title' => $game['title'],
                'description' => $game['description'],
                'url' => $game['url'],
                'hostLabel' => $game['hostLabel'],
                'tags' => $game['tags'],
                'views' => $game['views'],
            ];

            if ($game['image'] !== '') {
                $entry['snapshot'] = $game['image'];
                $entry['snapshotSize'] = $game['snapshotSize'];
            }

            if ($game['badge'] !== '') {
                $entry['badge'] = $game['badge'];
            }

            if ($game['genre'] !== []) {
                $entry['genre'] = $game['genre'];
            }

            if ($game['playMode'] !== []) {
                $entry['playMode'] = $game['playMode'];
            }

            if ($game['lang'] !== '') {
                $entry['lang'] = $game['lang'];
            }

            return $entry;
        }, $games);
    }

    /**
     * @param  list<array<string, mixed>>  $games
     * @return list<array<string, mixed>>
     */
    private function itemListElements(array $games): array
    {
        $items = [];

        foreach ($games as $index => $game) {
            $item = [
                '@type' => $game['schemaType'],
                'name' => $game['title'],
                'url' => $game['url'],
                'inLanguage' => $game['lang'] !== '' ? $game['lang'] : 'id',
                'isAccessibleForFree' => true,
                'gamePlatform' => 'Web browser',
                'offers' => [
                    '@type' => 'Offer',
                    'price' => '0',
                    'priceCurrency' => 'IDR',
                    'availability' => 'https://schema.org/InStock',
                ],
            ];

            if ($game['description'] !== '') {
                $item['description'] = $game['description'];
            }

            if ($game['image'] !== '' && ! str_starts_with($game['image'], 'data:')) {
                $item['image'] = $game['image'];
            }

            if ($game['genre'] !== []) {
                $item['genre'] = $game['genre'];
            }

            $modes = $this->schemaPlayModes($game['playMode']);

            if ($modes !== []) {
                $item['playMode'] = $modes;
            }

            $items[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'item' => $item,
            ];
        }

        return $items;
    }

    /**
     * @param  list<string>  $modes
     * @return list<string>
     */
    private function schemaPlayModes(array $modes): array
    {
        $urls = [];

        foreach ($modes as $mode) {
            if (str_starts_with($mode, 'https://schema.org/')) {
                $urls[] = $mode;

                continue;
            }

            if (in_array($mode, ['SinglePlayer', 'MultiPlayer', 'CoOp'], true)) {
                $urls[] = 'https://schema.org/'.$mode;
            }
        }

        return $urls;
    }

    /**
     * @param  array<mixed>  $node
     * @param  list<array<string, mixed>>  $games
     * @return array<mixed>
     */
    private function rewriteJsonLdNode(array $node, array $games): array
    {
        if ($this->typeIs($node, 'ItemList') && array_key_exists('itemListElement', $node)) {
            $node['numberOfItems'] = count($games);
            $node['itemListElement'] = $this->itemListElements($games);

            return $node;
        }

        foreach ($node as $key => $child) {
            if (is_array($child)) {
                $node[$key] = $this->rewriteJsonLdNode($child, $games);
            }
        }

        return $node;
    }

    /**
     * @param  array<mixed>  $node
     */
    private function typeIs(array $node, string $type): bool
    {
        $actual = $node['@type'] ?? null;

        if (is_string($actual)) {
            return $actual === $type;
        }

        return is_array($actual) && in_array($type, $actual, true);
    }

    /**
     * @return array{start: int, end: int, value: array<mixed>}|null
     */
    private function readJsonScript(string $html, string $id): ?array
    {
        foreach (['id="'.$id.'"', "id='".$id."'"] as $needle) {
            $pos = stripos($html, $needle);

            if ($pos === false) {
                continue;
            }

            $start = strpos($html, '>', $pos);
            $end = $start === false ? false : stripos($html, '</script>', $start);

            if ($start === false || $end === false) {
                return null;
            }

            $value = json_decode(trim(substr($html, $start + 1, $end - ($start + 1))), true);

            if (! is_array($value)) {
                return null;
            }

            return [
                'start' => $start + 1,
                'end' => $end,
                'value' => $value,
            ];
        }

        return null;
    }

    /**
     * @param  array<mixed>  $value
     */
    private function writeJsonScript(string $html, int $start, int $end, array $value): string
    {
        try {
            $json = json_encode(
                $value,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            return $html;
        }

        return substr($html, 0, $start).$json.substr($html, $end);
    }

    private function patchShowcaseScript(string $html): string
    {
        $html = str_replace(
            'url: u.href, host: u.host,',
            'url: u.href, host: g.hostLabel || u.host, subdomain: String(g.subdomain || ""),',
            $html,
        );
        $html = str_replace(
            's.textContent = g.host.split(".")[0];',
            's.textContent = g.subdomain || g.host.split(".")[0];',
            $html,
        );

        return str_replace(
            'snapshot: /^data:image\\/(webp|png|jpeg);base64,/.test(g.snapshot || "") ? g.snapshot : "",',
            'snapshot: /^(?:data:image\\/(?:webp|png|jpeg);base64,|https?:\\/\\/)/.test(g.snapshot || "") ? g.snapshot : "",',
            $html,
        );
    }

    private function injectStyle(string $html): string
    {
        if (str_contains($html, 'data-gamehub-sort-style')) {
            return $html;
        }

        $style = '<style data-gamehub-sort-style>'
            .'.hub-sort{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0}'
            .'.hub-sort a{display:inline-flex;align-items:center;padding:6px 12px;border-radius:999px;border:1px solid rgba(0,0,0,.14);background:transparent;color:inherit;text-decoration:none;font:600 13px/1.2 ui-sans-serif,system-ui,sans-serif}'
            .'.hub-sort a[aria-current="page"]{background:#c9f23b;border-color:transparent;color:#151515}'
            .'.hub-empty{margin:16px 0;color:#4d4d4a}'
            .'</style>';

        $replaced = preg_replace('/<\/head>/i', $style.'</head>', $html, 1, $count);

        if (is_string($replaced) && $count > 0) {
            return $replaced;
        }

        return $style.$html;
    }

    private function hasPrerenderCards(string $html): bool
    {
        $start = strpos($html, '<!-- prerender:cards -->');
        $end = strpos($html, '<!-- /prerender:cards -->');

        return $start !== false && $end !== false && $end > $start;
    }

    private function replacePrerenderCards(string $html, string $cards): string
    {
        $start = strpos($html, '<!-- prerender:cards -->');
        $end = strpos($html, '<!-- /prerender:cards -->');

        if ($start === false || $end === false || $end <= $start) {
            return $html;
        }

        $insertAt = $start + strlen('<!-- prerender:cards -->');

        return substr($html, 0, $insertAt).$cards.substr($html, $end);
    }

    private function insertSortNav(string $html, string $sortNav): string
    {
        if (str_contains($html, 'id="hub-sort"')) {
            return $html;
        }

        foreach (['id="filters"', 'id="toolbar"', 'id="grid"'] as $needle) {
            $pos = strpos($html, $needle);

            if ($pos === false) {
                continue;
            }

            $open = strrpos(substr($html, 0, $pos), '<');

            if ($open === false) {
                continue;
            }

            if ($needle === 'id="toolbar"') {
                $gt = strpos($html, '>', $pos);

                if ($gt === false) {
                    continue;
                }

                return substr($html, 0, $gt + 1).$sortNav.substr($html, $gt + 1);
            }

            return substr($html, 0, $open).$sortNav.substr($html, $open);
        }

        return $sortNav.$html;
    }

    private function appendSection(string $html, string $sortNav, string $cards): string
    {
        if (str_contains($html, 'data-gamehub-catalog')) {
            return $html;
        }

        $section = '<section id="games" data-gamehub-catalog aria-labelledby="hub-catalog-title">'
            .'<h2 id="hub-catalog-title">Semua Game</h2>'
            .$sortNav
            .'<div class="grid" id="grid">'.$cards.'</div>'
            .'</section>';

        $replaced = preg_replace('/<\/body>/i', $section.'</body>', $html, 1, $count);

        if (is_string($replaced) && $count > 0) {
            return $replaced;
        }

        return $html.$section;
    }

    private function sortUrl(Request $request, string $sort): string
    {
        $query = [];

        foreach ($request->query() as $key => $value) {
            if (! is_string($key) || $key === 'sort' || ! is_scalar($value)) {
                continue;
            }

            $query[$key] = (string) $value;
        }

        $query['sort'] = $sort;

        return '/'.GameHub::path().'/?'.http_build_query($query);
    }

    private static function documentTitle(string $html): string
    {
        if (preg_match('/<title\b[^>]*>(.*?)<\/title>/is', $html, $matches) !== 1) {
            return '';
        }

        return self::plainText($matches[1]);
    }

    private static function metaContent(string $html, string $attribute, string $expected): string
    {
        if (preg_match_all('/<meta\b[^>]*>/i', $html, $tags) < 1) {
            return '';
        }

        foreach ($tags[0] as $tag) {
            $actual = self::attribute($tag, $attribute);

            if ($actual === null || strcasecmp($actual, $expected) !== 0) {
                continue;
            }

            $content = self::attribute($tag, 'content');

            if ($content === null) {
                continue;
            }

            return self::plainText($content);
        }

        return '';
    }

    private static function attribute(string $tag, string $name): ?string
    {
        $pattern = '/\b'.preg_quote($name, '/').'\s*=\s*(["\'])(.*?)\1/i';

        if (preg_match($pattern, $tag, $matches) !== 1) {
            return null;
        }

        return $matches[2];
    }

    private static function absoluteImage(string $url, string $scheme): ?string
    {
        if ($url === '' || str_contains(strtolower($url), 'og-gamehub.jpg')) {
            return null;
        }

        if (str_starts_with($url, '//')) {
            $url = $scheme.':'.$url;
        } elseif (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            $url = $scheme.'://'.GameHub::host().$url;
        }

        if (preg_match('#\Ahttps?://[^\s<>"\']+\z#i', $url) !== 1) {
            return null;
        }

        return $url;
    }

    private static function shortenTitle(string $title): string
    {
        foreach ([' | ', ' · ', ' • '] as $separator) {
            $parts = explode($separator, $title, 2);
            $head = trim($parts[0]);

            if (count($parts) === 2 && mb_strlen($head) >= 2) {
                return $head;
            }
        }

        return $title;
    }

    private static function plainText(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $collapsed = preg_replace('/\s+/u', ' ', $value);

        if (! is_string($collapsed)) {
            $collapsed = preg_replace('/\s+/', ' ', $value);
        }

        return trim(is_string($collapsed) ? $collapsed : $value);
    }

    private static function limit(string $value, int $max): string
    {
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        $cut = mb_substr($value, 0, $max);
        $space = mb_strrpos($cut, ' ');

        if ($space !== false && $space > (int) ($max * 0.6)) {
            $cut = mb_substr($cut, 0, $space);
        }

        return rtrim($cut, " \t,.;:-");
    }

    private static function initials(string $title): string
    {
        $parts = preg_split('/[\s\x{2014}\x{00B7}&:-]+/u', $title) ?: [];
        $letters = [];

        foreach ($parts as $part) {
            if (! is_string($part) || $part === '' || preg_match('/[A-Za-z0-9]/', $part) !== 1) {
                continue;
            }

            $letters[] = mb_strtoupper(mb_substr($part, 0, 1));

            if (count($letters) === 2) {
                break;
            }
        }

        return $letters === [] ? '?' : implode('', $letters);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private static function snapshotSize(mixed $value): array
    {
        if (! is_array($value) || ! isset($value[0], $value[1])) {
            return [640, 400];
        }

        $width = (int) $value[0];
        $height = (int) $value[1];

        if ($width < 1 || $height < 1 || $width > 4000 || $height > 4000) {
            return [640, 400];
        }

        return [$width, $height];
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                continue;
            }

            $item = trim($item);

            if ($item !== '') {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param  array<mixed>  $previous
     */
    private static function language(array $previous): string
    {
        $lang = $previous['lang'] ?? '';

        if (! is_string($lang) || preg_match('/\A[a-z]{2}(?:-[A-Za-z]{2})?\z/', $lang) !== 1) {
            return 'id';
        }

        return $lang;
    }

    /**
     * @param  array<mixed>  $previous
     */
    private static function schemaType(array $previous): string
    {
        $type = $previous['schemaType'] ?? 'VideoGame';

        if (! is_string($type) || preg_match('/\A[A-Za-z]{1,40}\z/', $type) !== 1) {
            return 'VideoGame';
        }

        return $type;
    }
}
