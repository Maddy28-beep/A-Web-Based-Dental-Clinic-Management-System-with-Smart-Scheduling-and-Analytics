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

        <title>{{ config('app.name', 'Skye Dental') }} - Check-In</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="clinic-shell">
        <div class="max-w-4xl mx-auto p-4 md:p-8">
            <div class="clinic-header mb-6">
                <div class="clinic-brand">
                    <img src="{{ asset('logo.png') }}" alt="{{ config('app.name', 'Skye Dental') }} logo" class="h-11 w-11 rounded-2xl" style="box-shadow: var(--shadow-clinic-card);" />
                    <div>
                        <div class="text-xs clinic-subtle tracking-wide uppercase">{{ config('app.name', 'Skye Dental') }}</div>
                        <h1 class="text-2xl md:text-3xl font-semibold leading-tight">Clinic Check-In</h1>
                        <p class="text-sm clinic-subtle mt-1">Enter or scan a booking reference code and check in.</p>
                    </div>
                </div>
                <div class="btn-group">
                    <button id="toggleTheme" type="button" class="btn">Dark Mode</button>
                    <span class="pill">Staff Interface</span>
                    <a href="/appointments-dashboard" class="btn">Appointments</a>
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

            <div class="clinic-card">
                <div class="grid sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium mb-1" for="code">Booking Reference</label>
                        <input id="code" class="clinic-input font-mono" placeholder="DENT-2025-1222-ABCD" autocomplete="off" />
                        <div class="text-xs clinic-subtle mt-1">Tip: paste the code or scan a QR/barcode that contains it.</div>
                    </div>
                    <div class="flex flex-col justify-end gap-2 sm:pt-6">
                        <button id="lookupBtn" type="button" class="btn w-full">Lookup</button>
                        <button id="checkinBtn" type="button" class="btn btn-teal w-full" disabled>Check-in</button>
                    </div>
                </div>

                <div id="msg" class="text-sm mt-4" role="status" aria-live="polite"></div>

                <div id="panel" class="hidden mt-4 rounded-xl border p-4" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-xs clinic-subtle">Appointment</div>
                            <div class="text-sm font-medium" id="panelTitle"></div>
                        </div>
                        <a id="patientView" class="btn" href="#" target="_blank" rel="noopener">Patient View</a>
                    </div>
                    <div class="text-sm clinic-subtle mt-2" id="panelMeta"></div>
                </div>
            </div>
        </div>

        <a class="clinic-fab" href="/" aria-label="Book appointment">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 2v3M16 2v3M3 9h18"/><path d="M5 5h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"/><path d="M8 13h4M8 17h7"/></svg>
            <span>Book</span>
        </a>

        <script>
            const codeEl = document.getElementById('code');
            const lookupBtn = document.getElementById('lookupBtn');
            const checkinBtn = document.getElementById('checkinBtn');
            const msgEl = document.getElementById('msg');
            const panelEl = document.getElementById('panel');
            const panelTitleEl = document.getElementById('panelTitle');
            const panelMetaEl = document.getElementById('panelMeta');
            const patientViewEl = document.getElementById('patientView');

            let appointment = null;

            function setMsg(text, type) {
                msgEl.textContent = text || '';
                msgEl.className = type === 'success'
                    ? 'text-sm mt-4 text-emerald-700 dark:text-emerald-300'
                    : type === 'error'
                        ? 'text-sm mt-4 text-red-700 dark:text-red-300'
                        : 'text-sm mt-4';
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

            function isoToTimeLabel(iso) {
                const d = new Date(iso);
                if (Number.isNaN(d.getTime())) return iso;
                return d.toLocaleString();
            }

            function render(appt) {
                appointment = appt;
                if (!appt) {
                    panelEl.classList.add('hidden');
                    checkinBtn.disabled = true;
                    return;
                }

                const code = appt.booking_reference_code || '';
                const patient = appt.patient?.full_name || appt.patient_name || '-';
                const service = appt.service?.name || '-';
                const dentist = appt.dentist?.name || '-';
                const startAt = appt.start_at ? isoToTimeLabel(appt.start_at) : '-';
                const status = String(appt.status || '').toUpperCase();

                panelTitleEl.textContent = `${patient} • ${service}`;
                panelMetaEl.textContent = `${startAt} • ${dentist} • Status: ${status}`;
                const patientId = appt.patient?.id || appt.patient_id || null;
                if (patientId) {
                    patientViewEl.setAttribute('href', `/charting?patient_id=${encodeURIComponent(patientId)}`);
                } else if (code) {
                    patientViewEl.setAttribute('href', `/check-in?code=${encodeURIComponent(code)}`);
                } else {
                    patientViewEl.setAttribute('href', '#');
                }

                panelEl.classList.remove('hidden');
                checkinBtn.disabled = !code || ['CANCELLED', 'NO_SHOW', 'COMPLETED', 'CHECKED_IN', 'IN_TREATMENT'].includes(status);
            }

            async function lookup() {
                try {
                    setMsg('', '');
                    render(null);
                    const raw = String(codeEl.value || '').trim();
                    if (!raw) {
                        setMsg('Enter a booking reference code.', 'error');
                        return;
                    }
                    const code = raw.toUpperCase();
                    const res = await fetchJson(`/api/appointments/reference/${encodeURIComponent(code)}`);
                    render(res?.data || null);
                    if (res?.data) {
                        const status = String(res?.data?.status || '').toLowerCase();
                        if (status === 'checked_in') {
                            setMsg('Already checked in.', 'success');
                        } else if (['cancelled', 'no_show', 'completed'].includes(status)) {
                            setMsg(`Booking is ${status.replace('_', ' ')}.`, 'error');
                        } else {
                            setMsg('Booking found. Press CHECK-IN to confirm arrival.', 'success');
                        }
                    }
                } catch (e) {
                    setMsg(e?.message || 'Lookup failed.', 'error');
                }
            }

            async function checkIn() {
                if (!appointment?.booking_reference_code) return;
                try {
                    setMsg('', '');
                    const res = await fetchJson('/api/appointments/check-in', {
                        method: 'POST',
                        body: JSON.stringify({ booking_reference_code: appointment.booking_reference_code }),
                    });
                    render(res?.data || null);
                    setMsg('Checked in successfully.', 'success');
                } catch (e) {
                    setMsg(e?.message || 'Check-in failed.', 'error');
                }
            }

            lookupBtn.addEventListener('click', (e) => {
                e.preventDefault();
                lookup();
            });

            checkinBtn.addEventListener('click', (e) => {
                e.preventDefault();
                checkIn();
            });

            codeEl.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    lookup();
                }
            });

            const params = new URLSearchParams(window.location.search);
            const initialCode = params.get('code');
            if (initialCode) {
                codeEl.value = initialCode;
                lookup();
            } else {
                codeEl.focus();
            }
        </script>
    </body>
</html>

