@extends('layouts.admin')

@section('title', 'Company profiles')

@section('content')
    <div class="toolbar">
        <div>
            <h1>Company profiles</h1>
            <p class="muted lede">Manage client sites, publish status, and expiry in one place.</p>
        </div>
        @can('create', App\Models\CompanyProfile::class)
            <a class="button" href="{{ route('admin.clients.create') }}">New client</a>
        @endcan
    </div>

    <div class="card">
        <div class="table-wrap">
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
                            <td>
                                <a href="{{ $profile->publicUrl() }}" target="_blank" rel="noopener noreferrer">
                                    {{ $profile->subdomain }}.{{ config('singlepage.base_domain') }}
                                </a>
                            </td>
                            <td>
                                <span class="badge badge-{{ $profile->status->value }}">{{ $profile->status->value }}</span>
                            </td>
                            <td>
                                {{ $profile->expires_at?->format('d M Y') ?? '—' }}
                                @if ($profile->isExpired())
                                    <div><span class="badge badge-expired">expired</span></div>
                                @endif
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a class="button button-quiet button-small" href="{{ route('admin.clients.edit', $profile) }}">Edit</a>
                                    <a class="button button-quiet button-small" href="{{ $profile->publicUrl() }}" target="_blank" rel="noopener noreferrer">Visit website</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <p>No company profiles yet.</p>
                                    <p class="muted">Create a client to publish their first one-page site.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
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
