<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php
            $contextInterface = $currentInterface ?? 'staff';
            $contextUser = auth()->user();
            $contextRole = $userRole ?? ($contextUser?->normalizedRoleSlug() ?? null);
            $contextPayload = [
                'interface' => $contextInterface,
                'role' => $contextRole,
                'userId' => $contextUser?->id,
                'isAuthenticated' => (bool) $contextUser,
            ];
        @endphp

        <meta name="app-interface" content="{{ $contextInterface }}">
        <meta name="app-role" content="{{ $contextRole ?? '' }}">
        <script>
            window.__SKYE_DENTAL_CONTEXT__ = @json($contextPayload);
        </script>

        <title>{{ config('app.name', 'Skye Dental') }} - Analytics</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="clinic-shell">
        <div class="max-w-6xl mx-auto p-4 md:p-8">
            <div class="clinic-header mb-6">
                <div class="clinic-brand">
                    <img src="{{ asset('logo.png') }}" alt="{{ config('app.name', 'Skye Dental') }} logo" class="h-11 w-11 rounded-2xl" style="box-shadow: var(--shadow-clinic-card);" />
                    <div>
                        <div class="text-xs clinic-subtle tracking-wide uppercase">{{ config('app.name', 'Skye Dental') }}</div>
                        <h1 class="text-2xl md:text-3xl font-semibold leading-tight">Analytics Dashboard</h1>
                        <p class="text-sm clinic-subtle mt-1">One-look intelligence for operations, retention, and revenue.</p>
                    </div>
                </div>
                <div class="btn-group">
                    <button id="toggleTheme" type="button" class="btn">Dark Mode</button>
                    <span class="pill">Staff Interface</span>
                    <a href="/appointments-dashboard" class="btn">Appointments</a>
                    @if (auth()->user()?->hasPermission('appointments.checkin'))
                        <a href="/check-in" class="btn">Check-In</a>
                    @endif
                    @if (auth()->user()?->hasPermission('clinical.view'))
                        <a href="/charting" class="btn">Charting</a>
                    @endif
                    @if (auth()->user()?->hasPermission('billing.view'))
                        <a href="/billing" class="btn">Billing</a>
                    @endif
                    <div class="hidden sm:block text-xs clinic-subtle px-2">
                        {{ auth()->user()->name }} ({{ auth()->user()->role }})
                    </div>
                    <form method="POST" action="/logout">
                        @csrf
                        <button type="submit" class="btn">Logout</button>
                    </form>
                </div>
            </div>

            <div class="clinic-card mb-6">
                <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                    <div>
                        <h2 class="text-base font-medium">Smart Filters</h2>
                        <div class="text-sm clinic-subtle">Adjust once. Panels update instantly.</div>
                    </div>
                    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 w-full sm:w-auto">
                        <div>
                            <label class="block text-xs clinic-subtle mb-1" for="fromDate">From</label>
                            <input id="fromDate" type="date" class="clinic-input" />
                        </div>
                        <div>
                            <label class="block text-xs clinic-subtle mb-1" for="toDate">To</label>
                            <input id="toDate" type="date" class="clinic-input" />
                        </div>
                        <div>
                            <label class="block text-xs clinic-subtle mb-1" for="dentist">Dentist</label>
                            <select id="dentist" class="clinic-input">
                                <option value="">All</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs clinic-subtle mb-1" for="procedureType">Procedure</label>
                            <select id="procedureType" class="clinic-input">
                                <option value="">All</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs clinic-subtle mb-1" for="billStatus">Payment Status</label>
                            <select id="billStatus" class="clinic-input">
                                <option value="">All</option>
                                <option value="unpaid">Unpaid</option>
                                <option value="partial">Partial</option>
                                <option value="paid">Paid</option>
                                <option value="overdue">Overdue</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div id="filterMsg" class="text-sm mt-3"></div>
            </div>

            <div class="grid md:grid-cols-4 gap-4 mb-6">
                <div class="clinic-card">
                    <div class="text-xs clinic-subtle">Total Patients</div>
                    <div class="text-2xl font-semibold mt-1" id="cardPatients">-</div>
                    <div class="text-xs clinic-subtle mt-1" id="cardPatientsHint"></div>
                </div>
                <div class="clinic-card">
                    <div class="text-xs clinic-subtle">Appointments (Range)</div>
                    <div class="text-2xl font-semibold mt-1" id="cardAppointments">-</div>
                    <div class="text-xs clinic-subtle mt-1" id="cardAppointmentsHint"></div>
                </div>
                <div class="clinic-card">
                    <div class="text-xs clinic-subtle">Revenue (Range)</div>
                    <div class="text-2xl font-semibold mt-1" id="cardRevenue">-</div>
                    <div class="text-xs clinic-subtle mt-1" id="cardRevenueHint"></div>
                </div>
                <div class="clinic-card">
                    <div class="text-xs clinic-subtle">Returning Patient Rate</div>
                    <div class="text-2xl font-semibold mt-1" id="cardRetention">-</div>
                    <div class="text-xs clinic-subtle mt-1" id="cardRetentionHint"></div>
                </div>
                <div class="clinic-card">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-xs clinic-subtle">Follow-ups Due</div>
                            <div class="text-2xl font-semibold mt-1" id="cardFollowUpsDue">-</div>
                            <div class="text-xs clinic-subtle mt-1" id="cardFollowUpsDueHint"></div>
                        </div>
                        <button id="followUpsDrillBtn" class="btn" type="button">View</button>
                    </div>
                </div>
            </div>

            <div class="grid lg:grid-cols-12 gap-6">
                <div class="clinic-card lg:col-span-7">
                    <div class="flex items-center justify-between gap-3 mb-4">
                        <div>
                            <h2 class="font-medium">Most Common Procedures</h2>
                            <div class="text-sm clinic-subtle">Top 5 ranked with share. Click to see patients.</div>
                        </div>
                        <span class="pill" id="proceduresTotalPill">-</span>
                    </div>
                    <div id="proceduresList" class="space-y-3"></div>
                    <div id="proceduresEmpty" class="text-sm clinic-subtle hidden rounded-xl border p-3" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                        No procedures found in range.
                    </div>
                </div>

                <div class="clinic-card lg:col-span-5">
                    <div class="flex items-center justify-between gap-3 mb-4">
                        <div>
                            <h2 class="font-medium">Returning vs New Patients</h2>
                            <div class="text-sm clinic-subtle">Business health in one donut. Click for details.</div>
                        </div>
                        <span class="pill" id="retentionPill">-</span>
                    </div>

                    <div class="flex items-center gap-4">
                        <svg width="120" height="120" viewBox="0 0 120 120" class="shrink-0">
                            <circle cx="60" cy="60" r="44" stroke="rgba(148,163,184,.35)" stroke-width="14" fill="none"></circle>
                            <circle id="donutReturning" cx="60" cy="60" r="44" stroke="rgb(14,116,144)" stroke-width="14" fill="none" stroke-linecap="round" transform="rotate(-90 60 60)"></circle>
                        </svg>
                        <div class="flex-1">
                            <div class="text-sm font-medium" id="retentionHeadline">-</div>
                            <div class="text-sm clinic-subtle mt-1" id="retentionMeta">-</div>
                            <button id="retentionDrillBtn" class="btn mt-3">View Breakdown</button>
                        </div>
                    </div>
                </div>

                <div class="clinic-card lg:col-span-7">
                    <div class="flex items-center justify-between gap-3 mb-4">
                        <div>
                            <h2 class="font-medium">Revenue per Month</h2>
                            <div class="text-sm clinic-subtle">12-month trend. Click a month for receipts.</div>
                        </div>
                        <span class="pill" id="revenuePill">-</span>
                    </div>

                    <div class="rounded-xl border p-3" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                        <svg id="revenueSvg" viewBox="0 0 640 180" class="w-full h-[180px]">
                            <polyline id="revenueLine" fill="none" stroke="rgb(14,116,144)" stroke-width="3" stroke-linejoin="round"></polyline>
                            <g id="revenuePoints"></g>
                        </svg>
                        <div class="text-xs clinic-subtle mt-2" id="revenueHint"></div>
                    </div>
                </div>

                <div class="clinic-card lg:col-span-5">
                    <div class="flex items-center justify-between gap-3 mb-4">
                        <div>
                            <h2 class="font-medium">Peak Clinic Days</h2>
                            <div class="text-sm clinic-subtle">Heatmap by day-of-week. Click for schedule.</div>
                        </div>
                        <span class="pill" id="peakPill">-</span>
                    </div>

                    <div class="grid grid-cols-7 gap-2" id="peakGrid"></div>
                    <div class="text-xs clinic-subtle mt-3" id="peakHint"></div>
                </div>
            </div>
        </div>

        <div id="modalOverlay" class="fixed inset-0 hidden" style="background: rgba(2,6,23,.6);">
            <div class="max-w-3xl mx-auto p-4 md:p-8">
                <div class="clinic-card">
                    <div class="flex items-center justify-between gap-3 mb-4">
                        <div>
                            <div class="text-xs clinic-subtle" id="modalKicker"></div>
                            <h3 class="text-lg font-semibold leading-tight" id="modalTitle"></h3>
                        </div>
                        <button class="btn" id="modalClose">Close</button>
                    </div>
                    <div id="modalMsg" class="text-sm"></div>
                    <div class="overflow-x-auto rounded-xl border mt-3" style="border-color: var(--color-clinic-border);">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50/80 dark:bg-slate-950/40">
                                <tr class="text-left" id="modalHead"></tr>
                            </thead>
                            <tbody id="modalBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <a class="clinic-fab" href="/" aria-label="Book appointment">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 2v3M16 2v3M3 9h18"/><path d="M5 5h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"/><path d="M8 13h4M8 17h7"/></svg>
            <span>Book</span>
        </a>

        <script>
            const canChooseDentist = @json((bool) (auth()->user()?->hasPermission('analytics.choose_dentist') ?? false));
            const toggleThemeBtn = document.getElementById('toggleTheme');
            const fromDateEl = document.getElementById('fromDate');
            const toDateEl = document.getElementById('toDate');
            const dentistEl = document.getElementById('dentist');
            const procedureTypeEl = document.getElementById('procedureType');
            const billStatusEl = document.getElementById('billStatus');
            const filterMsgEl = document.getElementById('filterMsg');

            const cardPatientsEl = document.getElementById('cardPatients');
            const cardAppointmentsEl = document.getElementById('cardAppointments');
            const cardRevenueEl = document.getElementById('cardRevenue');
            const cardRetentionEl = document.getElementById('cardRetention');
            const cardPatientsHintEl = document.getElementById('cardPatientsHint');
            const cardAppointmentsHintEl = document.getElementById('cardAppointmentsHint');
            const cardRevenueHintEl = document.getElementById('cardRevenueHint');
            const cardRetentionHintEl = document.getElementById('cardRetentionHint');
            const cardFollowUpsDueEl = document.getElementById('cardFollowUpsDue');
            const cardFollowUpsDueHintEl = document.getElementById('cardFollowUpsDueHint');
            const followUpsDrillBtn = document.getElementById('followUpsDrillBtn');

            const proceduresTotalPillEl = document.getElementById('proceduresTotalPill');
            const proceduresListEl = document.getElementById('proceduresList');
            const proceduresEmptyEl = document.getElementById('proceduresEmpty');

            const donutReturningEl = document.getElementById('donutReturning');
            const retentionPillEl = document.getElementById('retentionPill');
            const retentionHeadlineEl = document.getElementById('retentionHeadline');
            const retentionMetaEl = document.getElementById('retentionMeta');
            const retentionDrillBtn = document.getElementById('retentionDrillBtn');

            const revenuePillEl = document.getElementById('revenuePill');
            const revenueSvgEl = document.getElementById('revenueSvg');
            const revenueLineEl = document.getElementById('revenueLine');
            const revenuePointsEl = document.getElementById('revenuePoints');
            const revenueHintEl = document.getElementById('revenueHint');

            const peakPillEl = document.getElementById('peakPill');
            const peakGridEl = document.getElementById('peakGrid');
            const peakHintEl = document.getElementById('peakHint');

            const modalOverlayEl = document.getElementById('modalOverlay');
            const modalCloseEl = document.getElementById('modalClose');
            const modalKickerEl = document.getElementById('modalKicker');
            const modalTitleEl = document.getElementById('modalTitle');
            const modalMsgEl = document.getElementById('modalMsg');
            const modalHeadEl = document.getElementById('modalHead');
            const modalBodyEl = document.getElementById('modalBody');

            let refreshTimer = null;
            let lastRevenueMonths = [];
            let lastPeakDays = [];
            let lastFollowUpsDue = { total: 0, items: [] };

            const currency = new Intl.NumberFormat(undefined, { style: 'currency', currency: 'PHP' });

            function setFilterMsg(text, type) {
                filterMsgEl.textContent = text || '';
                filterMsgEl.className = type === 'success'
                    ? 'text-sm mt-3 text-emerald-700 dark:text-emerald-300'
                    : type === 'error'
                        ? 'text-sm mt-3 text-red-700 dark:text-red-300'
                        : 'text-sm mt-3';
            }

            async function fetchJson(url, options) {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
                if (csrf) headers['X-CSRF-TOKEN'] = csrf;
                const res = await fetch(url, { headers, credentials: 'same-origin', ...options });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(data?.message || 'Request failed');
                return data;
            }

            function pad2(n) {
                return String(n).padStart(2, '0');
            }

            function dateToInputValue(d) {
                return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
            }

            function startOfMonth(d) {
                return new Date(d.getFullYear(), d.getMonth(), 1);
            }

            function endOfMonth(d) {
                return new Date(d.getFullYear(), d.getMonth() + 1, 0);
            }

            function buildParams(extra) {
                const params = new URLSearchParams();
                if (fromDateEl.value) params.set('from', fromDateEl.value);
                if (toDateEl.value) params.set('to', toDateEl.value);
                if (dentistEl.value) params.set('dentist_id', dentistEl.value);
                if (procedureTypeEl.value) params.set('procedure_type', procedureTypeEl.value);
                if (billStatusEl.value) params.set('bill_status', billStatusEl.value);
                for (const [k, v] of Object.entries(extra || {})) {
                    if (v !== null && v !== undefined && v !== '') params.set(k, String(v));
                }
                return params.toString();
            }

            async function loadDentists() {
                const res = await fetchJson('/api/dentists');
                const list = res?.data || [];
                dentistEl.innerHTML = '<option value="">All</option>';
                for (const d of list) {
                    const opt = document.createElement('option');
                    opt.value = String(d.id);
                    opt.textContent = d.name;
                    dentistEl.appendChild(opt);
                }

                if (!canChooseDentist) {
                    dentistEl.disabled = true;
                }
            }

            async function loadProcedureTypes() {
                const res = await fetchJson(`/api/analytics/procedures/types?${buildParams()}`);
                const list = res?.data || [];
                const current = procedureTypeEl.value;
                procedureTypeEl.innerHTML = '<option value="">All</option>';
                for (const row of list) {
                    const opt = document.createElement('option');
                    opt.value = String(row.procedure_type || '');
                    opt.textContent = `${row.procedure_type} (${row.count})`;
                    procedureTypeEl.appendChild(opt);
                }
                if (current) {
                    procedureTypeEl.value = current;
                }
            }

            function renderSummary(data) {
                cardPatientsEl.textContent = data?.total_patients ?? '-';
                cardAppointmentsEl.textContent = data?.appointments_in_range ?? '-';
                cardRevenueEl.textContent = typeof data?.revenue_cents === 'number' ? currency.format((data.revenue_cents || 0) / 100) : '-';
                cardRetentionEl.textContent = typeof data?.returning_patient_rate === 'number' ? `${data.returning_patient_rate}%` : '-';

                const range = data?.range ? `${data.range.from} → ${data.range.to}` : '';
                cardPatientsHintEl.textContent = 'All time';
                cardAppointmentsHintEl.textContent = range;
                cardRevenueHintEl.textContent = range;
                cardRetentionHintEl.textContent = range;
            }

            function renderFollowUpsDue(payload) {
                const total = typeof payload?.total === 'number' ? payload.total : 0;
                lastFollowUpsDue = {
                    total,
                    items: Array.isArray(payload?.items) ? payload.items : [],
                };
                cardFollowUpsDueEl.textContent = String(total);
                cardFollowUpsDueHintEl.textContent = `As of ${dateToInputValue(new Date())}`;
                followUpsDrillBtn.disabled = total === 0;
            }

            function renderTopProcedures(data) {
                const total = data?.total || 0;
                proceduresTotalPillEl.textContent = `${total} total`;
                proceduresListEl.innerHTML = '';
                proceduresEmptyEl.classList.toggle('hidden', total > 0);

                const top = data?.top || [];
                for (const row of top) {
                    const wrap = document.createElement('div');
                    wrap.className = 'rounded-xl border p-3';
                    wrap.style.borderColor = 'var(--color-clinic-border)';
                    wrap.style.background = 'var(--color-clinic-surface-2)';

                    const header = document.createElement('div');
                    header.className = 'flex items-center justify-between gap-3';

                    const left = document.createElement('div');
                    const title = document.createElement('div');
                    title.className = 'text-sm font-medium';
                    title.textContent = row.procedure_type;
                    const meta = document.createElement('div');
                    meta.className = 'text-xs clinic-subtle';
                    meta.textContent = `${row.count} • ${row.share_percent}%`;
                    left.appendChild(title);
                    left.appendChild(meta);

                    const btn = document.createElement('button');
                    btn.className = 'btn';
                    btn.type = 'button';
                    btn.textContent = 'View';
                    btn.addEventListener('click', () => openProcedurePatients(row.procedure_type));

                    header.appendChild(left);
                    header.appendChild(btn);

                    const barTrack = document.createElement('div');
                    barTrack.className = 'mt-3 h-2 rounded-full';
                    barTrack.style.background = 'rgba(148,163,184,.25)';

                    const bar = document.createElement('div');
                    bar.className = 'h-2 rounded-full';
                    bar.style.background = 'rgb(14,116,144)';
                    bar.style.width = `${Math.max(2, Math.min(100, row.share_percent || 0))}%`;
                    barTrack.appendChild(bar);

                    wrap.appendChild(header);
                    wrap.appendChild(barTrack);
                    proceduresListEl.appendChild(wrap);
                }
            }

            function renderRetention(data) {
                const total = data?.total || 0;
                const returning = data?.returning || 0;
                const pct = total > 0 ? returning / total : 0;
                const pctLabel = total > 0 ? `${Math.round(pct * 1000) / 10}% returning` : 'No data';

                retentionPillEl.textContent = `${total} patients`;
                retentionHeadlineEl.textContent = pctLabel;
                retentionMetaEl.textContent = `Returning: ${returning} • New: ${data?.new || 0}`;

                const r = 44;
                const c = 2 * Math.PI * r;
                donutReturningEl.setAttribute('stroke-dasharray', `${pct * c} ${c}`);
                donutReturningEl.setAttribute('stroke-dashoffset', '0');

                retentionDrillBtn.disabled = total === 0;
            }

            function renderRevenueMonthly(data) {
                const months = data?.months || [];
                lastRevenueMonths = months;

                const max = data?.max_cents || 0;
                revenuePillEl.textContent = `${months.length} months`;
                revenueHintEl.textContent = `Range: ${data?.from || ''} → ${data?.to || ''}`;

                const w = 640;
                const h = 180;
                const padding = 24;
                const innerW = w - padding * 2;
                const innerH = h - padding * 2;

                function xAt(i) {
                    if (months.length <= 1) return padding;
                    return padding + (i / (months.length - 1)) * innerW;
                }

                function yAt(v) {
                    const t = max > 0 ? v / max : 0;
                    return padding + (1 - t) * innerH;
                }

                const pts = months.map((m, i) => `${xAt(i)},${yAt(m.total_cents || 0)}`);
                revenueLineEl.setAttribute('points', pts.join(' '));

                revenuePointsEl.innerHTML = '';
                for (let i = 0; i < months.length; i++) {
                    const m = months[i];
                    const cx = xAt(i);
                    const cy = yAt(m.total_cents || 0);
                    const dot = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                    dot.setAttribute('cx', String(cx));
                    dot.setAttribute('cy', String(cy));
                    dot.setAttribute('r', '5');
                    dot.setAttribute('fill', 'rgb(14,116,144)');
                    dot.style.cursor = 'pointer';
                    dot.addEventListener('click', () => openRevenueReceipts(m.month));
                    revenuePointsEl.appendChild(dot);
                }
            }

            function dayLabel(dow) {
                const map = { 1: 'Mon', 2: 'Tue', 3: 'Wed', 4: 'Thu', 5: 'Fri', 6: 'Sat', 7: 'Sun' };
                return map[dow] || String(dow);
            }

            function renderPeakDays(data) {
                const days = data?.days || [];
                lastPeakDays = days;
                const max = data?.max || 0;

                peakPillEl.textContent = `${days.reduce((a, d) => a + (d.count || 0), 0)} appts`;
                peakHintEl.textContent = 'Darker = busier';

                peakGridEl.innerHTML = '';
                for (const d of days) {
                    const pct = max > 0 ? (d.count || 0) / max : 0;
                    const cell = document.createElement('button');
                    cell.type = 'button';
                    cell.className = 'rounded-xl border p-2 text-left';
                    cell.style.borderColor = 'var(--color-clinic-border)';
                    cell.style.background = `rgba(14,116,144,${0.12 + pct * 0.38})`;
                    cell.addEventListener('click', () => openPeakDayAppointments(d.day_of_week));

                    const label = document.createElement('div');
                    label.className = 'text-[11px] clinic-subtle';
                    label.textContent = dayLabel(d.day_of_week);

                    const value = document.createElement('div');
                    value.className = 'text-sm font-medium mt-1';
                    value.textContent = String(d.count || 0);

                    cell.appendChild(label);
                    cell.appendChild(value);
                    peakGridEl.appendChild(cell);
                }
            }

            function openModal({ kicker, title, columns, rows }) {
                modalKickerEl.textContent = kicker || '';
                modalTitleEl.textContent = title || '';
                modalMsgEl.textContent = '';
                modalMsgEl.className = 'text-sm';
                modalHeadEl.innerHTML = '';
                modalBodyEl.innerHTML = '';

                for (const c of columns) {
                    const th = document.createElement('th');
                    th.className = 'px-3 py-2 font-medium';
                    th.textContent = c;
                    modalHeadEl.appendChild(th);
                }

                for (const row of rows) {
                    const tr = document.createElement('tr');
                    tr.className = 'border-t';
                    tr.style.borderColor = 'var(--color-clinic-border)';
                    for (const cellVal of row) {
                        const td = document.createElement('td');
                        td.className = 'px-3 py-2';
                        td.textContent = cellVal;
                        tr.appendChild(td);
                    }
                    modalBodyEl.appendChild(tr);
                }

                modalOverlayEl.classList.remove('hidden');
            }

            function closeModal() {
                modalOverlayEl.classList.add('hidden');
            }

            modalCloseEl.addEventListener('click', closeModal);
            modalOverlayEl.addEventListener('click', (e) => {
                if (e.target === modalOverlayEl) closeModal();
            });

            async function openProcedurePatients(procedureType) {
                try {
                    openModal({ kicker: 'Clickable Analytics', title: `Patients for ${procedureType}`, columns: ['Patient', 'Count', 'Last Performed'], rows: [] });
                    const res = await fetchJson(`/api/analytics/procedures/${encodeURIComponent(procedureType)}/patients?${buildParams()}`);
                    const rows = (res?.data?.patients || []).map((r) => [
                        r.patient_name,
                        String(r.count),
                        r.last_performed_at ? new Date(r.last_performed_at).toLocaleString() : '-',
                    ]);
                    openModal({ kicker: 'Clickable Analytics', title: `Patients for ${procedureType}`, columns: ['Patient', 'Count', 'Last Performed'], rows: rows.length ? rows : [['No results', '', '']] });
                } catch (e) {
                    openModal({ kicker: 'Clickable Analytics', title: `Patients for ${procedureType}`, columns: ['Error'], rows: [[e?.message || 'Not allowed']] });
                }
            }

            async function openPeakDayAppointments(dayOfWeek) {
                try {
                    openModal({ kicker: 'Clickable Analytics', title: `Schedule for ${dayLabel(dayOfWeek)}`, columns: ['Start', 'Patient', 'Service', 'Dentist', 'Status'], rows: [] });
                    const res = await fetchJson(`/api/analytics/peak-days/${encodeURIComponent(dayOfWeek)}/appointments?${buildParams()}`);
                    const rows = (res?.data?.appointments || []).map((a) => [
                        a.start_at ? new Date(a.start_at).toLocaleString() : '-',
                        a.patient_name || '-',
                        a.service_name || '-',
                        a.dentist_name || '-',
                        String(a.status || ''),
                    ]);
                    openModal({ kicker: 'Clickable Analytics', title: `Schedule for ${dayLabel(dayOfWeek)}`, columns: ['Start', 'Patient', 'Service', 'Dentist', 'Status'], rows: rows.length ? rows : [['No results', '', '', '', '']] });
                } catch (e) {
                    openModal({ kicker: 'Clickable Analytics', title: `Schedule for ${dayLabel(dayOfWeek)}`, columns: ['Error'], rows: [[e?.message || 'Not allowed']] });
                }
            }

            async function openRevenueReceipts(month) {
                try {
                    openModal({ kicker: 'Clickable Analytics', title: `Receipts for ${month}`, columns: ['Paid At', 'Patient', 'Method', 'Amount', 'Receipt #', 'Bill Status'], rows: [] });
                    const res = await fetchJson(`/api/analytics/revenue/${encodeURIComponent(month)}/receipts?${buildParams()}`);
                    const rows = (res?.data?.receipts || []).map((r) => [
                        r.paid_at ? new Date(r.paid_at).toLocaleString() : '-',
                        r.patient_name || '-',
                        r.method || '-',
                        currency.format((r.amount_cents || 0) / 100),
                        r.receipt_number ? String(r.receipt_number) : '-',
                        r.bill_status || '-',
                    ]);
                    openModal({ kicker: 'Clickable Analytics', title: `Receipts for ${month}`, columns: ['Paid At', 'Patient', 'Method', 'Amount', 'Receipt #', 'Bill Status'], rows: rows.length ? rows : [['No results', '', '', '', '', '']] });
                } catch (e) {
                    openModal({ kicker: 'Clickable Analytics', title: `Receipts for ${month}`, columns: ['Error'], rows: [[e?.message || 'Not allowed']] });
                }
            }

            followUpsDrillBtn.addEventListener('click', async () => {
                try {
                    openModal({ kicker: 'Follow-ups', title: 'Follow-ups Due', columns: ['Due', 'Patient', 'Procedure', 'Performed', 'Dentist'], rows: [] });
                    const res = await fetchJson('/api/follow-ups/due?only_due=1&limit=200');
                    const items = res?.data?.items || [];
                    const rows = (items || []).map((x) => [
                        x.follow_up_suggested_at || '-',
                        x.patient_name || '-',
                        x.procedure_type || '-',
                        x.performed_at ? new Date(x.performed_at).toLocaleDateString() : '-',
                        x.dentist_name || '-',
                    ]);
                    openModal({ kicker: 'Follow-ups', title: 'Follow-ups Due', columns: ['Due', 'Patient', 'Procedure', 'Performed', 'Dentist'], rows: rows.length ? rows : [['No results', '', '', '', '']] });
                } catch (e) {
                    openModal({ kicker: 'Follow-ups', title: 'Follow-ups Due', columns: ['Error'], rows: [[e?.message || 'Failed']] });
                }
            });

            retentionDrillBtn.addEventListener('click', async () => {
                try {
                    const res = await fetchJson(`/api/analytics/patients/retention?${buildParams()}`);
                    const d = res?.data || {};
                    openModal({
                        kicker: 'Retention',
                        title: 'Returning vs New Patients',
                        columns: ['Metric', 'Value'],
                        rows: [
                            ['Total', String(d.total || 0)],
                            ['Returning', String(d.returning || 0)],
                            ['New', String(d.new || 0)],
                        ],
                    });
                } catch (e) {
                    openModal({ kicker: 'Retention', title: 'Returning vs New Patients', columns: ['Error'], rows: [[e?.message || 'Failed']] });
                }
            });

            async function refresh() {
                try {
                    setFilterMsg('Loading...', '');

                    await loadProcedureTypes();

                    const [summary, procedures, peak, revenue, retention, followUpsDue] = await Promise.all([
                        fetchJson(`/api/analytics/summary?${buildParams()}`),
                        fetchJson(`/api/analytics/procedures/top?${buildParams()}`),
                        fetchJson(`/api/analytics/appointments/peak-days?${buildParams()}`),
                        fetchJson(`/api/analytics/revenue/monthly?${buildParams({ to: toDateEl.value || undefined, months: 12 })}`),
                        fetchJson(`/api/analytics/patients/retention?${buildParams()}`),
                        fetchJson('/api/follow-ups/due?only_due=1&limit=1'),
                    ]);

                    renderSummary(summary?.data || {});
                    renderTopProcedures(procedures?.data || {});
                    renderPeakDays(peak?.data || {});
                    renderRevenueMonthly(revenue?.data || {});
                    renderRetention(retention?.data || {});
                    renderFollowUpsDue(followUpsDue?.data || {});

                    setFilterMsg('', '');
                } catch (e) {
                    setFilterMsg(e?.message || 'Failed to load analytics.', 'error');
                }
            }

            function scheduleRefresh() {
                if (refreshTimer) window.clearTimeout(refreshTimer);
                refreshTimer = window.setTimeout(refresh, 250);
            }

            fromDateEl.addEventListener('change', scheduleRefresh);
            toDateEl.addEventListener('change', scheduleRefresh);
            dentistEl.addEventListener('change', scheduleRefresh);
            procedureTypeEl.addEventListener('change', scheduleRefresh);
            billStatusEl.addEventListener('change', scheduleRefresh);

            const now = new Date();
            fromDateEl.value = dateToInputValue(startOfMonth(now));
            toDateEl.value = dateToInputValue(endOfMonth(now));

            loadDentists()
                .then(refresh)
                .catch((e) => setFilterMsg(e?.message || 'Failed to initialize.', 'error'));
        </script>
    </body>
</html>
