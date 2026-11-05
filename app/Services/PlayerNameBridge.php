<?php

namespace App\Services;

/**
 * Injects the shared player-name bridge into a tenant page.
 *
 * Game HTML is stored per profile and already contains the name gate
 * (sessionStorage key jdng.<slug>, plus that game's own localStorage key).
 * The bridge is served in front of that HTML so every game on www reads and
 * writes one localStorage key, jdng.name, without a rewrite of each profile.
 */
class PlayerNameBridge
{
    public const MARKER = 'data-jdng-name-bridge';

    public const STORAGE_KEY = 'jdng.name';

    public function inject(string $html): string
    {
        if (str_contains($html, self::MARKER) || ! $this->asksForPlayerName($html)) {
            return $html;
        }

        $script = $this->script();

        if ($script === '') {
            return $html;
        }

        $tag = '<script '.self::MARKER.">\n".$script."\n</script>";
        $replaced = preg_replace('/<head\b[^>]*>/i', '$0'.$tag, $html, 1, $count);

        if (is_string($replaced) && $count > 0) {
            return $replaced;
        }

        return $tag.$html;
    }

    private function script(): string
    {
        $path = public_path('js/player-name.js');

        if (! is_file($path)) {
            return '';
        }

        $script = file_get_contents($path);

        if (! is_string($script) || str_contains($script, '</script')) {
            return '';
        }

        return trim($script);
    }

    /**
     * Only pages that already ask for a player name receive the bridge.
     * The shared gate marks itself jd-namegate / JDNG_CFG. Drawquarium and
     * Langit Bersama use their own gate and a jdng.<slug> session key.
     */
    private function asksForPlayerName(string $html): bool
    {
        return str_contains($html, 'jd-namegate')
            || str_contains($html, 'JDNG_CFG')
            || str_contains($html, 'jdng.drawquarium')
            || str_contains($html, 'jdng.langitbersama');
    }
}
