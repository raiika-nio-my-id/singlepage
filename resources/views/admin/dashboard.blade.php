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

    {{-- Sort control --}}
    <form method="GET" action="{{ route('admin.dashboard') }}" class="sort-form" id="sort-form">
        <label for="sort-select" class="sort-form-label">Sort:</label>
        <select id="sort-select" name="sort" class="sort-select"
                onchange="this.form.submit()">
            <option value="custom"    @selected($sort === 'custom')>Custom order</option>
            <option value="name_asc"  @selected($sort === 'name_asc')>Name A–Z</option>
            <option value="name_desc" @selected($sort === 'name_desc')>Name Z–A</option>
            <option value="views"     @selected($sort === 'views')>Most views (30d)</option>
            <option value="expired"   @selected($sort === 'expired')>Expiring soon</option>
            <option value="newest"    @selected($sort === 'newest')>Newest</option>
        </select>
        <noscript>
            <button type="submit" class="button button-quiet button-small sort-submit-fallback">Apply</button>
        </noscript>
        @if ($sort !== 'custom')
            <span class="sort-drag-hint">Drag available in Custom order</span>
        @endif
    </form>

    <div class="card">
        <div class="table-wrap">
            <table id="profiles-table">
                <thead>
                    <tr>
                        <th class="controls-col"></th>
                        <th>Company / owner</th>
                        <th>Subdomain</th>
                        <th>Status</th>
                        <th>Expiry</th>
                        <th class="views-col" title="Unique visitors today / last 7 days / last 30 days">Views</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody
                    id="profiles-sortable"
                    data-sort="{{ $sort }}"
                    data-offset="{{ ($profiles->currentPage() - 1) * $profiles->perPage() }}"
                >
                    @forelse ($profiles as $profile)
                        @php $stats = $viewStats[$profile->id] ?? null; @endphp
                        <tr data-id="{{ $profile->id }}"
                            draggable="{{ $sort === 'custom' ? 'true' : 'false' }}"
                            class="{{ $profile->is_pinned ? 'row-pinned' : '' }}">
                            <td class="controls-col">
                                {{-- Pin star – JS fetch, form fallback --}}
                                <form method="POST"
                                      action="{{ route('admin.clients.pin', $profile) }}"
                                      class="inline pin-form">
                                    @csrf
                                    <button type="submit"
                                            class="pin-btn {{ $profile->is_pinned ? 'pinned' : '' }}"
                                            data-pin="{{ route('admin.clients.pin', $profile) }}"
                                            data-csrf="{{ csrf_token() }}"
                                            title="{{ $profile->is_pinned ? 'Unpin' : 'Pin to top' }}"
                                            aria-pressed="{{ $profile->is_pinned ? 'true' : 'false' }}">
                                        <svg viewBox="0 0 20 20" aria-hidden="true">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                        </svg>
                                    </button>
                                </form>
                                @if ($sort === 'custom')
                                    <span class="drag-handle" aria-hidden="true" title="Drag to reorder">⠿</span>
                                @endif
                            </td>
                            <td>
                                {{ $profile->user?->name }}
                                <div class="muted">{{ $profile->user?->email }}</div>
                            </td>
                            <td>
                                <a href="{{ $profile->publicUrl() }}" target="_blank" rel="noopener noreferrer">
                                    {{ $profile->subdomain }}.{{ config('singlepage.base_domain') }}
                                </a>
                                @foreach ($profile->aliases as $alias)
                                    <div class="muted alias-hint">↳ {{ $alias->subdomain }}.{{ config('singlepage.base_domain') }}</div>
                                @endforeach
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
                                    <a class="button button-quiet button-small"
                                       href="{{ route('admin.clients.edit', $profile) }}">Edit</a>
                                    <a class="button button-quiet button-small"
                                       href="{{ $profile->publicUrl() }}"
                                       target="_blank" rel="noopener noreferrer">Visit website</a>
                                    <a class="btn-icon"
                                       href="{{ route('admin.clients.analytics', $profile) }}"
                                       title="Analytics">
                                        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zm6-4a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zm6-3a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/>
                                        </svg>
                                    </a>
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

        @if ($profiles->hasPages())
            <div class="pagination-bar">
                @if ($profiles->onFirstPage())
                    <span class="page-btn disabled">‹ Prev</span>
                @else
                    <a class="page-btn" href="{{ $profiles->previousPageUrl() }}">‹ Prev</a>
                @endif
                <span class="page-info">
                    Page {{ $profiles->currentPage() }} of {{ $profiles->lastPage() }}
                    · {{ $profiles->total() }} total
                </span>
                @if ($profiles->hasMorePages())
                    <a class="page-btn" href="{{ $profiles->nextPageUrl() }}">Next ›</a>
                @else
                    <span class="page-btn disabled">Next ›</span>
                @endif
            </div>
        @endif
    </div>

    <script>
        (() => {
            /* ── Pin toggle via fetch (form POST fallback) ── */
            document.querySelectorAll('.pin-btn[data-pin]').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    const row = btn.closest('tr');
                    try {
                        const res = await fetch(btn.dataset.pin, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': btn.dataset.csrf,
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                            },
                        });
                        if (!res.ok) throw new Error();
                        const { is_pinned } = await res.json();
                        btn.classList.toggle('pinned', is_pinned);
                        btn.setAttribute('aria-pressed', String(is_pinned));
                        btn.setAttribute('title', is_pinned ? 'Unpin' : 'Pin to top');
                        row?.classList.toggle('row-pinned', is_pinned);
                    } catch {
                        btn.closest('form')?.submit();
                    }
                });
            });

            /* ── Drag-and-drop (Custom sort only) ── */
            const tbody = document.getElementById('profiles-sortable');
            const reorderUrl = @json(route('admin.clients.reorder'));
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

            if (!tbody) return;

            const sortMode = tbody.dataset.sort;
            const offset   = parseInt(tbody.dataset.offset || '0', 10);

            if (sortMode !== 'custom') return;

            let dragging    = null;
            let touchDragEl = null;
            let touchClone  = null;
            let touchOffY   = 0;
            let touchOffX   = 0;

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
                if (e.clientY < rect.top + rect.height / 2) {
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

            tbody.addEventListener('touchstart', (e) => {
                const handle = e.target.closest('.drag-handle');
                if (!handle) return;
                touchDragEl = handle.closest('tr');
                if (!touchDragEl) return;
                const touch = e.touches[0];
                const rect  = touchDragEl.getBoundingClientRect();
                touchOffY = touch.clientY - rect.top;
                touchOffX = touch.clientX - rect.left;
                touchClone = touchDragEl.cloneNode(true);
                touchClone.style.cssText = `position:fixed;z-index:9999;opacity:0.85;pointer-events:none;width:${rect.width}px;box-shadow:var(--shadow);background:var(--surface);`;
                touchClone.style.top  = `${rect.top}px`;
                touchClone.style.left = `${rect.left}px`;
                document.body.appendChild(touchClone);
                touchDragEl.classList.add('dragging');
            }, { passive: true });

            tbody.addEventListener('touchmove', (e) => {
                if (!touchDragEl || !touchClone) return;
                e.preventDefault();
                const touch = e.touches[0];
                touchClone.style.top  = `${touch.clientY - touchOffY}px`;
                touchClone.style.left = `${touch.clientX - touchOffX}px`;
                const elBelow  = document.elementFromPoint(touch.clientX, touch.clientY);
                const rowBelow = elBelow?.closest('tr');
                if (rowBelow && rowBelow !== touchDragEl && rowBelow.parentElement === tbody) {
                    const rect = rowBelow.getBoundingClientRect();
                    if (touch.clientY < rect.top + rect.height / 2) {
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
                touchClone  = null;
                persistOrder();
            });

            function persistOrder() {
                const ids = [...tbody.querySelectorAll('tr[data-id]')].map(r => r.dataset.id);
                fetch(reorderUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ ids, offset }),
                }).catch(() => {});
            }
        })();
    </script>
@endsection
