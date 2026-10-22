<?php

namespace Tests\Unit;

use App\Services\SeoInjector;
use Tests\TestCase;

class SeoInjectorTest extends TestCase
{
    public function test_it_injects_canonical_before_head_close_tag(): void
    {
        $html = '<html><head><title>Game</title></head><body>Content</body></html>';

        $result = $this->injector()->inject($html, 'https://game.jadidaring.com/');

        $this->assertStringContainsString('<link rel="canonical" href="https://game.jadidaring.com/">', $result);
        $this->assertMatchesRegularExpression('/<link rel="canonical"[^>]+>[\s\S]*<\/head>/', $result);
    }

    public function test_it_injects_favicon_link_tags_when_absent(): void
    {
        $html = '<html><head><title>Game</title></head><body></body></html>';

        $result = $this->injector()->inject($html, 'https://game.jadidaring.com/');

        $this->assertStringContainsString('<link rel="icon" href="/favicon.ico" sizes="any">', $result);
        $this->assertStringContainsString('<link rel="icon" href="/favicon.svg" type="image/svg+xml">', $result);
        $this->assertStringContainsString('<link rel="apple-touch-icon" href="/apple-touch-icon.png">', $result);
    }

    public function test_it_does_not_inject_canonical_when_already_present(): void
    {
        $html = '<html><head><link rel="canonical" href="https://existing.com/"><title>X</title></head><body></body></html>';

        $result = $this->injector()->inject($html, 'https://game.jadidaring.com/');

        $this->assertStringNotContainsString('https://game.jadidaring.com/', $result);
        $this->assertSame(1, substr_count($result, 'rel="canonical"'));
    }

    public function test_it_does_not_inject_favicon_links_when_already_present(): void
    {
        $html = '<html><head><link rel="icon" href="/custom-icon.png"><title>X</title></head><body></body></html>';

        $result = $this->injector()->inject($html, 'https://game.jadidaring.com/');

        $this->assertStringNotContainsString('/favicon.ico', $result);
        $this->assertSame(1, substr_count($result, 'rel="icon"'));
    }

    public function test_it_injects_og_image_tags_when_provided_and_absent(): void
    {
        $html = '<html><head><title>Hub</title></head><body></body></html>';

        $result = $this->injector()->inject($html, 'https://gamehub.jadidaring.com/', 'https://gamehub.jadidaring.com/og-gamehub.jpg');

        $this->assertStringContainsString('<meta property="og:image" content="https://gamehub.jadidaring.com/og-gamehub.jpg">', $result);
        $this->assertStringContainsString('<meta property="og:image:width" content="1200">', $result);
        $this->assertStringContainsString('<meta property="og:image:height" content="630">', $result);
        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $result);
    }

    public function test_it_does_not_inject_og_image_when_already_present(): void
    {
        $html = '<html><head><meta property="og:image" content="https://existing.com/img.jpg"><title>X</title></head><body></body></html>';

        $result = $this->injector()->inject($html, 'https://gamehub.jadidaring.com/', 'https://gamehub.jadidaring.com/og-gamehub.jpg');

        $this->assertStringNotContainsString('og-gamehub.jpg', $result);
        $this->assertSame(1, substr_count($result, 'og:image"'));
    }

    public function test_it_does_not_inject_og_image_when_no_image_url_given(): void
    {
        $html = '<html><head><title>Game</title></head><body></body></html>';

        $result = $this->injector()->inject($html, 'https://game.jadidaring.com/');

        $this->assertStringNotContainsString('og:image', $result);
    }

    public function test_it_returns_html_unchanged_when_no_head_close_tag(): void
    {
        $html = 'plain text, no html structure';

        $result = $this->injector()->inject($html, 'https://game.jadidaring.com/');

        $this->assertSame($html, $result);
    }

    public function test_it_returns_html_unchanged_when_nothing_to_inject(): void
    {
        $html = '<html><head><link rel="canonical" href="https://existing.com/"><link rel="icon" href="/icon.png"></head><body></body></html>';

        $result = $this->injector()->inject($html, 'https://game.jadidaring.com/');

        $this->assertSame($html, $result);
    }

    private function injector(): SeoInjector
    {
        return new SeoInjector;
    }
}
