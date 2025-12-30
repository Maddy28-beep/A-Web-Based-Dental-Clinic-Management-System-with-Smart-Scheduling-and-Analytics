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

        <title>{{ config('app.name', 'Skye Dental') }} - Appointments</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="clinic-shell">
        <div class="max-w-6xl mx-auto p-4 md:p-8">
            <div class="clinic-header mb-6">
                <div class="clinic-brand">
                    <img src="{{ asset('logo.png') }}" alt="{{ config('app.name', 'Skye Dental') }} logo" class="h-11 w-11 rounded-2xl" style="box-shadow: var(--shadow-clinic-card);" />
                    <div>
                        <div class="text-xs clinic-subtle tracking-wide uppercase">{{ config('app.name', 'Skye Dental') }}</div>
                        <h1 class="text-2xl md:text-3xl font-semibold leading-tight">Appointments Dashboard</h1>
                        <p class="text-sm clinic-subtle mt-1">Today’s bookings with fast status actions and check-in.</p>
                    </div>
                </div>
                <div class="btn-group">
                    <button id="toggleTheme" type="button" class="btn">Dark Mode</button>
                    <span class="pill">Staff Interface</span>
                    @if (auth()->user()?->hasPermission('appointments.checkin'))
                        <a href="/check-in" class="btn">Check-In</a>
                    @endif
                    @if (auth()->user()?->hasPermission('clinical.view'))
                        <a href="/charting" class="btn">Charting</a>
                    @endif
                    @if (auth()->user()?->hasPermission('billing.view'))
                        <a href="/billing" class="btn">Billing</a>
                    @endif
                    <a href="/" class="btn">Front Desk Booking</a>
                    <a href="/welcome" class="btn">Welcome</a>
                    <div class="hidden sm:block text-xs clinic-subtle px-2">
                        {{ auth()->user()->name }} ({{ auth()->user()->role }})
                    </div>
                    <form method="POST" action="/logout">
                        @csrf
                        <button type="submit" class="btn">Logout</button>
                    </form>
                </div>
            </div>

            <div class="grid lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-6">
                    <div class="clinic-card">
                        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-4">
                            <div>
                                <h2 class="text-base font-medium">Appointments</h2>
                                <div class="text-sm clinic-subtle">Filter by date and status. Use actions to update flow.</div>
                            </div>
                            <div class="flex gap-2 items-end">
                                <div>
                                    <label class="block text-xs clinic-subtle mb-1" for="fromDate">From</label>
                                    <input id="fromDate" type="date" class="clinic-input" />
                                </div>
                                <div>
                                    <label class="block text-xs clinic-subtle mb-1" for="toDate">To</label>
                                    <input id="toDate" type="date" class="clinic-input" />
                                </div>
                                <div>
                                    <label class="block text-xs clinic-subtle mb-1" for="status">Status</label>
                                    <select id="status" class="clinic-input">
                                        <option value="">All</option>
                                        <option value="booked">Booked</option>
                                        <option value="checked_in">Checked-In</option>
                                        <option value="in_treatment">In Treatment</option>
                                        <option value="completed">Completed</option>
                                        <option value="cancelled">Cancelled</option>
                                        <option value="no_show">No-Show</option>
                                    </select>
                                </div>
                                <button id="refreshBtn" class="btn">Refresh</button>
                            </div>
                        </div>

                        <div id="msg" class="text-sm"></div>
                        <div class="overflow-x-auto rounded-xl border" style="border-color: var(--color-clinic-border);">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50/80 dark:bg-slate-950/40">
                                    <tr class="text-left">
                                        <th class="px-3 py-2 font-medium">Time</th>
                                        <th class="px-3 py-2 font-medium">Patient</th>
                                        <th class="px-3 py-2 font-medium">Service</th>
                                        <th class="px-3 py-2 font-medium">Dentist</th>
                                        <th class="px-3 py-2 font-medium">Status</th>
                                        <th class="px-3 py-2 font-medium">Reference</th>
                                        <th class="px-3 py-2 font-medium">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="rows"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="clinic-card lg:sticky lg:top-6">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="font-medium">Selected</h2>
                        <div class="text-xs clinic-subtle">ID: <span id="selectedId" class="font-mono">None</span></div>
                    </div>

                    <div id="selectedEmpty" class="text-sm clinic-subtle">
                        Select an appointment row to view details and quick links.
                    </div>

                    <div id="selectedPanel" class="hidden space-y-3">
                        <div class="rounded-xl border p-3" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                            <div class="text-xs clinic-subtle">Booking Reference</div>
                            <div class="mt-1 flex items-center justify-between gap-3">
                                <div id="selectedCode" class="font-mono text-sm"></div>
                                <a id="selectedConfirmation" class="btn" href="#" target="_blank" rel="noopener">Patient View</a>
                            </div>
                        </div>

                        <div class="rounded-xl border p-3" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                            <div class="text-xs clinic-subtle">Details</div>
                            <div class="mt-2 text-sm" id="selectedDetails"></div>
                        </div>

                        <div class="rounded-xl border p-3" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                            <div class="text-xs clinic-subtle">Update Status</div>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button class="btn btn-teal" data-next="checked_in" id="statusCheckedInBtn">Check-In</button>
                                <button class="btn" data-next="in_treatment" id="statusInTreatmentBtn">In Treatment</button>
                                <button class="btn" data-next="completed" id="statusCompletedBtn">Completed</button>
                                <button class="btn" data-next="no_show" id="statusNoShowBtn">No-Show</button>
                                <button class="btn" data-next="cancelled" id="statusCancelledBtn">Cancelled</button>
                            </div>
                            <div class="text-xs clinic-subtle mt-2" id="selectedStatusHint"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <a class="clinic-fab" href="/" aria-label="Book appointment">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 2v3M16 2v3M3 9h18"/><path d="M5 5h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"/><path d="M8 13h4M8 17h7"/></svg>
            <span>Book</span>
        </a>

        <script>
            const fromDateEl = document.getElementById('fromDate');
            const toDateEl = document.getElementById('toDate');
            const statusEl = document.getElementById('status');
            const refreshBtn = document.getElementById('refreshBtn');
            const msgEl = document.getElementById('msg');
            const rowsEl = document.getElementById('rows');

            const selectedIdEl = document.getElementById('selectedId');
            const selectedEmptyEl = document.getElementById('selectedEmpty');
            const selectedPanelEl = document.getElementById('selectedPanel');
            const selectedCodeEl = document.getElementById('selectedCode');
            const selectedConfirmationEl = document.getElementById('selectedConfirmation');
            const selectedDetailsEl = document.getElementById('selectedDetails');
            const selectedStatusHintEl = document.getElementById('selectedStatusHint');

            const statusCheckedInBtn = document.getElementById('statusCheckedInBtn');
            const statusInTreatmentBtn = document.getElementById('statusInTreatmentBtn');
            const statusCompletedBtn = document.getElementById('statusCompletedBtn');
            const statusNoShowBtn = document.getElementById('statusNoShowBtn');
            const statusCancelledBtn = document.getElementById('statusCancelledBtn');

            const canClinicalView = @json((bool) auth()->user()?->hasPermission('clinical.view'));
            let selectedAppointment = null;

            function setMsg(text, type) {
                msgEl.textContent = text || '';
                msgEl.className = type === 'success'
                    ? 'text-sm text-emerald-700 dark:text-emerald-300'
                    : type === 'error'
                        ? 'text-sm text-red-700 dark:text-red-300'
                        : 'text-sm';
            }

            async function fetchJson(url, options) {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
                if (csrf) headers['X-CSRF-TOKEN'] = csrf;
                const res = await fetch(url, { headers, credentials: 'same-origin', ...options });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    throw new Error(data?.message || 'Request failed');
                }
                return data;
            }

            function pad2(n) {
                return String(n).padStart(2, '0');
            }

            function toDateInputValue(d) {
                return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
            }

            function isoToTimeLabel(iso) {
                const d = new Date(iso);
                if (Number.isNaN(d.getTime())) return iso;
                return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            }

            function statusLabel(status) {
                const s = String(status || '').toLowerCase();
                if (s === 'checked_in') return 'Checked-In';
                if (s === 'in_treatment') return 'In Treatment';
                if (s === 'no_show') return 'No-Show';
                return s ? s.charAt(0).toUpperCase() + s.slice(1) : '';
            }

            function renderSelected(appt) {
                selectedAppointment = appt;

                if (!appt) {
                    selectedIdEl.textContent = 'None';
                    selectedCodeEl.textContent = '';
                    selectedDetailsEl.textContent = '';
                    selectedStatusHintEl.textContent = '';
                    selectedConfirmationEl.setAttribute('href', '#');
                    selectedPanelEl.classList.add('hidden');
                    selectedEmptyEl.classList.remove('hidden');
                    return;
                }

                selectedIdEl.textContent = String(appt.id);
                const code = appt.booking_reference_code || '';
                selectedCodeEl.textContent = code;
                const patientId = appt.patient?.id || appt.patient_id || null;
                if (canClinicalView && patientId) {
                    selectedConfirmationEl.setAttribute('href', `/charting?patient_id=${encodeURIComponent(patientId)}`);
                } else {
                    selectedConfirmationEl.setAttribute('href', code ? `/check-in?code=${encodeURIComponent(code)}` : '#');
                }

                const time = appt.start_at ? isoToTimeLabel(appt.start_at) : '';
                const dentist = appt?.dentist?.name || '-';
                const service = appt?.service?.name || '-';
                const patient = appt.patient?.full_name || appt.patient_name || '-';
                const status = statusLabel(appt.status);
                const checkedInAt = appt.checked_in_at ? new Date(appt.checked_in_at).toLocaleString() : '-';

                selectedDetailsEl.textContent = `${time} • ${patient} • ${service} • ${dentist} • ${status} • Checked-In: ${checkedInAt}`;
                selectedStatusHintEl.textContent = `Current status: ${status}`;

                selectedPanelEl.classList.remove('hidden');
                selectedEmptyEl.classList.add('hidden');
            }

            function renderRows(items) {
                rowsEl.innerHTML = '';
                if (!Array.isArray(items) || !items.length) {
                    const tr = document.createElement('tr');
                    tr.innerHTML = '<td class="px-3 py-3 clinic-subtle" colspan="7">No appointments found.</td>';
                    rowsEl.appendChild(tr);
                    renderSelected(null);
                    return;
                }

                for (const appt of items) {
                    const tr = document.createElement('tr');
                    tr.className = 'border-t';
                    tr.style.borderColor = 'var(--color-clinic-border)';

                    const time = appt.start_at ? isoToTimeLabel(appt.start_at) : '-';
                    const patient = appt.patient?.full_name || appt.patient_name || '-';
                    const service = appt.service?.name || '-';
                    const dentist = appt.dentist?.name || '-';
                    const status = statusLabel(appt.status);
                    const code = appt.booking_reference_code || '-';

                    const actions = document.createElement('div');
                    actions.className = 'flex flex-wrap gap-2';

                    const selectBtn = document.createElement('button');
                    selectBtn.type = 'button';
                    selectBtn.className = 'btn';
                    selectBtn.textContent = 'Select';
                    selectBtn.addEventListener('click', () => renderSelected(appt));
                    actions.appendChild(selectBtn);

                    const checkInBtn = document.createElement('button');
                    checkInBtn.type = 'button';
                    checkInBtn.className = 'btn btn-teal';
                    checkInBtn.textContent = 'Check-In';
                    checkInBtn.disabled = !appt.booking_reference_code || ['checked_in', 'in_treatment', 'completed', 'cancelled', 'no_show'].includes(String(appt.status || '').toLowerCase());
                    checkInBtn.addEventListener('click', async () => {
                        try {
                            setMsg('', '');
                            await fetchJson('/api/appointments/check-in', {
                                method: 'POST',
                                body: JSON.stringify({ booking_reference_code: appt.booking_reference_code }),
                            });
                            await loadAppointments();
                        } catch (e) {
                            setMsg(e?.message || 'Check-in failed.', 'error');
                        }
                    });
                    actions.appendChild(checkInBtn);

                    const tdActions = document.createElement('td');
                    tdActions.className = 'px-3 py-2';
                    tdActions.appendChild(actions);

                    tr.innerHTML = `
                        <td class="px-3 py-2 font-mono text-xs">${time}</td>
                        <td class="px-3 py-2">${patient}</td>
                        <td class="px-3 py-2">${service}</td>
                        <td class="px-3 py-2">${dentist}</td>
                        <td class="px-3 py-2">${status}</td>
                        <td class="px-3 py-2 font-mono text-xs">${code}</td>
                    `;
                    tr.appendChild(tdActions);

                    rowsEl.appendChild(tr);
                }
            }

            async function loadAppointments() {
                try {
                    setMsg('Loading...', '');
                    const params = new URLSearchParams();
                    if (fromDateEl.value) params.set('from', fromDateEl.value);
                    if (toDateEl.value) params.set('to', toDateEl.value);
                    if (statusEl.value) params.set('status', statusEl.value);
                    params.set('limit', '200');

                    const res = await fetchJson(`/api/appointments?${params.toString()}`);
                    renderRows(res?.data || []);

                    if (selectedAppointment) {
                        const refreshed = (res?.data || []).find((a) => a.id === selectedAppointment.id);
                        renderSelected(refreshed || null);
                    }

                    setMsg('', '');
                } catch (e) {
                    setMsg(e?.message || 'Failed to load appointments.', 'error');
                    renderRows([]);
                }
            }

            async function updateSelectedStatus(next) {
                if (!selectedAppointment) return;
                try {
                    setMsg('', '');
                    await fetchJson(`/api/appointments/${encodeURIComponent(selectedAppointment.id)}/status`, {
                        method: 'PATCH',
                        body: JSON.stringify({ status: next }),
                    });
                    await loadAppointments();
                } catch (e) {
                    setMsg(e?.message || 'Status update failed.', 'error');
                }
            }

            statusCheckedInBtn.addEventListener('click', () => updateSelectedStatus('checked_in'));
            statusInTreatmentBtn.addEventListener('click', () => updateSelectedStatus('in_treatment'));
            statusCompletedBtn.addEventListener('click', () => updateSelectedStatus('completed'));
            statusNoShowBtn.addEventListener('click', () => updateSelectedStatus('no_show'));
            statusCancelledBtn.addEventListener('click', () => updateSelectedStatus('cancelled'));

            refreshBtn.addEventListener('click', loadAppointments);
            fromDateEl.addEventListener('change', loadAppointments);
            toDateEl.addEventListener('change', loadAppointments);
            statusEl.addEventListener('change', loadAppointments);

            const today = new Date();
            const todayStr = toDateInputValue(today);
            fromDateEl.value = todayStr;
            toDateEl.value = todayStr;

            loadAppointments();
        </script>
    </body>
</html>
