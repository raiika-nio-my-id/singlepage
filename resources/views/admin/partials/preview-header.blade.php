@php
    $siteSubdomain = $siteSubdomain ?? '';
    $siteUrl = filled($siteSubdomain) ? \App\Models\CompanyProfile::publicUrlFor($siteSubdomain) : null;
@endphp
<div class="preview-header">
    <h2>Live preview</h2>
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
