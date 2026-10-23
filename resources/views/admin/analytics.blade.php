@extends('layouts.admin')

@section('title', 'Analytics — ' . $companyProfile->subdomain)

@section('content')
    <div class="page-heading">
        <div>
            <h1>Analytics</h1>
            <p class="muted lede">
                <a href="{{ $companyProfile->publicUrl() }}" target="_blank" rel="noopener noreferrer">
                    {{ $companyProfile->subdomain }}.{{ config('singlepage.base_domain') }}
                </a>
                · Unique visitors per day
            </p>
        </div>
        <div class="row-actions">
            <a class="button button-quiet button-small" href="{{ route('admin.clients.edit', $companyProfile) }}">← Edit site</a>
            <a class="button button-quiet button-small" href="{{ route('admin.dashboard') }}">All clients</a>
        </div>
    </div>

    {{-- Range selector --}}
    <div class="analytics-ranges">
        @foreach ([7 => '7 days', 30 => '30 days', 90 => '90 days'] as $d => $label)
            <a href="{{ route('admin.clients.analytics', ['companyProfile' => $companyProfile, 'days' => $d]) }}"
               class="button button-small {{ $days === $d ? '' : 'button-quiet' }}">{{ $label }}</a>
        @endforeach
    </div>

    {{-- Summary cards --}}
    <div class="analytics-cards">
        <div class="analytics-card">
            <div class="analytics-card-value">{{ number_format($todayCount) }}</div>
            <div class="analytics-card-label">Today</div>
        </div>
        <div class="analytics-card">
            <div class="analytics-card-value">{{ number_format($total) }}</div>
            <div class="analytics-card-label">Last {{ $days }} days</div>
        </div>
        <div class="analytics-card">
            <div class="analytics-card-value">{{ number_format($avg, 1) }}</div>
            <div class="analytics-card-label">Daily avg</div>
        </div>
        <div class="analytics-card">
            <div class="analytics-card-value">{{ number_format($peak) }}</div>
            <div class="analytics-card-label">
                Best day
                @if ($peakDay)
                    <span class="analytics-card-sub">{{ \Carbon\Carbon::parse($peakDay)->format('d M') }}</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Bar chart --}}
    @php
        $barCount = count($series);
        $maxVal   = $barCount ? max(array_values($series)) : 0;
        $vW       = 800;
        $vH       = 180;
        $labelH   = 20;
        $barW     = $barCount > 0 ? $vW / $barCount : 1;
        $innerW   = max(1.0, $barW - 2);
        $dates    = array_keys($series);
    @endphp

    <div class="card analytics-chart-card">
        @if ($total === 0)
            <p class="muted analytics-empty">No visits recorded in this period.</p>
        @else
            <svg
                class="analytics-chart"
                viewBox="0 0 {{ $vW }} {{ $vH + $labelH }}"
                preserveAspectRatio="none"
                role="img"
                aria-label="Daily unique visitors bar chart for {{ $companyProfile->subdomain }}"
            >
                @foreach ($series as $date => $count)
                    @php
                        $idx  = $loop->index;
                        $barH = $maxVal > 0 ? ($count / $maxVal) * $vH : 0;
                        $x    = $idx * $barW + 1;
                        $y    = $vH - $barH;
                    @endphp
                    <rect
                        x="{{ $x }}"
                        y="{{ round($y, 2) }}"
                        width="{{ round($innerW, 2) }}"
                        height="{{ round(max(1, $barH), 2) }}"
                        class="chart-bar{{ $count === 0 ? ' chart-bar-zero' : '' }}"
                    ><title>{{ $date }}: {{ $count }} visitor{{ $count !== 1 ? 's' : '' }}</title></rect>
                @endforeach

                {{-- X-axis: first, middle, last date labels --}}
                @foreach ([0, (int) floor(($barCount - 1) / 2), $barCount - 1] as $li)
                    @if ($barCount > 0 && isset($dates[$li]))
                        <text
                            x="{{ round($li * $barW + $barW / 2, 2) }}"
                            y="{{ $vH + $labelH - 3 }}"
                            class="chart-label"
                            text-anchor="middle"
                        >{{ \Carbon\Carbon::parse($dates[$li])->format('d/m') }}</text>
                    @endif
                @endforeach
            </svg>
        @endif
    </div>

    {{-- Expandable daily breakdown --}}
    <details class="card analytics-details">
        <summary class="analytics-summary">Daily breakdown ({{ $days }} days)</summary>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Date</th><th>Unique visitors</th></tr>
                </thead>
                <tbody>
                    @foreach (array_reverse($series, true) as $date => $count)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($date)->translatedFormat('d M Y') }}</td>
                            <td>{{ $count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
@endsection
