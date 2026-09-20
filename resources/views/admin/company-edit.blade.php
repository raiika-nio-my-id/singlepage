@php
    $workspace = $workspace ?? false;
    $updateUrl = $workspace
        ? route('client.workspace.update')
        : route('admin.clients.update', $companyProfile);
@endphp

@extends('layouts.admin')

@section('title', $workspace ? 'Your site' : 'Edit '.$companyProfile->subdomain)
@section('main_class', 'wide')

@section('content')
    <h1>{{ $workspace ? 'Your site' : 'Edit company profile' }}</h1>
    @unless ($workspace)
        <p class="muted">Client HTML is previewed in a sandboxed iframe. It is never rendered in this admin document.</p>
    @endunless

    @if ($companyProfile->isExpired())
        <div class="errors" role="status">This site has expired and is not publicly visible.</div>
    @endif

    @if ($errors->any())
        <ul class="errors">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <div class="editor-split">
        <form class="card editor-pane" method="POST" action="{{ $updateUrl }}">
            @csrf
            @method('PUT')

            <label for="subdomain">{{ $workspace ? 'Your web address' : 'Subdomain' }}</label>
            @can('updateSubdomain', $companyProfile)
                <input id="subdomain" name="subdomain" value="{{ old('subdomain', $companyProfile->subdomain) }}" maxlength="63" required>
            @else
                <p class="readonly-field" id="subdomain">{{ $companyProfile->subdomain }}.{{ config('singlepage.base_domain') }}</p>
            @endcan

            <label for="expires_at">Expired</label>
            @can('updateExpiration', $companyProfile)
                <input id="expires_at" type="date" name="expires_at" value="{{ old('expires_at', $companyProfile->expires_at?->toDateString()) }}" required>
                <p class="muted">Default paket 1 tahun setelah pembayaran.</p>
            @else
                <p class="readonly-field">
                    @if ($companyProfile->expires_at)
                        {{ $companyProfile->expires_at->translatedFormat('d M Y') }}
                        @if ($companyProfile->isExpired())
                            — expired
                        @endif
                    @else
                        Not set
                    @endif
                </p>
            @endcan

            @can('updateCredentials', $companyProfile)
                <fieldset>
                    <legend>Client account</legend>
                    <label for="name">Name</label>
                    <input id="name" name="name" value="{{ old('name', $companyProfile->user?->name) }}" required>

                    <label for="email">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email', $companyProfile->user?->email) }}" required autocomplete="username">

                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" autocomplete="new-password">
                    <p class="muted">Leave blank to keep the current password.</p>
                </fieldset>
            @endcan

            <label for="html">HTML</label>
            <textarea id="html" class="html-editor" name="html" data-live-preview-source spellcheck="false" required>{{ old('html', $companyProfile->html) }}</textarea>
            @can('updateFooterVisibility', $companyProfile)
                <p class="muted">A discreet Terms and Jadidaring link is attached on the public site unless you hide it below.</p>
            @else
                @unless ($companyProfile->hide_footer)
                    <p class="muted">A discreet Terms link is attached automatically on the public website.</p>
                @endunless
            @endcan

            <label>
                <input type="checkbox" name="status" value="published" @checked(old('status', $companyProfile->status->value) === 'published')>
                Published
            </label>

            @can('updateFooterVisibility', $companyProfile)
                <label>
                    <input type="checkbox" name="hide_footer" value="1" @checked((bool) old('hide_footer', $companyProfile->hide_footer))>
                    Hide platform footer
                </label>
                <p class="muted">Paid extra. Removes the Jadidaring credit and Terms link from the public website.</p>
            @endcan

            <p><button type="submit">Save</button></p>
        </form>

        <section class="card editor-pane preview-panel" aria-label="Live preview">
            @include('admin.partials.preview-header', [
                'siteSubdomain' => old('subdomain', $companyProfile->subdomain),
            ])
            <div data-live-preview-mount>
                <iframe class="preview" sandbox="allow-scripts" title="Sandboxed company preview" srcdoc="{{ old('html', $companyProfile->html) }}"></iframe>
            </div>
        </section>
    </div>
@endsection
