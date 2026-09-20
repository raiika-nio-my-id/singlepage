@extends('layouts.admin')

@section('title', 'New client')
@section('main_class', 'wide')

@section('content')
    <div class="page-heading">
        <div>
            <h1>New client</h1>
            <p class="muted lede">Creates one client account and their unique company subdomain. Client HTML is never rendered in this admin page.</p>
        </div>
    </div>

    @if ($errors->any())
        <ul class="errors">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <div class="editor-split">
        <form class="card editor-pane" method="POST" action="{{ route('admin.clients.store') }}" data-editor-form>
            @csrf
            <label for="name">Client name</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" autocomplete="name" required>

            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required>

            <label for="password">Password</label>
            <input id="password" type="password" name="password" required autocomplete="new-password">

            <label for="subdomain">Subdomain</label>
            <input id="subdomain" type="text" name="subdomain" value="{{ old('subdomain') }}" maxlength="63" autocapitalize="off" spellcheck="false" required>

            <label for="expires_at">Expired</label>
            <input id="expires_at" type="date" name="expires_at" value="{{ old('expires_at', $defaultExpiresAt) }}" required>
            <p class="muted">Paket 1 tahun (Rp 50.000). Ubah tanggal jika perlu.</p>

            <label for="html">Published HTML <span class="unsaved-flag" data-unsaved-flag hidden role="status">Unsaved</span></label>
            <textarea id="html" class="html-editor" name="html" data-live-preview-source spellcheck="false" required>{{ old('html', '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Company</title></head><body><h1>Company</h1></body></html>') }}</textarea>
            <p class="muted">A discreet Terms and Jadidaring link is attached on the public site unless you hide it below.</p>

            <label>
                <input type="checkbox" name="status" value="published" @checked(old('status') === 'published')>
                Published
            </label>

            <label>
                <input type="checkbox" name="hide_footer" value="1" @checked((bool) old('hide_footer'))>
                Hide platform footer
            </label>
            <p class="muted">Paid extra. Removes the Jadidaring credit and Terms link from the public website.</p>

            <p class="editor-save-row">
                <button type="submit">Create client</button>
                <span class="unsaved-flag" data-unsaved-flag hidden role="status">Unsaved changes · Ctrl+S</span>
            </p>
        </form>

        <x-live-preview :site-subdomain="old('subdomain')">
            <iframe class="preview" sandbox="allow-scripts" title="Sandboxed company preview" srcdoc="{{ old('html', '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Company</title></head><body><h1>Company</h1></body></html>') }}"></iframe>
        </x-live-preview>
    </div>
@endsection
