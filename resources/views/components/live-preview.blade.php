@props([
    'siteSubdomain' => '',
])
@php
    $siteUrl = filled($siteSubdomain) ? \App\Models\CompanyProfile::publicUrlFor($siteSubdomain) : null;
@endphp
<section {{ $attributes->class(['card', 'editor-pane', 'preview-panel']) }} aria-label="Live preview">
    <div class="preview-header">
        <h2>Live preview</h2>
        <div class="preview-header-actions">
            <div class="preview-modes" role="group" aria-label="Preview size">
                <button type="button" data-preview-mode-button="desktop" aria-pressed="true">Desktop</button>
                <button type="button" data-preview-mode-button="mobile" aria-pressed="false">Mobile</button>
            </div>
            <a
                class="button button-quiet"
                data-site-link
                data-site-url-template="{{ \App\Models\CompanyProfile::publicUrlFor('{subdomain}') }}"
                @if ($siteUrl)
                    href="{{ $siteUrl }}"
                @else
                    aria-disabled="true"
                @endif
                target="_blank"
                rel="noopener noreferrer"
            >Visit website</a>
        </div>
    </div>
    <div
        class="preview-stage"
        data-preview-stage
        data-preview-mode="desktop"
        data-desktop-width="1280"
        data-desktop-height="800"
        data-mobile-width="390"
        data-mobile-height="844"
    >
        <div class="preview-device" data-preview-device>
            <span class="preview-device-btn preview-device-btn-silent" aria-hidden="true"></span>
            <span class="preview-device-btn preview-device-btn-vol-up" aria-hidden="true"></span>
            <span class="preview-device-btn preview-device-btn-vol-down" aria-hidden="true"></span>
            <span class="preview-device-btn preview-device-btn-power" aria-hidden="true"></span>
            <div class="preview-device-shell">
                <span class="preview-device-webcam" aria-hidden="true"></span>
                <div class="preview-device-screen" data-live-preview-mount>
                    {{ $slot }}
                </div>
                <span class="preview-device-home" aria-hidden="true"></span>
            </div>
            <div class="preview-device-stand" aria-hidden="true">
                <span class="preview-device-neck"></span>
                <span class="preview-device-base"></span>
            </div>
        </div>
    </div>
</section>
