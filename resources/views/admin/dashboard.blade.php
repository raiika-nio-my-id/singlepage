@extends('layouts.admin')

@section('title', 'Company profiles')

@section('content')
    <div class="toolbar">
        <h1>Company profiles</h1>
        @can('create', App\Models\CompanyProfile::class)
            <a class="button" href="{{ route('admin.clients.create') }}">New client</a>
        @endcan
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Company / owner</th>
                    <th>Subdomain</th>
                    <th>Status</th>
                    <th>Expired</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($profiles as $profile)
                    <tr>
                        <td>
                            {{ $profile->user?->name }}
                            <div class="muted">{{ $profile->user?->email }}</div>
                        </td>
                        <td>{{ $profile->subdomain }}.{{ config('singlepage.base_domain') }}</td>
                        <td>{{ $profile->status->value }}</td>
                        <td>
                            {{ $profile->expires_at?->format('d M Y') ?? '—' }}
                            @if ($profile->isExpired())
                                <div class="muted">expired</div>
                            @endif
                        </td>
                        <td><a href="{{ route('admin.clients.edit', $profile) }}">Edit</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="muted">No company profiles yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if ($profiles->hasPages())
            <p class="muted">
                @if ($profiles->onFirstPage())
                    <span>Previous</span>
                @else
                    <a href="{{ $profiles->previousPageUrl() }}">Previous</a>
                @endif
                ·
                @if ($profiles->hasMorePages())
                    <a href="{{ $profiles->nextPageUrl() }}">Next</a>
                @else
                    <span>Next</span>
                @endif
            </p>
        @endif
    </div>
@endsection
