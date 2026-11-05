<?php

namespace App\Services;

use App\Models\CompanyProfile;
use Illuminate\Support\Facades\Schema;

/**
 * The public website-demo page at /showcase/ on the apex and www hosts.
 *
 * A live company profile whose subdomain is "showcase" supplies the document
 * when one exists, so demo titles edited in admin stay on the page. Otherwise
 * the copy shipped in resources/marketing/showcase.html is used. Either source
 * loses the Unique tab before it is sent, and the header uses the homepage mark.
 */
class ShowcasePage
{
    public static function html(): string
    {
        return JaRingMark::apply(self::withoutUniqueTab(self::source()));
    }

    public static function withoutUniqueTab(string $html): string
    {
        $html = preg_replace(
            '/<button\b[^>]*\bdata-tab=["\']customs["\'][^>]*>.*?<\/button>/is',
            '',
            $html,
        ) ?? $html;

        $html = preg_replace(
            '/<div\b[^>]*\bclass=["\']showcase-tabs["\'][^>]*>.*?<\/div>/is',
            '',
            $html,
            1,
        ) ?? $html;

        $html = preg_replace(
            '/<div\b[^>]*\bid=["\']customsPanel["\'][^>]*>.*?<\/div>\s*<\/div>/is',
            '',
            $html,
            1,
        ) ?? $html;

        $html = preg_replace_callback(
            '/<div\b[^>]*\bid=["\']uniqueWebPanel["\'][^>]*>/is',
            static function (array $match): string {
                return preg_replace('/\s(?:role|aria-labelledby)=["\'][^"\']*["\']/', '', $match[0]) ?? $match[0];
            },
            $html,
            1,
        ) ?? $html;

        $html = preg_replace(
            '/const customGrid = document\.getElementById\("customGrid"\);\s*customs\.forEach\(\(\[url, title\]\) => \{\s*customGrid\.appendChild\(createCustomCard\(url, title\)\);\s*\}\);/s',
            '',
            $html,
            1,
        ) ?? $html;

        return preg_replace(
            '/\/\* =+\s*SHOWCASE TABS \+ ACTIVE TAB COUNTER\s*=+\s*\*\/.*?updateShowcaseCount\("unique-web"\);/s',
            'if (typeof showcaseCount !== "undefined" && typeof TOTAL_DEMOS !== "undefined") { showcaseCount.textContent = `${String(TOTAL_DEMOS).padStart(2, "0")} Websites`; }',
            $html,
            1,
        ) ?? $html;
    }

    private static function source(): string
    {
        $profile = null;

        if (Schema::hasTable('company_profiles')) {
            $profile = CompanyProfile::query()->live()->where('subdomain', 'showcase')->first();
        }

        if ($profile instanceof CompanyProfile && trim($profile->html) !== '') {
            return $profile->html;
        }

        $path = resource_path('marketing/showcase.html');
        $file = is_file($path) ? file_get_contents($path) : false;

        return is_string($file) ? $file : '';
    }
}
