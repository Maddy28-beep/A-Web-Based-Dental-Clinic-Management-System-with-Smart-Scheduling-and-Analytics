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

        <title>{{ config('app.name', 'Skye Dental') }} - Design System</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="clinic-shell">
        <div class="max-w-6xl mx-auto p-4 md:p-8">
            <div class="clinic-header mb-6">
                <div class="clinic-brand">
                    <img src="{{ asset('logo.png') }}" alt="{{ config('app.name', 'Skye Dental') }} logo" class="h-11 w-11 rounded-2xl" style="box-shadow: var(--shadow-clinic-card);" />
                    <div>
                        <div class="text-xs clinic-subtle tracking-wide uppercase">{{ config('app.name', 'Skye Dental') }}</div>
                        <h1 class="text-2xl md:text-3xl font-semibold leading-tight">Design System</h1>
                        <p class="text-sm clinic-subtle mt-1">Buttons, spacing, interactions, and accessibility rules.</p>
                    </div>
                </div>
                <div class="btn-group">
                    <button id="toggleTheme" type="button" class="btn">Dark Mode</button>
                    <span class="pill">Staff Interface</span>
                    <a href="/billing" class="btn">Back</a>
                </div>
            </div>

            <div class="grid lg:grid-cols-12 gap-6">
                <div class="clinic-card lg:col-span-7 space-y-6">
                    <div>
                        <h2 class="text-base font-medium">Buttons</h2>
                        <div class="text-sm clinic-subtle mt-1">Use `.btn` as the base and add a variant for intent.</div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button type="button" class="btn">Default</button>
                            <button type="button" class="btn btn-primary">Primary</button>
                            <button type="button" class="btn btn-teal">Accent</button>
                            <button type="button" class="btn btn-ghost">Ghost</button>
                            <button type="button" class="btn btn-link">Link</button>
                            <button type="button" class="btn btn-icon btn-ghost" aria-label="Icon button">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v18"/><path d="M3 12h18"/></svg>
                            </button>
                            <button type="button" class="btn" disabled>Disabled</button>
                        </div>
                    </div>

                    <div>
                        <h2 class="text-base font-medium">Hierarchy</h2>
                        <div class="text-sm clinic-subtle mt-1">Prefer one primary action per surface. Group related actions.</div>
                        <div class="mt-4 rounded-xl border p-4" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <div class="text-sm font-medium">Example Panel</div>
                                    <div class="text-sm clinic-subtle">Primary is emphasized. Secondary stays neutral.</div>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" class="btn">Cancel</button>
                                    <button type="button" class="btn btn-primary">Save</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h2 class="text-base font-medium">Interaction</h2>
                        <div class="text-sm clinic-subtle mt-1">Hover elevates, active settles, focus is always visible.</div>
                        <div class="mt-4 text-sm">
                            <div class="grid sm:grid-cols-2 gap-4">
                                <div class="rounded-xl border p-4" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                                    <div class="text-xs clinic-subtle">Keyboard</div>
                                    <div class="mt-1">Use Tab to focus buttons and Enter/Space to activate.</div>
                                </div>
                                <div class="rounded-xl border p-4" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                                    <div class="text-xs clinic-subtle">Touch</div>
                                    <div class="mt-1">Buttons are at least 48×48px (via `min-h-12`).</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="clinic-card lg:col-span-5 space-y-6">
                    <div>
                        <h2 class="text-base font-medium">Spacing</h2>
                        <div class="text-sm clinic-subtle mt-1">Recommended gaps and padding for consistent rhythm.</div>
                        <div class="mt-4 grid gap-3 text-sm">
                            <div class="rounded-xl border p-4" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                                <div class="font-medium">Button group gap</div>
                                <div class="text-sm clinic-subtle mt-1">Use `gap-2` for related actions.</div>
                            </div>
                            <div class="rounded-xl border p-4" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                                <div class="font-medium">Card padding</div>
                                <div class="text-sm clinic-subtle mt-1">Use `p-4` or `p-5` depending on density.</div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h2 class="text-base font-medium">Accessibility</h2>
                        <div class="text-sm clinic-subtle mt-1">Baseline requirements for interactive controls.</div>
                        <div class="mt-4 rounded-xl border p-4 text-sm" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                            <ul class="list-disc pl-5 space-y-1">
                                <li>Minimum touch target 48×48px for buttons and key actions.</li>
                                <li>Visible focus style for keyboard navigation (`:focus-visible`).</li>
                                <li>Use `disabled` or `aria-disabled="true"` for unavailable actions.</li>
                                <li>Icon-only buttons require an `aria-label`.</li>
                            </ul>
                        </div>
                    </div>

                    <div>
                        <h2 class="text-base font-medium">Usage</h2>
                        <div class="text-sm clinic-subtle mt-1">Copy/paste class patterns.</div>
                        <pre class="mt-4 rounded-xl border p-4 text-xs overflow-x-auto" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface);"><code>&lt;button class="btn"&gt;Default&lt;/button&gt;
&lt;button class="btn btn-primary"&gt;Primary&lt;/button&gt;
&lt;button class="btn btn-teal"&gt;Accent&lt;/button&gt;
&lt;button class="btn btn-ghost"&gt;Ghost&lt;/button&gt;
&lt;button class="btn btn-link"&gt;Link&lt;/button&gt;
&lt;button class="btn btn-icon btn-ghost" aria-label="Open"&gt;...&lt;/button&gt;</code></pre>
                    </div>

                    <div>
                        <h2 class="text-base font-medium">Theme</h2>
                        <div class="text-sm clinic-subtle mt-1">Prefer surface + border variables so light/dark stays consistent.</div>
                        <div class="mt-4 grid gap-3 text-sm">
                            <div class="rounded-xl border p-4" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                                <div class="font-medium">Surfaces</div>
                                <div class="text-sm clinic-subtle mt-1">Use `.clinic-shell` for the page and `.clinic-card` for sections.</div>
                            </div>
                            <div class="rounded-xl border p-4" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                                <div class="font-medium">Modals</div>
                                <div class="text-sm clinic-subtle mt-1">Use `background: var(--color-clinic-surface)` and `border-color: var(--color-clinic-border)`.</div>
                            </div>
                        </div>
                        <pre class="mt-4 rounded-xl border p-4 text-xs overflow-x-auto" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface);"><code>&lt;div class="rounded-xl border p-4"
     style="border-color: var(--color-clinic-border);
            background: var(--color-clinic-surface-2);"&gt;
  ...
&lt;/div&gt;</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
