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

        <title>{{ config('app.name', 'Skye Dental') }} - Booking</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="clinic-shell">
        <div class="max-w-6xl mx-auto p-4 md:p-8">
            <div class="clinic-header mb-8">
                <div class="clinic-brand">
                    <img src="{{ asset('logo.png') }}" alt="{{ config('app.name', 'Skye Dental') }} logo" class="h-11 w-11 rounded-2xl" style="box-shadow: var(--shadow-clinic-card);" />
                    <div>
                        <div class="text-xs clinic-subtle tracking-wide uppercase">{{ config('app.name', 'Skye Dental') }}</div>
                        <h1 class="text-2xl md:text-3xl font-semibold leading-tight">Online Booking</h1>
                        <p class="text-sm clinic-subtle mt-1">No account needed. Pick service, dentist, date, then a slot.</p>
                    </div>
                </div>
                <div class="btn-group">
                    <button id="toggleTheme" type="button" class="btn">Dark Mode</button>
                    <span class="pill">Client Interface</span>
                    <a href="/welcome" class="btn">Welcome</a>
                </div>
            </div>

            <div class="grid lg:grid-cols-12 gap-6">
                <div class="clinic-card lg:col-span-7">
                    <div class="flex items-center justify-between gap-3 mb-4">
                        <h2 class="font-medium">Appointment Details</h2>
                        <span class="pill">Step 1 of 2</span>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-1" for="service">Service</label>
                            <select id="service" class="clinic-input">
                                <option value="">Loading...</option>
                            </select>
                            <div id="serviceMeta" class="text-xs clinic-subtle mt-1"></div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1" for="dentist">Dentist</label>
                            <select id="dentist" class="clinic-input">
                                <option value="">Loading...</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1" for="date">Preferred Date</label>
                            <input id="date" type="date" class="clinic-input" />
                        </div>
                    </div>

                    <div class="mt-6">
                        <div class="flex items-center justify-between gap-3 mb-2">
                            <h3 class="font-medium">Available Slots</h3>
                            <span class="pill" id="slotsHint">Pick a time</span>
                        </div>
                        <div id="slots" class="grid grid-cols-2 sm:grid-cols-4 gap-2"></div>
                        <div id="slotsEmpty" class="text-sm clinic-subtle hidden rounded-xl border p-3" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                            No available slots for the selected day.
                        </div>
                    </div>
                </div>

                <div class="clinic-card lg:col-span-5">
                    <div class="flex items-center justify-between gap-3 mb-4">
                        <h2 class="font-medium">Patient Info</h2>
                        <span class="pill">Step 2 of 2</span>
                    </div>

                    <form id="bookingForm" class="space-y-4">
                        <input type="hidden" id="startAt" />

                        <div>
                            <label class="block text-sm font-medium mb-1" for="patientName">Full Name</label>
                            <input id="patientName" class="clinic-input" placeholder="Your name" required />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1" for="patientPhone">Contact Number</label>
                                <input id="patientPhone" class="clinic-input" placeholder="09xxxxxxxxx" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" for="patientEmail">Email</label>
                                <input id="patientEmail" type="email" class="clinic-input" placeholder="you@email.com" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1" for="notes">Notes (optional)</label>
                            <textarea id="notes" rows="3" class="clinic-input" placeholder="Any notes..."></textarea>
                        </div>

                        <button type="submit" class="w-full btn btn-primary" id="submitBtn" disabled>
                            Confirm Booking
                        </button>

                        <div id="message" class="text-sm" role="status" aria-live="polite"></div>
                        <div id="confirmationBlock" class="hidden rounded-xl border p-3" style="border-color: rgba(16,185,129,.25); background: rgba(16,185,129,.08);">
                            <div class="text-xs clinic-subtle">Booking Reference</div>
                            <div class="mt-1 flex items-center justify-between gap-3">
                                <div class="font-mono text-sm" id="confirmationCode"></div>
                                <a class="btn btn-teal" id="confirmationLink" href="#" target="_blank" rel="noopener">Open</a>
                            </div>
                            <div class="text-xs clinic-subtle mt-2">Screenshot this for clinic check-in.</div>
                        </div>
                        <div class="text-xs clinic-subtle">
                            Selected slot: <span id="selectedSlotText">None</span>
                        </div>

                        <div class="text-xs clinic-subtle rounded-xl border p-3" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                            Clinic hours: Mon–Fri, 9:00 AM – 5:00 PM
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <a class="clinic-fab" href="/" aria-label="Book appointment">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 2v3M16 2v3M3 9h18"/><path d="M5 5h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"/><path d="M8 13h4M8 17h7"/></svg>
            <span>Book</span>
        </a>

        <script>
            const serviceEl = document.getElementById('service');
            const serviceMetaEl = document.getElementById('serviceMeta');
            const dentistEl = document.getElementById('dentist');
            const dateEl = document.getElementById('date');
            const slotsEl = document.getElementById('slots');
            const slotsEmptyEl = document.getElementById('slotsEmpty');
            const startAtEl = document.getElementById('startAt');
            const bookingForm = document.getElementById('bookingForm');
            const messageEl = document.getElementById('message');
            const submitBtn = document.getElementById('submitBtn');
            const selectedSlotTextEl = document.getElementById('selectedSlotText');
            const confirmationBlockEl = document.getElementById('confirmationBlock');
            const confirmationCodeEl = document.getElementById('confirmationCode');
            const confirmationLinkEl = document.getElementById('confirmationLink');
            let selectedSlotBtn = null;

            function clearMessage() {
                messageEl.textContent = '';
                messageEl.className = 'text-sm';
            }

            function resetConfirmation() {
                confirmationBlockEl.classList.add('hidden');
                confirmationCodeEl.textContent = '';
                confirmationLinkEl.setAttribute('href', '#');
            }

            function setMessage(text, type) {
                messageEl.textContent = text;
                messageEl.className = type === 'success' ? 'text-sm text-emerald-700 dark:text-emerald-300' : 'text-sm text-red-700 dark:text-red-300';
            }

            function isoToTimeLabel(iso) {
                const d = new Date(iso);
                if (Number.isNaN(d.getTime())) return iso;
                return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            }

            function setSelectedSlot(startIso, endIso) {
                startAtEl.value = startIso;
                submitBtn.disabled = !startAtEl.value || !serviceEl.value || !dentistEl.value;
                selectedSlotTextEl.textContent = `${isoToTimeLabel(startIso)} - ${isoToTimeLabel(endIso)}`;
            }

            async function fetchJson(url, options) {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
                if (csrf) headers['X-CSRF-TOKEN'] = csrf;
                const res = await fetch(url, {
                    headers,
                    credentials: 'same-origin',
                    ...options,
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    const msg = data?.message || 'Request failed';
                    throw new Error(msg);
                }
                return data;
            }

            async function loadServices() {
                const { data } = await fetchJson('/api/services');
                serviceEl.innerHTML = '<option value="">Select service...</option>';
                for (const s of data) {
                    const opt = document.createElement('option');
                    opt.value = String(s.id);
                    opt.textContent = s.name;
                    opt.dataset.duration = String(s.duration_minutes ?? '');
                    opt.dataset.buffer = String(s.buffer_minutes ?? '');
                    serviceEl.appendChild(opt);
                }
            }

            async function loadDentists() {
                const { data } = await fetchJson('/api/dentists');
                dentistEl.innerHTML = '<option value="">Select dentist...</option>';
                for (const d of data) {
                    const opt = document.createElement('option');
                    opt.value = String(d.id);
                    opt.textContent = d.name;
                    dentistEl.appendChild(opt);
                }
            }

            function updateServiceMeta() {
                const opt = serviceEl.options[serviceEl.selectedIndex];
                const duration = opt?.dataset?.duration;
                const buffer = opt?.dataset?.buffer;
                if (!serviceEl.value || !duration) {
                    serviceMetaEl.textContent = '';
                    return;
                }
                const b = buffer ? Number(buffer) : 0;
                serviceMetaEl.textContent = `Duration: ${duration} mins${b > 0 ? ` + Buffer: ${b} mins` : ''}`;
            }

            async function loadSlots(options = {}) {
                if (options.clearMessage !== false) {
                    clearMessage();
                }
                if (options.resetConfirmation !== false) {
                    resetConfirmation();
                }
                slotsEl.innerHTML = '';
                slotsEmptyEl.classList.add('hidden');
                startAtEl.value = '';
                selectedSlotTextEl.textContent = 'None';
                submitBtn.disabled = true;
                selectedSlotBtn = null;

                if (!dentistEl.value || !serviceEl.value || !dateEl.value) {
                    return;
                }

                const url = `/api/dentists/${encodeURIComponent(dentistEl.value)}/availability?date=${encodeURIComponent(dateEl.value)}&service_id=${encodeURIComponent(serviceEl.value)}`;
                const { data } = await fetchJson(url);
                const slots = data?.slots || [];

                if (!slots.length) {
                    slotsEmptyEl.classList.remove('hidden');
                    return;
                }

                for (const slot of slots) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'btn w-full justify-center';
                    btn.textContent = isoToTimeLabel(slot.start_at);
                    btn.addEventListener('click', () => {
                        if (selectedSlotBtn) {
                            selectedSlotBtn.classList.remove('btn-teal');
                        }
                        selectedSlotBtn = btn;
                        btn.classList.add('btn-teal');
                        setSelectedSlot(slot.start_at, slot.service_end_at || slot.end_at);
                    });
                    slotsEl.appendChild(btn);
                }
            }

            bookingForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                clearMessage();
                resetConfirmation();

                if (!serviceEl.value || !dentistEl.value || !startAtEl.value) {
                    setMessage('Please select service, dentist, date, and a slot.', 'error');
                    return;
                }

                const payload = {
                    service_id: Number(serviceEl.value),
                    dentist_id: Number(dentistEl.value),
                    start_at: startAtEl.value,
                    patient_name: document.getElementById('patientName').value,
                    patient_phone: document.getElementById('patientPhone').value || null,
                    patient_email: document.getElementById('patientEmail').value || null,
                    notes: document.getElementById('notes').value || null,
                };

                submitBtn.disabled = true;
                submitBtn.textContent = 'Booking...';

                try {
                    const res = await fetchJson('/api/appointments', {
                        method: 'POST',
                        body: JSON.stringify(payload),
                    });
                    const code = res?.data?.booking_reference_code;
                    const confirmationUrl = res?.data?.confirmation_url;

                    setMessage('Booked successfully. Please save your booking reference code.', 'success');
                    if (code) {
                        confirmationCodeEl.textContent = code;
                        confirmationLinkEl.setAttribute('href', confirmationUrl || `/booking/${encodeURIComponent(code)}`);
                        confirmationBlockEl.classList.remove('hidden');
                    }
                    await loadSlots({ clearMessage: false, resetConfirmation: false });
                } catch (err) {
                    setMessage(err?.message || 'Booking failed.', 'error');
                } finally {
                    submitBtn.textContent = 'Confirm Booking';
                    submitBtn.disabled = !startAtEl.value;
                }
            });

            serviceEl.addEventListener('change', async () => {
                updateServiceMeta();
                await loadSlots();
            });
            dentistEl.addEventListener('change', loadSlots);
            dateEl.addEventListener('change', loadSlots);

            Promise.all([loadServices(), loadDentists()])
                .then(() => {
                    serviceEl.value = '';
                    dentistEl.value = '';
                    updateServiceMeta();
                })
                .catch((e) => setMessage(e.message || 'Failed to load data.', 'error'));
        </script>
    </body>
</html>
