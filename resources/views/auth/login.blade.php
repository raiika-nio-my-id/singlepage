<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign in — {{ config('app.name') }}</title>
    <style>
        :root { --ink: #1c1917; --muted: #57534e; --line: #e7e5e4; --paper: #f5f1eb; --card: #fffcf7; --accent: #0f3d3e; --danger: #9f1239; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; font-family: "Iowan Old Style", Palatino, Georgia, serif; background: var(--paper); color: var(--ink); }
        main { width: min(26rem, calc(100% - 2rem)); background: var(--card); border: 1px solid var(--line); padding: 2rem; }
        h1 { margin: 0 0 .35rem; font-size: 1.75rem; }
        p { color: var(--muted); margin: 0 0 1.25rem; }
        label { display: block; font-family: ui-sans-serif, system-ui, sans-serif; font-size: .85rem; margin: .9rem 0 .35rem; }
        input { width: 100%; padding: .6rem .7rem; border: 1px solid var(--line); font: inherit; }
        button { margin-top: 1.25rem; width: 100%; font-family: ui-sans-serif, system-ui, sans-serif; background: var(--accent); color: #fff; border: 0; padding: .7rem 1rem; cursor: pointer; }
        .errors { color: var(--danger); margin: 0 0 1rem; padding: 0; font-family: ui-sans-serif, system-ui, sans-serif; font-size: .9rem; }
        .remember { display: flex; align-items: center; gap: .5rem; margin-top: .9rem; font-family: ui-sans-serif, system-ui, sans-serif; font-size: .9rem; }
        .remember input { width: auto; }
    </style>
</head>
<body>
    <main>
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
</body>
</html>
