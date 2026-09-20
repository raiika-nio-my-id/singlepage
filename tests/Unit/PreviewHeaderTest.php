<?php

namespace Tests\Unit;

use Tests\TestCase;

class PreviewHeaderTest extends TestCase
{
    public function test_preview_header_links_to_the_public_site(): void
    {
        $html = view('admin.partials.preview-header', [
            'siteSubdomain' => 'acme',
        ])->render();

        $this->assertStringContainsString('Visit website', $html);
        $this->assertStringContainsString('href="http://acme.jadidaring.test"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
    }

    public function test_preview_header_is_disabled_without_a_subdomain(): void
    {
        $html = view('admin.partials.preview-header')->render();

        $this->assertStringContainsString('Visit website', $html);
        $this->assertStringContainsString('aria-disabled="true"', $html);
        $this->assertStringNotContainsString('href="http://', $html);
    }
}
