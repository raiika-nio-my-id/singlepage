<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * One-time mark of profiles that belong on GameHub.
 *
 * is_game defaults to false, so a new company site stays out of the catalog.
 * This list is the deploy backfill for games that were already published.
 * The match is the exact primary subdomain, lowercase.
 *
 * Three live pages were not in the original name list. Their public pages are
 * playable browser games (genre, play tags, and a game description), so they
 * are tagged too: hantukampung, poconglari, rumahkontrakan.
 *
 * Left untagged: gamehub, showcase, demo, demo1–demo12, and every other
 * company or client profile. The spaced names use the live slugs
 * cukurkekinian, mudikmacet, and pawanghujan.
 */
class GameHubGameBackfill
{
    /**
     * @var list<string>
     */
    public const SUBDOMAINS = [
        'bajajdrift',
        'benteng',
        'boxing',
        'bumerangsakti',
        'clubgame',
        'coratcoret',
        'cukurkekinian',
        'dapurpadang',
        'dapurpadang3d',
        'drag201',
        'drawquarium',
        'duelular',
        'explodingkitten',
        'gambletogether',
        'gamelanhero',
        'gasingarena',
        'gelembung',
        'hantukampung',
        'idle-game',
        'ikanrakus',
        'kehidupankonoha',
        'kopisenja',
        'langitbersama',
        'lanjutkata',
        'mahjong',
        'mahjong-master',
        'malampendakian',
        'mandarinharian',
        'mlbbpicker',
        'monyetuluwatu',
        'mudikmacet',
        'nadapiano',
        'ogohogoharak',
        'pawanghujan',
        'pedangsihir',
        'penaltitimnas',
        'poconglari',
        'rebutbendera',
        'rumahkontrakan',
        'sambungcoret',
        'skalateman',
        'spiderman',
        'tamiyalintasan',
        'tiltingball',
        'ttskilat',
        'ulartangga',
        'whoosh',
    ];

    public static function apply(): void
    {
        DB::table('company_profiles')
            ->whereNull('is_game')
            ->update(['is_game' => false]);

        DB::table('company_profiles')
            ->whereIn('subdomain', self::SUBDOMAINS)
            ->update(['is_game' => true]);
    }
}
