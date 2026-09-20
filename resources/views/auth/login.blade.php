<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign in — {{ config('app.name') }}</title>
    @include('partials.theme-boot')
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
    <div class="auth-shell">
        <div class="auth-top">
            @include('partials.theme-toggle')
        </div>
        <main class="auth-card">
            <a class="brand" href="{{ route('login') }}">
                <span class="brand-mark" aria-hidden="true">{{ strtoupper(substr(config('app.name'), 0, 1)) }}</span>
                {{ config('app.name') }}
            </a>
            <h1>Sign in</h1>
            <p>Admin host only. Session cookies stay on this hostname.</p>
            @if ($errors->any())
                <ul class="errors">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
            <form method="POST" action="{{ route('login') }}">
                @csrf
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required autocomplete="current-password">
                <label class="remember">
                    <input type="checkbox" name="remember" value="1">
                    Remember me
                </label>
                <button type="submit">Continue</button>
            </form>
        </main>
    </div>
    <script src="{{ asset('js/theme.js') }}" defer></script>
</body>
</html>
