@extends('layouts.admin')

@section('title', 'Company profiles')

@section('content')
    <div class="toolbar">
        <div>
            <h1>Company profiles</h1>
            <p class="muted lede">Manage client sites, publish status, and expiry in one place. Drag rows to reorder.</p>
        </div>
        @can('create', App\Models\CompanyProfile::class)
            <a class="button" href="{{ route('admin.clients.create') }}">New client</a>
        @endcan
    </div>

    <div class="card">
        <div class="table-wrap">
            <table id="profiles-table">
                <thead>
                    <tr>
                        <th class="drag-col" aria-label="Drag to reorder"></th>
                        <th>Company / owner</th>
                        <th>Subdomain</th>
                        <th>Status</th>
                        <th>Expired</th>
                        <th class="views-col" title="Unique visitors today / last 7 days / last 30 days">Views</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="profiles-sortable">
                    @forelse ($profiles as $profile)
                        @php $stats = $viewStats[$profile->id] ?? null; @endphp
                        <tr data-id="{{ $profile->id }}" draggable="true">
                            <td class="drag-handle" aria-hidden="true">⠿</td>
                            <td>
                                @if ($profile->admin_alias)
                                    <strong>{{ $profile->admin_alias }}</strong>
                                    <div class="muted">{{ $profile->user?->name }}</div>
                                @else
                                    {{ $profile->user?->name }}
                                @endif
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
                            <td class="views-col">
                                @if ($stats)
                                    <span title="Today">{{ (int) $stats->today }}</span>
                                    <span class="muted"> / </span>
                                    <span title="Last 7 days">{{ (int) $stats->last7 }}</span>
                                    <span class="muted"> / </span>
                                    <span title="Last 30 days">{{ (int) $stats->last30 }}</span>
                                @else
                                    <span class="muted">0 / 0 / 0</span>
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
                            <td colspan="7">
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
    </div>

    <script>
        (() => {
            const tbody = document.getElementById('profiles-sortable');
            const reorderUrl = @json(route('admin.clients.reorder'));
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

            if (!tbody) return;

            let dragging = null;
            let touchDragEl = null;
            let touchClone = null;
            let touchOffsetY = 0;
            let touchOffsetX = 0;

            /* ── Mouse / HTML5 drag ── */

            tbody.addEventListener('dragstart', (e) => {
                dragging = e.target.closest('tr');
                if (!dragging) return;
                dragging.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
            });

            tbody.addEventListener('dragend', () => {
                if (dragging) dragging.classList.remove('dragging');
                dragging = null;
                tbody.querySelectorAll('tr').forEach(r => r.classList.remove('drag-over'));
            });

            tbody.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                const target = e.target.closest('tr');
                if (!target || target === dragging) return;
                tbody.querySelectorAll('tr').forEach(r => r.classList.remove('drag-over'));
                target.classList.add('drag-over');
                const rect = target.getBoundingClientRect();
                const mid = rect.top + rect.height / 2;
                if (e.clientY < mid) {
                    tbody.insertBefore(dragging, target);
                } else {
                    tbody.insertBefore(dragging, target.nextSibling);
                }
            });

            tbody.addEventListener('drop', (e) => {
                e.preventDefault();
                tbody.querySelectorAll('tr').forEach(r => r.classList.remove('drag-over'));
                persistOrder();
            });

            /* ── Touch drag ── */

            tbody.addEventListener('touchstart', (e) => {
                const handle = e.target.closest('.drag-handle');
                if (!handle) return;
                touchDragEl = handle.closest('tr');
                if (!touchDragEl) return;
                const touch = e.touches[0];
                const rect = touchDragEl.getBoundingClientRect();
                touchOffsetY = touch.clientY - rect.top;
                touchOffsetX = touch.clientX - rect.left;

                touchClone = touchDragEl.cloneNode(true);
                touchClone.style.cssText = `position:fixed;z-index:9999;opacity:0.85;pointer-events:none;width:${rect.width}px;box-shadow:var(--shadow);background:var(--surface);`;
                touchClone.style.top = `${rect.top}px`;
                touchClone.style.left = `${rect.left}px`;
                document.body.appendChild(touchClone);
                touchDragEl.classList.add('dragging');
            }, { passive: true });

            tbody.addEventListener('touchmove', (e) => {
                if (!touchDragEl || !touchClone) return;
                e.preventDefault();
                const touch = e.touches[0];
                touchClone.style.top = `${touch.clientY - touchOffsetY}px`;
                touchClone.style.left = `${touch.clientX - touchOffsetX}px`;

                const elBelow = document.elementFromPoint(touch.clientX, touch.clientY);
                const rowBelow = elBelow?.closest('tr');
                if (rowBelow && rowBelow !== touchDragEl && rowBelow.parentElement === tbody) {
                    const rect = rowBelow.getBoundingClientRect();
                    const mid = rect.top + rect.height / 2;
                    if (touch.clientY < mid) {
                        tbody.insertBefore(touchDragEl, rowBelow);
                    } else {
                        tbody.insertBefore(touchDragEl, rowBelow.nextSibling);
                    }
                }
            }, { passive: false });

            tbody.addEventListener('touchend', () => {
                if (!touchDragEl) return;
                touchDragEl.classList.remove('dragging');
                touchClone?.remove();
                touchDragEl = null;
                touchClone = null;
                persistOrder();
            });

            /* ── Persist ── */

            function persistOrder() {
                const ids = [...tbody.querySelectorAll('tr[data-id]')].map(r => r.dataset.id);
                fetch(reorderUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ ids }),
                }).catch(() => { /* silent – order will revert on next reload */ });
            }
        })();
    </script>
@endsection
