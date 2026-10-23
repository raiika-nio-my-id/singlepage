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

    {{-- Range selector + export hint --}}
    <div class="analytics-header-row">
        <div class="analytics-ranges">
            @foreach ([7 => '7 days', 30 => '30 days', 90 => '90 days'] as $d => $label)
                <a href="{{ route('admin.clients.analytics', ['companyProfile' => $companyProfile, 'days' => $d]) }}"
                   class="analytics-range-btn {{ $days === $d ? 'active' : '' }}">{{ $label }}</a>
            @endforeach
        </div>
        <span class="muted" style="font-size:.82rem">Last {{ $days }} days · unique visitors</span>
    </div>

    {{-- Summary cards --}}
    <div class="analytics-cards">
        <div class="analytics-card">
            <div class="analytics-card-label">Today</div>
            <div class="analytics-card-value">{{ number_format($todayCount) }}</div>
        </div>
        <div class="analytics-card">
            <div class="analytics-card-label">{{ $days }}-day total</div>
            <div class="analytics-card-value">{{ number_format($total) }}</div>
        </div>
        <div class="analytics-card">
            <div class="analytics-card-label">Daily avg</div>
            <div class="analytics-card-value">{{ number_format($avg, 1) }}</div>
        </div>
        <div class="analytics-card">
            <div class="analytics-card-label">Best day</div>
            <div class="analytics-card-value">{{ number_format($peak) }}</div>
            @if ($peakDay)
                <div class="analytics-card-sub">{{ \Carbon\Carbon::parse($peakDay)->format('d M Y') }}</div>
            @endif
        </div>
    </div>

    {{-- Bar chart --}}
    @php
        $barCount = count($series);
        $maxVal   = $barCount ? max(array_values($series)) : 0;
        $vW       = 800;
        $vH       = 200;   // chart drawing area height
        $padB     = 28;    // bottom padding for labels
        $padT     = 10;    // top padding so max bar doesn't touch edge
        $totalH   = $vH + $padB + $padT;
        $drawH    = $vH - $padT;
        $barW     = $barCount > 0 ? $vW / $barCount : 1;
        $innerW   = max(1.0, $barW - 2);
        $dates    = array_keys($series);
        // Y gridlines at 25 / 50 / 75 / 100 %
        $gridYs   = [.25, .5, .75, 1.0];
    @endphp

    <div class="card analytics-chart-card">
        @if ($total === 0)
            <p class="analytics-empty">No visits recorded in this period.</p>
        @else
            <svg
                class="analytics-chart"
                viewBox="0 0 {{ $vW }} {{ $totalH }}"
                preserveAspectRatio="none"
                role="img"
                aria-label="Daily unique visitors for {{ $companyProfile->subdomain }}, last {{ $days }} days"
            >
                {{-- Horizontal grid lines --}}
                @foreach ($gridYs as $pct)
                    @php $gy = round($padT + $drawH * (1 - $pct), 2); @endphp
                    <line class="chart-grid"
                          x1="0" y1="{{ $gy }}"
                          x2="{{ $vW }}" y2="{{ $gy }}"
                          stroke-dasharray="{{ $pct === 1.0 ? 'none' : '3 4' }}"
                          stroke-width="1"/>
                @endforeach

                {{-- Bars --}}
                @foreach ($series as $date => $count)
                    @php
                        $idx  = $loop->index;
                        $barH = ($maxVal > 0 && $count > 0) ? max(2, ($count / $maxVal) * $drawH) : 2;
                        $x    = round($idx * $barW + 1, 2);
                        $y    = round($padT + $drawH - $barH, 2);
                    @endphp
                    <rect
                        x="{{ $x }}"
                        y="{{ $y }}"
                        width="{{ round($innerW, 2) }}"
                        height="{{ round($barH, 2) }}"
                        rx="3" ry="3"
                        class="chart-bar{{ $count === 0 ? ' chart-bar-zero' : '' }}"
                    ><title>{{ $date }}: {{ $count }} visitor{{ $count !== 1 ? 's' : '' }}</title></rect>
                @endforeach

                {{-- X-axis baseline --}}
                <line class="chart-axis"
                      x1="0" y1="{{ $padT + $drawH + 1 }}"
                      x2="{{ $vW }}" y2="{{ $padT + $drawH + 1 }}"/>

                {{-- X-axis date labels: first / middle / last --}}
                @php
                    $labelPos = array_unique([0, (int) floor(($barCount - 1) / 2), max(0, $barCount - 1)]);
                @endphp
                @foreach ($labelPos as $li)
                    @if (isset($dates[$li]))
                        <text
                            x="{{ round($li * $barW + $barW / 2, 2) }}"
                            y="{{ $totalH - 4 }}"
                            class="chart-label"
                            text-anchor="middle"
                        >{{ \Carbon\Carbon::parse($dates[$li])->format('d/m') }}</text>
                    @endif
                @endforeach
            </svg>
        @endif
    </div>

    {{-- Expandable daily table --}}
    <details class="card analytics-details">
        <summary class="analytics-summary">Daily breakdown · {{ $days }} days</summary>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Unique visitors</th>
                    </tr>
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
