<?php

namespace Tests\Unit;

use App\Services\GameHubCatalog;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GameHubCatalogTest extends TestCase
{
    public function test_page_metadata_prefers_og_title_and_drops_the_seo_tail(): void
    {
        $html = <<<'HTML'
        <html><head>
        <title>Bajaj Drift · Game Balap Drift</title>
        <meta property="og:title" content="Bajaj Drift · tahan buat drift">
        <meta name="description" content="Deskripsi dari meta.">
        <meta property="og:description" content="Deskripsi OG yang kalah.">
        </head><body></body></html>
        HTML;

        $meta = GameHubCatalog::pageMetadata($html);

        $this->assertSame('Bajaj Drift', $meta['title']);
        $this->assertSame('Deskripsi dari meta.', $meta['description']);
    }

    public function test_page_metadata_keeps_an_em_dash_title_and_decodes_entities(): void
    {
        $html = '<html><head><title>Jade Room &#x2014; Mahjong, together</title>'
            .'<meta content="Mahjong bersama." property="og:description">'
            .'</head></html>';

        $meta = GameHubCatalog::pageMetadata($html);

        $this->assertSame('Jade Room — Mahjong, together', $meta['title']);
        $this->assertSame('Mahjong bersama.', $meta['description']);
        $this->assertSame('', $meta['image']);
    }

    public function test_page_metadata_ignores_the_hub_jpeg_and_absolutizes_a_cover(): void
    {
        $hub = '<html><head><title>Kopi Senja</title>'
            .'<meta property="og:image" content="https://www.jadidaring.test/og-gamehub.jpg">'
            .'<meta name="twitter:image" content="/covers/kopi.webp">'
            .'</head></html>';

        $meta = GameHubCatalog::pageMetadata($hub, 'https');

        $this->assertSame('https://www.jadidaring.test/covers/kopi.webp', $meta['image']);
    }

    #[DataProvider('sorts')]
    public function test_unknown_sort_falls_back_to_newest(mixed $sort, string $expected): void
    {
        $this->assertSame($expected, GameHubCatalog::normalizeSort($sort));
    }

    /**
     * @return array<string, array{0: mixed, 1: string}>
     */
    public static function sorts(): array
    {
        return [
            'missing' => [null, GameHubCatalog::SORT_NEW],
            'array' => [['views'], GameHubCatalog::SORT_NEW],
            'newest' => ['new', GameHubCatalog::SORT_NEW],
            'views' => ['VIEWS', GameHubCatalog::SORT_VIEWS],
            'other' => ['custom', GameHubCatalog::SORT_NEW],
        ];
    }
}
