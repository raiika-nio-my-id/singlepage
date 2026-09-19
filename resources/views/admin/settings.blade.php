@extends('layouts.admin')

@section('title', 'Settings')

@section('content')
    <h1>Settings</h1>
    <p class="muted">One reserved subdomain per line. These labels cannot be assigned to a client. Built-in reserved names always include <code>admin</code>.</p>

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
        <p><button type="submit">Save settings</button></p>
    </form>
@endsection
