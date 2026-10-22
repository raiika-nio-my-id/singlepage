<?php

namespace App\Services;

class SeoInjector
{
    /**
     * Inject SEO head tags (canonical, favicon links, optional og:image) into raw HTML.
     *
     * Only injects tags that are not already present so existing meta is never overwritten.
     * Inserts before </head>; if no </head> is found the HTML is returned unchanged.
     */
    public function inject(string $html, string $canonicalUrl, ?string $ogImageUrl = null): string
    {
        $tags = '';

        if (! $this->hasCanonical($html)) {
            $tags .= '<link rel="canonical" href="'.e($canonicalUrl).'">'."\n";
        }

        if (! $this->hasFaviconLink($html)) {
            $tags .= '<link rel="icon" href="/favicon.ico" sizes="any">'."\n";
            $tags .= '<link rel="icon" href="/favicon.svg" type="image/svg+xml">'."\n";
            $tags .= '<link rel="apple-touch-icon" href="/apple-touch-icon.png">'."\n";
        }

        if ($ogImageUrl !== null && ! $this->hasOgImage($html)) {
            $tags .= '<meta property="og:image" content="'.e($ogImageUrl).'">'."\n";
            $tags .= '<meta property="og:image:width" content="1200">'."\n";
            $tags .= '<meta property="og:image:height" content="630">'."\n";
            $tags .= '<meta name="twitter:card" content="summary_large_image">'."\n";
            $tags .= '<meta name="twitter:image" content="'.e($ogImageUrl).'">'."\n";
        }

        if ($tags === '') {
            return $html;
        }

        $replaced = preg_replace('/<\/head>/i', $tags.'</head>', $html, 1, $count);

        if (is_string($replaced) && $count > 0) {
            return $replaced;
        }

        return $html;
    }

    private function hasCanonical(string $html): bool
    {
        return (bool) preg_match('/rel=["\']canonical["\']/', $html);
    }

    private function hasFaviconLink(string $html): bool
    {
        return (bool) preg_match('/rel=["\']icon["\']/', $html);
    }

    private function hasOgImage(string $html): bool
    {
        return (bool) preg_match('/property=["\']og:image["\']/', $html);
    }
}
