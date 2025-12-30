<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php
            $isStaff = (bool) ($showStaffLogin ?? false);
            $contextInterface = $currentInterface ?? ($isStaff ? 'staff' : 'client');
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

        <title>{{ config('app.name', 'Skye Dental') }}{{ $isStaff ? ' - Staff Login' : '' }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            .hero-slide {
                opacity: 0;
                transition: opacity 700ms ease;
            }

            .hero-slide.is-active {
                opacity: 1;
            }

            .hero-dot {
                width: 10px;
                height: 10px;
                border-radius: 999px;
                border: 1px solid rgba(255, 255, 255, 0.55);
                background: rgba(255, 255, 255, 0.20);
            }

            .hero-dot.is-active {
                background: rgba(255, 255, 255, 0.95);
                border-color: rgba(255, 255, 255, 0.95);
            }
        </style>
    </head>
    <body class="clinic-shell">
        <div class="max-w-6xl mx-auto px-4 md:px-8 py-6 md:py-10">
            <div class="clinic-header mb-6">
                <div class="clinic-brand">
                    <img src="{{ asset('logo.png') }}" alt="{{ config('app.name', 'Skye Dental') }} logo" class="h-11 w-11 rounded-2xl" style="box-shadow: var(--shadow-clinic-card);" />
                    <div>
                        <div class="text-xs clinic-subtle tracking-wide uppercase">{{ config('app.name', 'Skye Dental') }}</div>
                        <h1 class="text-2xl md:text-3xl font-semibold leading-tight">{{ $isStaff ? 'Staff Portal' : 'Welcome' }}</h1>
                        <p class="text-sm clinic-subtle mt-1">{{ $isStaff ? 'Sign in to access clinic dashboards and patient records.' : 'Bright smiles, healthy teeth.' }}</p>
                    </div>
                </div>
                <div class="btn-group">
                    <button id="toggleTheme" type="button" class="btn">Dark Mode</button>
                    <span class="pill">{{ $contextInterface === 'staff' ? 'Staff Interface' : 'Client Interface' }}</span>
                    @if ($isStaff)
                        <a href="/welcome" class="btn">Client Site</a>
                    @else
                        <a href="/welcome#services" class="btn hidden sm:inline-flex">Services</a>
                        <a href="/welcome#how" class="btn hidden sm:inline-flex">How to Book</a>
                        <a href="/welcome#reviews" class="btn hidden sm:inline-flex">Reviews</a>
                        <a href="/welcome#contact" class="btn hidden sm:inline-flex">Contact</a>
                        <a href="/" class="btn btn-primary">Book Appointment</a>
                    @endif
                </div>
            </div>

            @if ($isStaff)
                <section class="mt-6 grid lg:grid-cols-12 gap-6 items-start">
                    <div class="lg:col-span-5 clinic-card">
                        <div class="pill">Access</div>
                        <h2 class="text-xl font-semibold mt-3">Clinic staff login</h2>
                        <p class="text-sm clinic-subtle mt-2">Dentists, receptionists, and admins only.</p>
                        <div class="mt-4 text-xs clinic-subtle">For patient booking, use the client site.</div>
                        <a href="/welcome" class="btn mt-4">Back to Client Site</a>
                    </div>
                    <div class="lg:col-span-7 clinic-card">
                        <form method="POST" action="/login" class="space-y-4">
                            @csrf
                            <div class="grid sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium mb-1" for="loginEmail">Email</label>
                                    <input id="loginEmail" name="email" type="email" class="clinic-input" value="{{ old('email') }}" required />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1" for="loginPassword">Password</label>
                                    <input id="loginPassword" name="password" type="password" class="clinic-input" required />
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary w-full">Sign In</button>
                            @if ($errors->any())
                                <div class="text-sm text-red-700 dark:text-red-300">{{ $errors->first() }}</div>
                            @endif
                        </form>
                    </div>
                </section>
            @else
                <section class="rounded-3xl overflow-hidden clinic-card relative" aria-label="Hero" id="home">
                    <div class="relative h-[420px] sm:h-[520px]">
                        <img data-hero-slide src="{{ asset('pic1.jpg') }}" alt="Modern dental clinic interior" class="hero-slide is-active absolute inset-0 w-full h-full object-cover" />
                        <img data-hero-slide src="{{ asset('pic2.jpg') }}" alt="Clean dental tools and technology" class="hero-slide absolute inset-0 w-full h-full object-cover" />
                        <img data-hero-slide src="{{ asset('pic3.jpg') }}" alt="Patient booking and front desk check-in" class="hero-slide absolute inset-0 w-full h-full object-cover" />

                        <div class="absolute inset-0" style="background: linear-gradient(120deg, rgba(2,6,23,.78), rgba(2,6,23,.20));"></div>

                        <div class="absolute inset-0 p-6 md:p-10 flex items-end">
                            <div class="max-w-2xl">
                                <div class="pill" style="border-color: rgba(255,255,255,.25); background: rgba(255,255,255,.10); color: rgba(255,255,255,.92);">Dental Clinic</div>
                                <h2 class="text-3xl md:text-5xl font-semibold mt-4 text-white leading-tight">{{ config('app.name', 'Skye Dental') }}</h2>
                                <div class="text-white/90 text-base md:text-lg mt-2">Bright Smiles, Healthy Teeth</div>
                                <div class="text-sm mt-3" style="color: rgba(255,255,255,.85);">Book in minutes. No account needed. Get a unique reference code as proof.</div>

                                <div class="mt-6 flex flex-wrap items-center gap-2">
                                    <a href="/" class="btn btn-primary">Book Appointment</a>
                                    <a href="#services" class="btn btn-ghost">View Services</a>
                                </div>
                            </div>
                        </div>

                        <button type="button" data-hero-prev aria-label="Previous slide" class="btn btn-icon btn-ghost absolute left-4 top-1/2 -translate-y-1/2">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18 9 12l6-6"/></svg>
                        </button>
                        <button type="button" data-hero-next aria-label="Next slide" class="btn btn-icon btn-ghost absolute right-4 top-1/2 -translate-y-1/2">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
                        </button>

                        <div class="absolute bottom-5 left-1/2 -translate-x-1/2 flex items-center gap-2" data-hero-dots></div>
                    </div>
                </section>

                <section class="mt-4 clinic-card" aria-label="Stats strip">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div class="rounded-2xl border p-4" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                            <div class="flex items-center gap-2">
                                <span class="h-9 w-9 rounded-xl flex items-center justify-center" style="background: rgba(16,185,129,.14); color: rgb(5, 150, 105);">
                                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v3m0 12v3M4.22 5.22l2.12 2.12m11.32 11.32 2.12 2.12M3 12h3m12 0h3M4.22 18.78l2.12-2.12m11.32-11.32 2.12-2.12"/><path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z"/></svg>
                                </span>
                                <div>
                                    <div class="text-lg font-semibold leading-none">5+ Years</div>
                                    <div class="text-xs clinic-subtle mt-1">Experience</div>
                                </div>
                            </div>
                        </div>
                        <div class="rounded-2xl border p-4" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                            <div class="flex items-center gap-2">
                                <span class="h-9 w-9 rounded-xl flex items-center justify-center" style="background: rgba(37,99,235,.14); color: rgb(37, 99, 235);">
                                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 11c1.66 0 3-1.34 3-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3Z"/><path d="M8 11c1.66 0 3-1.34 3-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3Z"/><path d="M8 13c-2.21 0-4 1.34-4 3v2h8v-2c0-1.66-1.79-3-4-3Z"/><path d="M16 13c-2.21 0-4 1.34-4 3v2h8v-2c0-1.66-1.79-3-4-3Z"/></svg>
                                </span>
                                <div>
                                    <div class="text-lg font-semibold leading-none">1,000+</div>
                                    <div class="text-xs clinic-subtle mt-1">Patients</div>
                                </div>
                            </div>
                        </div>
                        <div class="rounded-2xl border p-4" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                            <div class="flex items-center gap-2">
                                <span class="h-9 w-9 rounded-xl flex items-center justify-center" style="background: rgba(245,158,11,.16); color: rgb(202, 138, 4);">
                                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2c-2.2 0-4 1.8-4 4v2H6a2 2 0 0 0-2 2v2c0 2.2 1.8 4 4 4h1l1 4h4l1-4h1c2.2 0 4-1.8 4-4v-2a2 2 0 0 0-2-2h-2V6c0-2.2-1.8-4-4-4Z"/></svg>
                                </span>
                                <div>
                                    <div class="text-lg font-semibold leading-none">10+ Services</div>
                                    <div class="text-xs clinic-subtle mt-1">Options</div>
                                </div>
                            </div>
                        </div>
                        <div class="rounded-2xl border p-4" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                            <div class="flex items-center gap-2">
                                <span class="h-9 w-9 rounded-xl flex items-center justify-center" style="background: rgba(239,68,68,.12); color: rgb(220, 38, 38);">
                                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2 2 7l10 5 10-5-10-5Z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                                </span>
                                <div>
                                    <div class="text-lg font-semibold leading-none">100%</div>
                                    <div class="text-xs clinic-subtle mt-1">Sterilized</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="mt-10" id="services">
                    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
                        <div>
                            <div class="pill">Services</div>
                            <h3 class="text-xl md:text-2xl font-semibold mt-3">Dental care for every smile</h3>
                            <div class="text-sm clinic-subtle mt-1">Choose a service, then pick your preferred date and time.</div>
                        </div>
                        <a href="/" class="btn btn-primary">Book Now</a>
                    </div>

                    @php
                        $fallbackServices = collect([
                            (object) ['name' => 'Dental Cleaning', 'duration_minutes' => 60, 'buffer_minutes' => 0, 'desc' => 'Prevent cavities and keep your gums healthy.'],
                            (object) ['name' => 'Tooth Extraction', 'duration_minutes' => 60, 'buffer_minutes' => 30, 'desc' => 'Safe removal with proper aftercare guidance.'],
                            (object) ['name' => 'Braces & Orthodontics', 'duration_minutes' => 90, 'buffer_minutes' => 30, 'desc' => 'Alignment options tailored to your needs.'],
                            (object) ['name' => 'Fillings & Restoration', 'duration_minutes' => 60, 'buffer_minutes' => 30, 'desc' => 'Restore function and protect affected teeth.'],
                            (object) ['name' => 'Teeth Whitening', 'duration_minutes' => 45, 'buffer_minutes' => 15, 'desc' => 'A brighter smile with safe, guided treatment.'],
                            (object) ['name' => 'Dental Consultation', 'duration_minutes' => 30, 'buffer_minutes' => 15, 'desc' => 'Discuss concerns and get a clear treatment plan.'],
                        ]);

                        $serviceItems = isset($services) && count($services)
                            ? collect($services)->where('is_active', true)->take(6)->values()->map(function ($s) {
                                $name = (string) ($s->name ?? 'Service');
                                $n = strtolower($name);
                                $desc = 'Comfort-first care with modern equipment.';
                                if (str_contains($n, 'clean')) {
                                    $desc = 'Prevent cavities and keep your gums healthy.';
                                } elseif (str_contains($n, 'extract')) {
                                    $desc = 'Safe removal with proper aftercare guidance.';
                                } elseif (str_contains($n, 'brace') || str_contains($n, 'ortho')) {
                                    $desc = 'Alignment options tailored to your needs.';
                                } elseif (str_contains($n, 'fill') || str_contains($n, 'restor')) {
                                    $desc = 'Restore function and protect affected teeth.';
                                } elseif (str_contains($n, 'white')) {
                                    $desc = 'A brighter smile with safe, guided treatment.';
                                } elseif (str_contains($n, 'consult')) {
                                    $desc = 'Discuss concerns and get a clear treatment plan.';
                                }
                                $s->desc = $desc;

                                return $s;
                            })
                            : $fallbackServices;
                    @endphp

                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3 mt-4">
                        @foreach ($serviceItems as $svc)
                            <div class="clinic-card">
                                <div class="flex items-start gap-3">
                                    <div class="h-11 w-11 rounded-2xl flex items-center justify-center" style="background: rgba(14,116,144,.12); color: rgb(14,116,144);">
                                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2c-2.2 0-4 1.8-4 4v2H6a2 2 0 0 0-2 2v2c0 2.2 1.8 4 4 4h1l1 4h4l1-4h1c2.2 0 4-1.8 4-4v-2a2 2 0 0 0-2-2h-2V6c0-2.2-1.8-4-4-4Z"/></svg>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-sm font-medium">{{ $svc->name }}</div>
                                        <div class="text-xs clinic-subtle mt-1">{{ $svc->desc ?? 'Comfort-first care with modern equipment.' }}</div>
                                        <div class="text-xs clinic-subtle mt-2">
                                            Typically {{ (int) ($svc->duration_minutes ?? 0) }} mins
                                            @if ((int) ($svc->buffer_minutes ?? 0) > 0)
                                                • Buffer {{ (int) $svc->buffer_minutes }} mins
                                            @endif
                                        </div>
                                        <div class="mt-4">
                                            <a href="/" class="btn">Book</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="mt-10 clinic-card" id="how">
                    <div class="grid lg:grid-cols-12 gap-6 items-center">
                        <div class="lg:col-span-7">
                            <div class="pill">How to Book</div>
                            <h3 class="text-xl md:text-2xl font-semibold mt-3">No account needed</h3>
                            <div class="text-sm clinic-subtle mt-1">Each booking generates a unique reference code you can screenshot or share.</div>

                            <div class="grid sm:grid-cols-3 gap-3 mt-5">
                                <div class="rounded-2xl border p-4" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                                    <div class="text-xs clinic-subtle uppercase tracking-wide">Step 1</div>
                                    <div class="text-sm font-medium mt-1">Select service</div>
                                    <div class="text-xs clinic-subtle mt-1">Pick the treatment you need.</div>
                                </div>
                                <div class="rounded-2xl border p-4" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                                    <div class="text-xs clinic-subtle uppercase tracking-wide">Step 2</div>
                                    <div class="text-sm font-medium mt-1">Pick date &amp; time</div>
                                    <div class="text-xs clinic-subtle mt-1">Choose a slot that fits your day.</div>
                                </div>
                                <div class="rounded-2xl border p-4" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                                    <div class="text-xs clinic-subtle uppercase tracking-wide">Step 3</div>
                                    <div class="text-sm font-medium mt-1">Get reference</div>
                                    <div class="text-xs clinic-subtle mt-1">Show it at the clinic for fast check-in.</div>
                                </div>
                            </div>

                            <div class="mt-5 flex flex-wrap gap-2 text-xs">
                                <span class="pill" style="border-color: rgba(16,185,129,.25); background: rgba(16,185,129,.08); color: rgb(5, 150, 105);">No account required</span>
                                <span class="pill" style="border-color: rgba(16,185,129,.25); background: rgba(16,185,129,.08); color: rgb(5, 150, 105);">Reference proof</span>
                                <span class="pill" style="border-color: rgba(16,185,129,.25); background: rgba(16,185,129,.08); color: rgb(5, 150, 105);">Fast check-in</span>
                            </div>
                        </div>
                        <div class="lg:col-span-5">
                            <div class="rounded-3xl overflow-hidden border" style="border-color: rgba(2,6,23,.10); box-shadow: var(--shadow-clinic-card);">
                                <img src="{{ asset('pic3.jpg') }}" alt="Booking proof flow" class="w-full h-64 object-cover" />
                            </div>
                        </div>
                    </div>
                </section>

                <section class="mt-10" id="reviews">
                    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
                        <div>
                            <div class="pill">Testimonials</div>
                            <h3 class="text-xl md:text-2xl font-semibold mt-3">What our clients say</h3>
                            <div class="text-sm clinic-subtle mt-1">A calm, clean, and friendly clinic experience.</div>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" class="btn" data-test-prev>Prev</button>
                            <button type="button" class="btn" data-test-next>Next</button>
                        </div>
                    </div>

                    <div class="mt-4 grid lg:grid-cols-3 gap-3" data-testimonials>
                        <div class="clinic-card" data-test-card>
                            <div class="text-sm">“Very smooth booking and the staff were welcoming. Loved the reference code system.”</div>
                            <div class="text-xs clinic-subtle mt-3">— Maria S.</div>
                        </div>
                        <div class="clinic-card hidden lg:block" data-test-card>
                            <div class="text-sm">“Clean clinic and clear pricing. Appointment reminders helped a lot.”</div>
                            <div class="text-xs clinic-subtle mt-3">— John R.</div>
                        </div>
                        <div class="clinic-card hidden lg:block" data-test-card>
                            <div class="text-sm">“Fast check-in. I just showed the screenshot and everything was verified quickly.”</div>
                            <div class="text-xs clinic-subtle mt-3">— Anne C.</div>
                        </div>
                    </div>
                </section>

                <section class="mt-10" id="contact">
                    <div class="grid lg:grid-cols-12 gap-6">
                        <div class="lg:col-span-7 clinic-card">
                            <div class="pill">Contact</div>
                            <h3 class="text-lg font-semibold mt-3">Visit us</h3>
                            <div class="text-sm clinic-subtle mt-2">Mon–Fri, 9:00 AM – 5:00 PM</div>
                            <div class="text-sm clinic-subtle mt-1">Phone: 09xx-xxx-xxxx</div>
                            <div class="text-sm clinic-subtle mt-1">Address: Your clinic address here</div>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <a href="#" class="btn">Facebook</a>
                                <a href="#" class="btn">Instagram</a>
                                <a href="#" class="btn">Email</a>
                            </div>
                        </div>
                        <div class="lg:col-span-5 clinic-card">
                            <div class="pill">Ready?</div>
                            <h3 class="text-lg font-semibold mt-3">Book your appointment</h3>
                            <div class="text-sm clinic-subtle mt-2">Select a service, pick a slot, then save your reference code.</div>
                            <a href="/" class="btn btn-primary w-full mt-4">Book Appointment</a>
                        </div>
                    </div>
                </section>
            @endif

            <footer class="mt-10 pb-6 text-xs clinic-subtle">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div>{{ config('app.name', 'Skye Dental') }} • Web-Based Dental Clinic System</div>
                    <div>Booking • Check-in • Records • Billing • Audit Logs</div>
                </div>
            </footer>
        </div>

        @if (! $isStaff)
            <a class="clinic-fab" href="/" aria-label="Book appointment">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 2v3M16 2v3M3 9h18"/><path d="M5 5h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"/><path d="M8 13h4M8 17h7"/></svg>
                <span class="text-sm font-semibold">Book</span>
            </a>
        @endif

        <script>
            const isStaffMode = @json($isStaff);

            if (!isStaffMode) {
                const slides = Array.from(document.querySelectorAll('[data-hero-slide]'));
                const prevBtn = document.querySelector('[data-hero-prev]');
                const nextBtn = document.querySelector('[data-hero-next]');
                const dotsWrap = document.querySelector('[data-hero-dots]');

                let activeIndex = 0;
                let timer = null;

                function setActive(nextIndex) {
                    if (slides.length === 0) return;
                    activeIndex = (nextIndex + slides.length) % slides.length;
                    slides.forEach((el, i) => el.classList.toggle('is-active', i === activeIndex));
                    if (dotsWrap) {
                        Array.from(dotsWrap.children).forEach((dot, i) => dot.classList.toggle('is-active', i === activeIndex));
                    }
                }

                function startAuto() {
                    stopAuto();
                    timer = window.setInterval(() => setActive(activeIndex + 1), 6500);
                }

                function stopAuto() {
                    if (timer) {
                        window.clearInterval(timer);
                        timer = null;
                    }
                }

                if (dotsWrap) {
                    dotsWrap.innerHTML = '';
                    slides.forEach((_, i) => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'hero-dot' + (i === 0 ? ' is-active' : '');
                        btn.setAttribute('aria-label', `Slide ${i + 1}`);
                        btn.addEventListener('click', () => {
                            setActive(i);
                            startAuto();
                        });
                        dotsWrap.appendChild(btn);
                    });
                }

                prevBtn?.addEventListener('click', () => {
                    setActive(activeIndex - 1);
                    startAuto();
                });
                nextBtn?.addEventListener('click', () => {
                    setActive(activeIndex + 1);
                    startAuto();
                });

                startAuto();

                const testCards = Array.from(document.querySelectorAll('[data-test-card]'));
                const testPrev = document.querySelector('[data-test-prev]');
                const testNext = document.querySelector('[data-test-next]');
                let testIndex = 0;

                function renderTestimonial() {
                    if (window.matchMedia('(min-width: 1024px)').matches) {
                        testCards.forEach((c) => c.classList.remove('hidden'));
                        return;
                    }
                    testCards.forEach((c, i) => c.classList.toggle('hidden', i !== testIndex));
                }

                testPrev?.addEventListener('click', () => {
                    testIndex = (testIndex - 1 + testCards.length) % testCards.length;
                    renderTestimonial();
                });
                testNext?.addEventListener('click', () => {
                    testIndex = (testIndex + 1) % testCards.length;
                    renderTestimonial();
                });

                window.addEventListener('resize', renderTestimonial);
                renderTestimonial();
            }
        </script>
    </body>
</html>
