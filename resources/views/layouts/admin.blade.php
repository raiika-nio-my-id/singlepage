<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — {{ config('app.name') }}</title>
    <style>
        :root {
            --ink: #1c1917;
            --muted: #57534e;
            --line: #e7e5e4;
            --paper: #f5f1eb;
            --card: #fffcf7;
            --accent: #0f3d3e;
            --accent-hover: #0b2f30;
            --danger: #9f1239;
            --ok: #166534;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Iowan Old Style", "Palatino Linotype", Palatino, Georgia, serif;
            color: var(--ink);
            background: var(--paper);
            line-height: 1.5;
        }
        a { color: var(--accent); }
        header.app-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--line);
            background: var(--card);
        }
        header .brand { font-weight: 700; letter-spacing: .02em; text-decoration: none; color: var(--ink); }
        nav { display: flex; gap: 1rem; align-items: center; font-family: ui-sans-serif, system-ui, sans-serif; font-size: .95rem; }
        nav a { text-decoration: none; color: var(--muted); }
        nav a[aria-current="page"], nav a:hover { color: var(--ink); }
        main { max-width: 1100px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
        main.wide { max-width: 92rem; }
        .editor-split {
            display: grid;
            grid-template-columns: minmax(22rem, 1fr) minmax(24rem, 1fr);
            gap: 1.25rem;
            align-items: start;
        }
        @media (max-width: 960px) {
            .editor-split { grid-template-columns: 1fr; }
        }
        .editor-pane { min-width: 0; }
        .editor-pane textarea.html-editor { min-height: calc(100vh - 22rem); }
        .preview-panel { position: sticky; top: 1rem; }
        .preview-panel h2 { margin: 0 0 .6rem; font-size: 1.15rem; }
        iframe.preview { width: 100%; height: calc(100vh - 10rem); min-height: 28rem; border: 1px solid var(--line); background: #fff; margin: 0; }
        .readonly-field {
            font-family: ui-sans-serif, system-ui, sans-serif;
            padding: .6rem .7rem;
            border: 1px dashed var(--line);
            background: #fafaf9;
        }
        fieldset {
            border: 1px solid var(--line);
            margin: 1.25rem 0 0;
            padding: 0 1rem 1rem;
        }
        legend { padding: 0 .35rem; font-family: ui-sans-serif, system-ui, sans-serif; font-size: .85rem; }
        h1 { font-size: 2rem; margin: 0 0 1rem; }
        .flash {
            background: #ecfdf3;
            color: var(--ok);
            border: 1px solid #bbf7d0;
            padding: .75rem 1rem;
            margin-bottom: 1.25rem;
        }
        .errors { color: var(--danger); margin: 0 0 1rem; padding: 0; }
        .card {
            background: var(--card);
            border: 1px solid var(--line);
            padding: 1.25rem;
        }
        label { display: block; font-family: ui-sans-serif, system-ui, sans-serif; font-size: .85rem; margin: 1rem 0 .35rem; }
        input[type="text"], input[type="email"], input[type="password"], textarea, select {
            width: 100%;
            font: inherit;
            padding: .6rem .7rem;
            border: 1px solid var(--line);
            background: #fff;
        }
        textarea { min-height: 16rem; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .9rem; }
        button, .button {
            font-family: ui-sans-serif, system-ui, sans-serif;
            background: var(--accent);
            color: #fff;
            border: 0;
            padding: .65rem 1rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        button:hover, .button:hover { background: var(--accent-hover); }
        .button-quiet { background: transparent; color: var(--accent); border: 1px solid var(--line); }
        table { width: 100%; border-collapse: collapse; font-family: ui-sans-serif, system-ui, sans-serif; font-size: .95rem; }
        th, td { text-align: left; padding: .7rem .4rem; border-bottom: 1px solid var(--line); }
        .muted { color: var(--muted); }
        .toolbar { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1rem; }
        form.inline { display: inline; }
    </style>
</head>
<body>
    <header class="app-header">
        <a class="brand" href="{{ route('home') }}">{{ config('app.name') }}</a>
        <nav>
            @if (auth()->user()->isAdmin())
                <a href="{{ route('admin.dashboard') }}" @if(request()->routeIs('home') || request()->routeIs('admin.dashboard')) aria-current="page" @endif>Profiles</a>
                @can('create', App\Models\CompanyProfile::class)
                    <a href="{{ route('admin.clients.create') }}" @if(request()->routeIs('admin.clients.create')) aria-current="page" @endif>New client</a>
                @endcan
                @can('manage-settings')
                    <a href="{{ route('admin.settings') }}" @if(request()->routeIs('admin.settings')) aria-current="page" @endif>Settings</a>
                @endcan
            @endif
            <span class="muted">{{ auth()->user()->name }}</span>
            <form class="inline" method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="button-quiet" type="submit">Log out</button>
            </form>
        </nav>
    </header>
    <main class="@yield('main_class')">
        @if (session('success'))
            <div class="flash" role="status">{{ session('success') }}</div>
        @endif
        @yield('content')
    </main>
    <script src="{{ asset('js/live-preview.js') }}" defer></script>
</body>
</html>
