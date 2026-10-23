<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — {{ config('app.name') }}</title>
    @include('partials.theme-boot')
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
    <header class="app-header">
        <a class="brand" href="{{ route('home') }}">
            <span class="brand-mark" aria-hidden="true">{{ strtoupper(substr(config('app.name'), 0, 1)) }}</span>
            {{ config('app.name') }}
        </a>
        <button
            type="button"
            class="nav-toggle"
            data-nav-toggle
            aria-expanded="false"
            aria-controls="app-nav"
        >Menu</button>
        <nav class="app-nav" id="app-nav">
            @if (auth()->user()->isAdmin())
                <a href="{{ route('admin.dashboard') }}" @if(request()->routeIs('home') || request()->routeIs('admin.dashboard')) aria-current="page" @endif>Profiles</a>
                @can('create', App\Models\CompanyProfile::class)
                    <a href="{{ route('admin.clients.create') }}" @if(request()->routeIs('admin.clients.create')) aria-current="page" @endif>New client</a>
                @endcan
                @can('manage-settings')
                    <a href="{{ route('admin.settings') }}" @if(request()->routeIs('admin.settings')) aria-current="page" @endif>Settings</a>
                @endcan
            @endif
            <span class="user-chip">{{ auth()->user()->name }}</span>
            @include('partials.theme-toggle')
            <form class="inline" method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="button-quiet button-small" type="submit">Log out</button>
            </form>
        </nav>
    </header>
    <main class="@yield('main_class')">
        @if (session('success'))
            <div class="flash" role="status">{{ session('success') }}</div>
        @endif
        @yield('content')
    </main>
    <script src="{{ asset('js/theme.js') }}" defer></script>
    <script src="{{ asset('js/editor-draft.js') }}" defer></script>
    <script src="{{ asset('js/live-preview.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
