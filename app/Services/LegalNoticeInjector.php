<?php

namespace App\Services;

class LegalNoticeInjector
{
    public const MARKER = 'data-jadidaring-legal-notice';

    public function inject(string $html, string $termsUrl): string
    {
        if (str_contains($html, self::MARKER)) {
            return $html;
        }

        $notice = view('tenant.legal-notice', [
            'termsUrl' => $termsUrl,
            'platformName' => (string) config('singlepage.platform_name', 'Jadidaring'),
            'marketingUrl' => (string) config('singlepage.marketing_url', 'https://jadidaring.com/'),
        ])->render();

        $replaced = preg_replace('/<\/body>/i', $notice.'</body>', $html, 1, $count);

        if (is_string($replaced) && $count > 0) {
            return $replaced;
        }

        $replaced = preg_replace('/<\/html>/i', $notice.'</html>', $html, 1, $count);

        if (is_string($replaced) && $count > 0) {
            return $replaced;
        }

        return $html.$notice;
    }
}
