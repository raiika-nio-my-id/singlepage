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
    <div class="page-heading">
        <div>
            <h1>{{ $workspace ? 'Your site' : 'Edit company profile' }}</h1>
            @unless ($workspace)
                <p class="muted lede">Client HTML is previewed in a sandboxed iframe. It is never rendered in this admin document.</p>
            @endunless
        </div>
        @unless ($workspace)
            @if (auth()->user()->isAdmin())
                <a class="button button-quiet button-small" href="{{ route('admin.clients.analytics', $companyProfile) }}">
                    <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zm6-4a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zm6-3a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/>
                    </svg>
                    View analytics
                </a>
            @endif
        @endunless
    </div>

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
        <form class="card editor-pane" method="POST" action="{{ $updateUrl }}" data-editor-form>
            @csrf
            @method('PUT')

            <label for="subdomain">{{ $workspace ? 'Your web address' : 'Subdomain' }}</label>
            @can('updateSubdomain', $companyProfile)
                <input id="subdomain" type="text" name="subdomain" value="{{ old('subdomain', $companyProfile->subdomain) }}" maxlength="63" autocapitalize="off" spellcheck="false" required>
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
                    <input id="name" type="text" name="name" value="{{ old('name', $companyProfile->user?->name) }}" autocomplete="name" required>

                    <label for="email">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email', $companyProfile->user?->email) }}" required autocomplete="username">

                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" autocomplete="new-password">
                    <p class="muted">Leave blank to keep the current password.</p>
                </fieldset>
            @endcan

            <label for="html">HTML <span class="unsaved-flag" data-unsaved-flag hidden role="status">Unsaved</span></label>
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

            <p class="editor-save-row">
                <button type="submit">Save</button>
                <span class="unsaved-flag" data-unsaved-flag hidden role="status">Unsaved changes · Ctrl+S</span>
            </p>
        </form>

        @unless ($workspace)
            @if (auth()->user()->isAdmin())
                <div class="card alias-panel">
                    <h2>Alias subdomains <span class="badge badge-draft">admin only</span></h2>
                    <p class="muted">Each alias redirects (301) to the primary subdomain, keeping one canonical URL.</p>

                    @if ($errors->hasBag('aliases') || $errors->has('alias'))
                        <ul class="errors">
                            @foreach ($errors->get('alias') as $msg)
                                <li>{{ $msg }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($companyProfile->aliases->isEmpty())
                        <p class="muted">No aliases yet.</p>
                    @else
                        <ul class="alias-list">
                            @foreach ($companyProfile->aliases as $alias)
                                <li class="alias-item">
                                    <span>{{ $alias->subdomain }}.{{ config('singlepage.base_domain') }}</span>
                                    <form method="POST" action="{{ route('admin.clients.aliases.destroy', [$companyProfile, $alias]) }}" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="button button-quiet button-small">Remove</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <form method="POST" action="{{ route('admin.clients.aliases.store', $companyProfile) }}" class="alias-add-form">
                        @csrf
                        <input type="text" name="alias" placeholder="alias-subdomain" maxlength="63" autocapitalize="off" spellcheck="false" aria-label="New alias subdomain">
                        <span class="alias-domain-hint">.{{ config('singlepage.base_domain') }}</span>
                        <button type="submit" class="button button-small">Add alias</button>
                    </form>
                </div>
            @endif
        @endunless

        <x-live-preview :site-subdomain="old('subdomain', $companyProfile->subdomain)">
            <iframe class="preview" sandbox="allow-scripts" title="Sandboxed company preview" srcdoc="{{ old('html', $companyProfile->html) }}"></iframe>
        </x-live-preview>
    </div>
@endsection
