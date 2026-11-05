<?php

namespace App\Services;

/**
 * The homepage JaRing monogram, reused in other public chrome.
 *
 * The path lives in public/marketing/images/logo-mark.svg. Callers insert
 * that same drawing into a header brand link and point the favicon at it.
 */
class JaRingMark
{
    public static function svg(): string
    {
        $path = public_path('marketing/images/logo-mark.svg');
        $svg = is_file($path) ? file_get_contents($path) : false;

        if (! is_string($svg) || trim($svg) === '') {
            return '';
        }

        $svg = preg_replace('/\s+/', ' ', trim($svg)) ?? trim($svg);

        return preg_replace(
            '/<svg\b/',
            '<svg class="brand__mark" width="42" height="36"',
            $svg,
            1,
        ) ?? $svg;
    }

    /**
     * Insert the monogram into the first brand link and retarget favicons.
     */
    public static function apply(string $html): string
    {
        $html = self::insertMark($html);

        return self::retargetIcons($html);
    }

    private static function insertMark(string $html): string
    {
        if (str_contains($html, 'class="brand__mark"') || str_contains($html, "class='brand__mark'")) {
            return $html;
        }

        $svg = self::svg();

        if ($svg === '') {
            return $html;
        }

        $replaced = preg_replace_callback(
            '/<a\b[^>]*\bclass=["\'][^"\']*\bbrand\b[^"\']*["\'][^>]*>/i',
            static function (array $match) use ($svg): string {
                return $match[0].$svg;
            },
            $html,
            1,
            $count,
        );

        if (! is_string($replaced) || $count < 1) {
            return $html;
        }

        $style = '<style id="jaring-mark">.brand__mark{height:36px;width:auto;max-width:none;display:block;flex:0 0 auto}.brand{align-items:center}</style>';

        if (! str_contains($replaced, 'id="jaring-mark"')) {
            $withStyle = preg_replace('/<\/head>/i', $style.'</head>', $replaced, 1, $styled);

            if (is_string($withStyle) && $styled > 0) {
                return $withStyle;
            }
        }

        return $replaced;
    }

    private static function retargetIcons(string $html): string
    {
        $icon = '/marketing/images/logo-mark.svg';
        $replaced = preg_replace_callback(
            '/<link\b[^>]*\brel=["\'](?:shortcut icon|icon)["\'][^>]*>/i',
            static function (array $match) use ($icon): string {
                $tag = $match[0];

                if (str_contains($tag, 'logo-mark.svg')) {
                    return $tag;
                }

                if (preg_match('/\bhref=["\'][^"\']*["\']/', $tag) === 1) {
                    $tag = preg_replace('/\bhref=["\'][^"\']*["\']/', 'href="'.$icon.'"', $tag, 1) ?? $tag;
                }

                if (preg_match('/\btype=["\'][^"\']*["\']/', $tag) === 1) {
                    return preg_replace('/\btype=["\'][^"\']*["\']/', 'type="image/svg+xml"', $tag, 1) ?? $tag;
                }

                return str_replace('<link', '<link type="image/svg+xml"', $tag);
            },
            $html,
            -1,
            $count,
        );

        if (is_string($replaced) && $count > 0) {
            return $replaced;
        }

        $inserted = preg_replace(
            '/<\/head>/i',
            '<link rel="icon" href="'.$icon.'" type="image/svg+xml"></head>',
            $html,
            1,
            $added,
        );

        if (is_string($inserted) && $added > 0) {
            return $inserted;
        }

        return $html;
    }
}
