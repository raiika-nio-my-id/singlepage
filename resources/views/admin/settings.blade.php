@extends('layouts.admin')

@section('title', 'Settings')

@section('content')
    <div class="page-heading">
        <div>
            <h1>Settings</h1>
            <p class="muted lede">Reserved subdomains and the shared game gamemaster key. Built-in reserved names always include <code>admin</code>.</p>
        </div>
    </div>

    @if ($errors->any())
        <ul class="errors">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form class="card" method="POST" action="{{ route('admin.settings.update') }}">
        @csrf
        @method('PUT')
        <label for="reserved_subdomains">Reserved subdomains</label>
        <textarea id="reserved_subdomains" name="reserved_subdomains" rows="14" spellcheck="false">{{ old('reserved_subdomains', $reservedSubdomains) }}</textarea>
        <p class="muted">One reserved subdomain per line. These labels cannot be assigned to a client.</p>

        <label for="gamemaster_secret">Gamemaster secret</label>
        <input type="text" id="gamemaster_secret" name="gamemaster_secret" value="{{ old('gamemaster_secret', $gamemasterSecret) }}" maxlength="128" autocomplete="off" spellcheck="false" placeholder="{{ config('games.gamemaster_secret') }}">
        <p class="muted">Shared GM key. Players should type this in-room and POST <code>/api/game/rooms/{code}/gamemaster</code>. <code>?gm=</code> / <code>X-Game-Master</code> private-room listing is transitional. Leave empty to use the server default (<code>{{ config('games.gamemaster_secret') }}</code>). Never exposed on <code>/api/game/config</code>.</p>
        <p><button type="submit">Save settings</button></p>
    </form>
@endsection
