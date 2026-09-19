@extends('layouts.admin')

@section('title', 'New client')
@section('main_class', 'wide')

@section('content')
    <h1>New client</h1>
    <p class="muted">Creates one client account and their unique company subdomain. Client HTML is never rendered in this admin page.</p>

    @if ($errors->any())
        <ul class="errors">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <div class="editor-split">
        <form class="card editor-pane" method="POST" action="{{ route('admin.clients.store') }}">
            @csrf
            <label for="name">Client name</label>
            <input id="name" name="name" value="{{ old('name') }}" required>

            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required>

            <label for="password">Password</label>
            <input id="password" type="password" name="password" required autocomplete="new-password">

            <label for="subdomain">Subdomain</label>
            <input id="subdomain" name="subdomain" value="{{ old('subdomain') }}" maxlength="63" required>

            <label for="expires_at">Expired</label>
            <input id="expires_at" type="date" name="expires_at" value="{{ old('expires_at', $defaultExpiresAt) }}" required>
            <p class="muted">Paket 1 tahun (Rp 50.000). Ubah tanggal jika perlu.</p>

            <label for="html">Published HTML</label>
            <textarea id="html" class="html-editor" name="html" data-live-preview-source spellcheck="false" required>{{ old('html', '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Company</title></head><body><h1>Company</h1></body></html>') }}</textarea>

            <label>
                <input type="checkbox" name="status" value="published" @checked(old('status') === 'published')>
                Published
            </label>

            <p><button type="submit">Create client</button></p>
        </form>

        <section class="card editor-pane preview-panel" aria-label="Live preview">
            <h2>Live preview</h2>
            <div data-live-preview-mount>
                <iframe class="preview" sandbox="allow-scripts" title="Sandboxed company preview" srcdoc="{{ old('html', '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Company</title></head><body><h1>Company</h1></body></html>') }}"></iframe>
            </div>
        </section>
    </div>
@endsection
