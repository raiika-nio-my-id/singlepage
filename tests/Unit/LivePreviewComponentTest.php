<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LivePreviewComponentTest extends TestCase
{
    public function test_preview_links_to_the_public_site_and_offers_viewport_modes(): void
    {
        $html = Blade::render('<x-live-preview :site-subdomain="$siteSubdomain" />', [
            'siteSubdomain' => 'acme',
        ]);

        $this->assertStringContainsString('Visit website', $html);
        $this->assertStringContainsString('href="http://acme.jadidaring.test"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
        $this->assertStringContainsString('>Desktop</button>', $html);
        $this->assertStringContainsString('>Mobile</button>', $html);
        $this->assertStringContainsString('data-preview-mode-button="desktop"', $html);
        $this->assertStringContainsString('data-preview-mode-button="mobile"', $html);
        $this->assertStringNotContainsString('<svg', $html);
        $this->assertStringContainsString('data-preview-stage', $html);
        $this->assertStringContainsString('data-desktop-width="1280"', $html);
        $this->assertStringContainsString('data-desktop-height="800"', $html);
        $this->assertStringContainsString('data-mobile-width="390"', $html);
        $this->assertStringContainsString('data-mobile-height="844"', $html);
        $this->assertStringContainsString('preview-device', $html);
        $this->assertStringContainsString('preview-device-stand', $html);
        $this->assertStringNotContainsString('preview-device-island', $html);
    }

    public function test_preview_visit_link_is_disabled_without_a_subdomain(): void
    {
        $html = Blade::render('<x-live-preview />');

        $this->assertStringContainsString('Visit website', $html);
        $this->assertStringContainsString('aria-disabled="true"', $html);
        $this->assertStringNotContainsString('href="http://', $html);
    }

    public function test_mobile_preview_hides_scrollbars_and_enables_drag_scrolling(): void
    {
        $script = File::get(public_path('js/live-preview.js'));
        $css = File::get(public_path('css/admin.css'));

        $this->assertStringContainsString('data-cms-preview-ui', $script);
        $this->assertStringContainsString('scrollbar-width: none', $script);
        $this->assertStringContainsString('dragstart', $script);
        $this->assertStringContainsString('-webkit-user-drag: none', $script);
        $this->assertStringContainsString('cms-preview-dragging', $script);
        $this->assertStringContainsString("behavior: 'instant'", $script);
        $this->assertStringNotContainsString('requestAnimationFrame(glide)', $script);
        $this->assertStringContainsString("setAttribute('sandbox', 'allow-scripts')", $script);
        $this->assertStringNotContainsString('allow-same-origin', $script);
        $this->assertStringContainsString("type: 'restore-scroll'", $script);
        $this->assertStringContainsString("type: 'scroll'", $script);
        $this->assertStringContainsString('postMessage', $script);
        $this->assertStringContainsString('[data-preview-mode="mobile"] iframe.preview', $css);
        $this->assertStringContainsString('scrollbar-width: none', $css);
        $this->assertStringContainsString('.preview-modes {', $css);
        $this->assertStringContainsString('height: 2.35rem', $css);
    }
}
