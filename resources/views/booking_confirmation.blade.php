<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php
            $contextInterface = $currentInterface ?? 'client';
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

        <title>{{ config('app.name', 'Skye Dental') }} - Booking Confirmation</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="clinic-shell">
        <div class="max-w-3xl mx-auto p-4 md:p-8">
            <div class="clinic-header mb-6">
                <div class="clinic-brand">
                    <img src="{{ asset('logo.png') }}" alt="{{ config('app.name', 'Skye Dental') }} logo" class="h-11 w-11 rounded-2xl" style="box-shadow: var(--shadow-clinic-card);" />
                    <div>
                        <div class="text-xs clinic-subtle tracking-wide uppercase">{{ config('app.name', 'Skye Dental') }}</div>
                        <h1 class="text-2xl md:text-3xl font-semibold leading-tight">Booking Confirmation</h1>
                        <p class="text-sm clinic-subtle mt-1">Show this booking reference code at the clinic.</p>
                    </div>
                </div>
                <div class="btn-group">
                    <button id="toggleTheme" type="button" class="btn">Dark Mode</button>
                    <span class="pill">Client Interface</span>
                    <a href="/" class="btn">Back to Booking</a>
                </div>
            </div>

            <div class="clinic-card">
                <div class="rounded-xl border p-4" style="border-color: rgba(14,116,144,.25); background: rgba(14,116,144,.08);">
                    <div class="text-xs clinic-subtle">Booking Reference</div>
                    <div class="mt-2 text-2xl md:text-3xl font-mono font-semibold tracking-wide" id="code"></div>
                    <div class="text-xs clinic-subtle mt-2">Save a screenshot. This code is your proof of booking.</div>
                </div>

                <div id="msg" class="text-sm mt-4" role="status" aria-live="polite"></div>

                <div class="mt-4 grid gap-4">
                    <div class="rounded-xl border p-4" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                        <div class="text-sm font-medium">Appointment Details</div>
                        <div class="text-sm clinic-subtle mt-2" id="details">Loading...</div>
                    </div>

                    <div class="rounded-xl border p-4" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                        <div class="text-sm font-medium">Clinic Rules</div>
                        <ul class="text-sm clinic-subtle mt-2 space-y-1">
                            <li>Arrive 10–15 minutes early for processing.</li>
                            <li>If you need to reschedule, contact the clinic ahead of time.</li>
                            <li>Bring this booking reference code for fast check-in.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <a class="clinic-fab" href="/" aria-label="Book appointment">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 2v3M16 2v3M3 9h18"/><path d="M5 5h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"/><path d="M8 13h4M8 17h7"/></svg>
            <span>Book</span>
        </a>

        <script>
            const codeEl = document.getElementById('code');
            const msgEl = document.getElementById('msg');
            const detailsEl = document.getElementById('details');
            const initialCode = @json($bookingReferenceCode ?? '');

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

            function isoToLabel(iso) {
                const d = new Date(iso);
                if (Number.isNaN(d.getTime())) return iso;
                return d.toLocaleString();
            }

            async function load() {
                const raw = String(initialCode || '').trim();
                const code = raw.toUpperCase();
                codeEl.textContent = code || '-';

                if (!code) {
                    setMsg('Invalid confirmation link.', 'error');
                    detailsEl.textContent = 'No booking reference provided.';
                    return;
                }

                try {
                    setMsg('', '');
                    const res = await fetchJson(`/api/appointments/reference/${encodeURIComponent(code)}`);
                    const appt = res?.data;
                    const patient = appt?.patient?.full_name || appt?.patient_name || '-';
                    const service = appt?.service?.name || '-';
                    const dentist = appt?.dentist?.name || '-';
                    const startAt = appt?.start_at ? isoToLabel(appt.start_at) : '-';
                    const status = String(appt?.status || '').toUpperCase();

                    detailsEl.textContent = `${startAt} • ${service} • ${dentist} • ${patient} • Status: ${status}`;
                } catch (e) {
                    setMsg(e?.message || 'Could not load booking details.', 'error');
                    detailsEl.textContent = 'Details unavailable.';
                }
            }

            load();
        </script>
    </body>
</html>
