@extends('layouts.admin')

@section('title', 'Deploy')

@section('content')
    <div class="page-heading">
        <div>
            <h1>Deploy</h1>
            <p class="muted lede">Trigger a production deploy of admin.jadidaring.com via GitHub Actions.</p>
        </div>
    </div>

    @if ($errors->any())
        <ul class="errors">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    {{-- ── Deploy card ──────────────────────────────────────────────────── --}}
    <div class="card" style="max-width:36rem">
        @if ($configured)
            <form
                id="deploy-form"
                method="POST"
                action="{{ route('admin.deploy.dispatch') }}"
                onsubmit="return confirmDeploy(this)"
            >
                @csrf
                <div style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap">
                    <div style="flex:1;min-width:10rem">
                        <label for="deploy-ref" style="display:block;margin-bottom:.4rem;font-weight:500">
                            Branch or tag
                        </label>
                        <input
                            id="deploy-ref"
                            type="text"
                            name="ref"
                            value="{{ old('ref', 'main') }}"
                            placeholder="main"
                            pattern="[a-zA-Z0-9_.\/\-]+"
                            autocomplete="off"
                            spellcheck="false"
                            style="width:100%"
                        >
                    </div>
                    <button type="submit" id="deploy-btn" class="button">
                        Deploy
                    </button>
                </div>
                <p class="muted" style="margin:.75rem 0 0;font-size:.85rem">
                    The workflow runs on GitHub Actions. A concurrency lock prevents parallel deploys.
                </p>
            </form>
        @else
            <p style="margin:0 0 .5rem;font-weight:500">GitHub integration not configured</p>
            <p class="muted" style="margin:0;font-size:.9rem">
                Add <code>GITHUB_DEPLOY_TOKEN</code> and <code>GITHUB_DEPLOY_REPO</code> to the
                server's <code>.env</code> file and restart the containers to enable this button.
                See <strong>DEPLOY.md</strong> in the repository for setup instructions.
            </p>
            <p style="margin:.75rem 0 0">
                <button class="button" disabled style="cursor:not-allowed;opacity:.5">Deploy</button>
            </p>
        @endif
    </div>

    {{-- ── Recent workflow runs ─────────────────────────────────────────── --}}
    @if ($configured)
        <h2 style="margin:2rem 0 .75rem;font-size:1.15rem;letter-spacing:-.025em">
            Recent deploy runs
            <span id="runs-spinner" style="display:none;font-size:.8rem;color:var(--muted);font-weight:400"> · refreshing…</span>
        </h2>

        <div id="runs-container">
            <p class="muted" style="font-size:.9rem">Loading runs…</p>
        </div>
    @endif

    {{-- ── Audit log (press history) ────────────────────────────────────── --}}
    @if ($recentLogs->isNotEmpty())
        <h2 style="margin:2.5rem 0 .75rem;font-size:1.15rem;letter-spacing:-.025em">Who pressed Deploy</h2>
        <div class="table-wrap">
            <table style="width:100%;border-collapse:collapse;font-size:.9rem">
                <thead>
                    <tr style="border-bottom:1px solid var(--line);text-align:left">
                        <th style="padding:.5rem .75rem;font-weight:600">Admin</th>
                        <th style="padding:.5rem .75rem;font-weight:600">Ref</th>
                        <th style="padding:.5rem .75rem;font-weight:600">When</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentLogs as $log)
                        <tr style="border-bottom:1px solid var(--line)">
                            <td style="padding:.5rem .75rem">{{ $log->user_name }}</td>
                            <td style="padding:.5rem .75rem"><code>{{ $log->ref }}</code></td>
                            <td style="padding:.5rem .75rem;color:var(--muted)">
                                {{ $log->created_at->diffForHumans() }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection

@if ($configured)
@push('scripts')
<script>
(function () {
    'use strict';

    var RUNS_URL   = {{ Js::from(route('admin.deploy.runs')) }};
    var pollTimer  = null;
    var inProgress = false;

    function statusBadge(run) {
        var s = run.status, c = run.conclusion;
        if (s === 'completed') {
            if (c === 'success')   return '<span class="badge badge-published">success</span>';
            if (c === 'failure')   return '<span class="badge badge-expired">failure</span>';
            if (c === 'cancelled') return '<span class="badge badge-draft">cancelled</span>';
            return '<span class="badge">' + escHtml(c || s) + '</span>';
        }
        if (s === 'in_progress') return '<span class="badge badge-draft">in progress</span>';
        return '<span class="badge">' + escHtml(s) + '</span>';
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderRuns(runs) {
        if (!runs || runs.length === 0) {
            document.getElementById('runs-container').innerHTML =
                '<p class="muted" style="font-size:.9rem">No runs found.</p>';
            return;
        }

        var rows = runs.map(function (r) {
            var when = r.created_at ? new Date(r.created_at).toLocaleString() : '—';
            return '<tr style="border-bottom:1px solid var(--line)">'
                + '<td style="padding:.5rem .75rem">' + statusBadge(r) + '</td>'
                + '<td style="padding:.5rem .75rem"><code>' + escHtml(r.display_ref) + '</code></td>'
                + '<td style="padding:.5rem .75rem;color:var(--muted)">' + escHtml(r.actor) + '</td>'
                + '<td style="padding:.5rem .75rem;color:var(--muted);font-size:.85rem">' + escHtml(when) + '</td>'
                + '<td style="padding:.5rem .75rem">'
                +   '<a href="' + escHtml(r.html_url) + '" target="_blank" rel="noopener" style="font-size:.85rem">View #' + escHtml(String(r.run_number)) + '</a>'
                + '</td>'
                + '</tr>';
        }).join('');

        document.getElementById('runs-container').innerHTML =
            '<div class="table-wrap"><table style="width:100%;border-collapse:collapse;font-size:.9rem">'
            + '<thead><tr style="border-bottom:1px solid var(--line);text-align:left">'
            + '<th style="padding:.5rem .75rem">Status</th>'
            + '<th style="padding:.5rem .75rem">Ref</th>'
            + '<th style="padding:.5rem .75rem">Triggered by</th>'
            + '<th style="padding:.5rem .75rem">Started</th>'
            + '<th style="padding:.5rem .75rem">Link</th>'
            + '</tr></thead>'
            + '<tbody>' + rows + '</tbody>'
            + '</table></div>';

        // Determine whether any run is still active.
        inProgress = runs.some(function (r) {
            return r.status === 'queued' || r.status === 'in_progress';
        });
    }

    function fetchRuns() {
        var spinner = document.getElementById('runs-spinner');
        if (spinner) spinner.style.display = 'inline';

        fetch(RUNS_URL, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (resp) { return resp.json(); })
            .then(function (data) {
                if (spinner) spinner.style.display = 'none';
                if (data.configured === false) return;
                if (data.error) {
                    document.getElementById('runs-container').innerHTML =
                        '<p class="muted" style="font-size:.9rem">Could not load runs: ' + escHtml(data.error) + '</p>';
                    return;
                }
                renderRuns(data.runs || []);
                schedulePoll();
            })
            .catch(function () {
                if (spinner) spinner.style.display = 'none';
            });
    }

    function schedulePoll() {
        clearTimeout(pollTimer);
        // Poll every 12 s while a run is in progress; every 60 s otherwise.
        var delay = inProgress ? 12000 : 60000;
        pollTimer = setTimeout(fetchRuns, delay);
    }

    window.confirmDeploy = function (form) {
        var ref = form.querySelector('[name="ref"]').value || 'main';
        if (!confirm('Deploy branch/tag "' + ref + '" to production?\n\nThis will restart the app containers.')) {
            return false;
        }
        var btn = document.getElementById('deploy-btn');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Dispatching…';
        }
        return true;
    };

    // Initial fetch.
    fetchRuns();
})();
</script>
@endpush
@endif
