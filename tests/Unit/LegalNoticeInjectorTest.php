<?php

namespace Tests\Unit;

use App\Services\LegalNoticeInjector;
use Tests\TestCase;

class LegalNoticeInjectorTest extends TestCase
{
    public function test_it_injects_the_notice_before_the_body_close_tag(): void
    {
        $html = $this->injector()->inject(
            '<html><body>Acme</body></html>',
            'http://acme.jadidaring.test/syarat-ketentuan',
        );

        $this->assertStringContainsString('Acme', $html);
        $this->assertStringContainsString('data-jadidaring-legal-notice', $html);
        $this->assertStringContainsString('http://acme.jadidaring.test/syarat-ketentuan', $html);
        $this->assertStringContainsString('Syarat &amp; Ketentuan', $html);
        $this->assertStringContainsString('https://jadidaring.com/', $html);
        $this->assertStringContainsString('>Jadidaring</a>', $html);
        $this->assertStringNotContainsString('bukan tanggung jawab', $html);
        $this->assertMatchesRegularExpression('/data-jadidaring-legal-notice[\s\S]*<\/body>/', $html);
    }

    public function test_it_appends_the_notice_when_the_document_has_no_body_tag(): void
    {
        $html = $this->injector()->inject('just text', 'http://acme.jadidaring.test/syarat-ketentuan');

        $this->assertStringStartsWith('just text', $html);
        $this->assertStringContainsString('data-jadidaring-legal-notice', $html);
    }

    public function test_it_does_not_inject_twice(): void
    {
        $injector = $this->injector();
        $once = $injector->inject('<html><body>Acme</body></html>', 'http://example.test/syarat-ketentuan');
        $twice = $injector->inject($once, 'http://example.test/syarat-ketentuan');

        $this->assertSame(1, substr_count($twice, 'data-jadidaring-legal-notice'));
    }

    private function injector(): LegalNoticeInjector
    {
        return new LegalNoticeInjector;
    }
}
