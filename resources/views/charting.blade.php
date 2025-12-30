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

        <title>{{ config('app.name', 'Skye Dental') }} - Tooth Charting</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="clinic-shell">
        <div class="max-w-6xl mx-auto p-4 md:p-8">
            <div class="clinic-header mb-6">
                <div class="clinic-brand">
                    <img src="{{ asset('logo.png') }}" alt="{{ config('app.name', 'Skye Dental') }} logo" class="h-11 w-11 rounded-2xl" style="box-shadow: var(--shadow-clinic-card);" />
                    <div>
                        <div class="text-xs clinic-subtle tracking-wide uppercase">{{ config('app.name', 'Skye Dental') }}</div>
                        <h1 class="text-2xl md:text-3xl font-semibold leading-tight">Clinical Tooth Charting</h1>
                        <p class="text-sm clinic-subtle mt-1">Click teeth, record conditions, and review history.</p>
                    </div>
                </div>
                <div class="btn-group">
                    <button id="toggleTheme" type="button" class="btn">Dark Mode</button>
                    <span class="pill">Staff Interface</span>
                    <a href="/appointments-dashboard" class="btn">Appointments</a>
                    @if (auth()->user()?->hasPermission('appointments.checkin'))
                        <a href="/check-in" class="btn">Check-In</a>
                    @endif
                    <a href="/" class="btn">Front Desk Booking</a>
                    @if (auth()->user()?->hasPermission('billing.view'))
                        <a href="/billing" class="btn">Billing</a>
                    @endif
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
                <div class="lg:col-span-2 clinic-card">
                    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-4">
                        <div class="flex-1">
                            <label class="block text-sm font-medium mb-1" for="patient">
                                Patient
                                <span id="allergyIcon" class="ml-2 hidden align-middle text-[11px] px-2 py-0.5 rounded-full border tone-red">Allergy</span>
                            </label>
                            <div class="flex gap-2">
                                <select id="patient" class="flex-1 clinic-input">
                                    <option value="">Loading...</option>
                                </select>
                                <button id="newPatientBtn" class="btn">New</button>
                            </div>
                            <div id="patientMeta" class="text-xs clinic-subtle mt-1"></div>
                            <div id="allergyBanner" class="hidden mt-2 rounded-xl border p-3 text-sm"></div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" for="dentition">Dentition</label>
                            <select id="dentition" class="clinic-input">
                                <option value="adult">Adult</option>
                                <option value="pediatric">Pediatric</option>
                            </select>
                        </div>
                    </div>

                    <div class="rounded-lg border border-slate-200 dark:border-slate-700 p-4 bg-slate-50 dark:bg-slate-950/40">
                        <div class="flex items-center justify-between mb-3">
                            <div class="text-sm font-medium">Teeth Diagram</div>
                            <div class="flex items-center gap-3 text-xs clinic-subtle">
                                <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded bg-emerald-500 inline-block"></span> Healthy</span>
                                <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded bg-amber-500 inline-block"></span> Needs Attention</span>
                                <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded bg-red-500 inline-block"></span> Urgent</span>
                            </div>
                        </div>

                        <div class="grid gap-4">
                            <div>
                                <div class="text-xs clinic-subtle mb-2">Upper</div>
                                <div class="grid sm:grid-cols-2 gap-4">
                                    <div id="quadUR" class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white/60 dark:bg-slate-950/30 p-3">
                                        <div class="flex items-center justify-between mb-2">
                                            <div class="text-[11px] font-medium tracking-wide">UR</div>
                                            <div class="text-[11px] clinic-subtle">Upper Right</div>
                                        </div>
                                        <div id="upperRightRow" class="grid grid-cols-8 gap-2"></div>
                                    </div>
                                    <div id="quadUL" class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white/60 dark:bg-slate-950/30 p-3">
                                        <div class="flex items-center justify-between mb-2">
                                            <div class="text-[11px] font-medium tracking-wide">UL</div>
                                            <div class="text-[11px] clinic-subtle">Upper Left</div>
                                        </div>
                                        <div id="upperLeftRow" class="grid grid-cols-8 gap-2"></div>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <div class="text-xs clinic-subtle mb-2">Lower</div>
                                <div class="grid sm:grid-cols-2 gap-4">
                                    <div id="quadLR" class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white/60 dark:bg-slate-950/30 p-3">
                                        <div class="flex items-center justify-between mb-2">
                                            <div class="text-[11px] font-medium tracking-wide">LR</div>
                                            <div class="text-[11px] clinic-subtle">Lower Right</div>
                                        </div>
                                        <div id="lowerRightRow" class="grid grid-cols-8 gap-2"></div>
                                    </div>
                                    <div id="quadLL" class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white/60 dark:bg-slate-950/30 p-3">
                                        <div class="flex items-center justify-between mb-2">
                                            <div class="text-[11px] font-medium tracking-wide">LL</div>
                                            <div class="text-[11px] clinic-subtle">Lower Left</div>
                                        </div>
                                        <div id="lowerLeftRow" class="grid grid-cols-8 gap-2"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="clinic-card lg:sticky lg:top-6">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="font-medium">Tooth Details</h2>
                        <div class="text-xs clinic-subtle">Selected: <span id="selectedToothLabel" class="font-mono">None</span></div>
                    </div>

                    <div id="emptyPanel" class="text-sm clinic-subtle">
                        Select a patient and click a tooth.
                    </div>

                    <div id="panel" class="hidden">
                        <div class="grid grid-cols-1 gap-3">
                            <div>
                                <label class="block text-sm font-medium mb-1" for="condition">Condition</label>
                                <select id="condition" class="clinic-input">
                                    <option value="healthy">Healthy</option>
                                    <option value="needs_attention">Needs Attention</option>
                                    <option value="urgent">Urgent</option>
                                    <option value="cavity">Cavity</option>
                                    <option value="sensitive">Sensitive</option>
                                    <option value="infection">Infection</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1" for="recordedAt">Recorded At</label>
                                <input id="recordedAt" type="datetime-local" class="clinic-input" />
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1" for="procedure">Procedure</label>
                                <div class="grid grid-cols-3 gap-2 mb-2" id="procedureQuick"></div>
                                <div class="grid grid-cols-3 gap-2 mb-2">
                                    <div class="col-span-2">
                                        <input id="procedure" class="clinic-input" placeholder="e.g. filling, crown, extraction" />
                                    </div>
                                    <div>
                                        <input id="cdtCode" class="clinic-input font-mono text-sm" placeholder="CDT code" />
                                    </div>
                                </div>
                            </div>

                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="block text-sm font-medium" for="surfacesWrap">Surfaces</label>
                                    <div class="text-xs clinic-subtle">M D B L O</div>
                                </div>
                                <div id="surfacesWrap" class="grid grid-cols-5 gap-2"></div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1" for="notes">Notes</label>
                                <textarea id="notes" rows="3" class="clinic-input" placeholder="Dentist notes..."></textarea>
                            </div>

                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <div class="text-sm font-medium">Tooth Photos (optional)</div>
                                    <div class="text-xs clinic-subtle">Not X-rays</div>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-sm font-medium mb-1" for="before">Before photo</label>
                                        <input id="before" type="file" accept="image/*" class="w-full text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-1" for="after">After photo</label>
                                        <input id="after" type="file" accept="image/*" class="w-full text-sm" />
                                    </div>
                                </div>
                                <div class="text-xs clinic-subtle mt-1">These attach to the tooth record. Use the X-Rays section below for radiographs.</div>
                            </div>

                            <button id="saveBtn" class="w-full btn btn-primary" disabled>Save Record</button>
                            <div class="text-[11px] clinic-subtle text-center">Shortcut: Ctrl+S</div>
                            <div id="msg" class="text-sm"></div>
                        </div>

                        <div class="mt-6">
                            <div class="flex items-center justify-between mb-2">
                                <div class="font-medium text-sm">Allergies</div>
                                <div class="flex items-center gap-2">
                                    <div class="text-xs clinic-subtle" id="allergyCount"></div>
                                    <button id="newAllergyBtn" type="button" class="btn">Add</button>
                                </div>
                            </div>
                            <div id="allergiesList" class="space-y-2"></div>
                            <div id="allergiesEmpty" class="text-sm clinic-subtle hidden rounded-xl border p-3" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                                No allergies on file.
                            </div>
                        </div>

                        <div class="mt-6">
                            <div class="flex items-center justify-between mb-2">
                                <div class="font-medium text-sm">Medical History</div>
                                <div class="flex items-center gap-2">
                                    <div class="text-xs clinic-subtle" id="medicalCount"></div>
                                    <button id="editMedicalBtn" type="button" class="btn">Update</button>
                                </div>
                            </div>
                            <div id="medicalSummary" class="text-sm"></div>
                            <div id="medicalEmpty" class="text-sm clinic-subtle hidden rounded-xl border p-3" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                                No medical history on file.
                            </div>
                        </div>

                        <div class="mt-6">
                            <div class="flex items-center justify-between mb-2">
                                <div class="font-medium text-sm">History</div>
                                <div class="text-xs clinic-subtle" id="historyCount"></div>
                            </div>
                            <input id="timeline" type="range" min="0" max="0" value="0" class="w-full" />
                            <div class="mt-3 text-xs clinic-subtle" id="timelineLabel"></div>
                            <div class="mt-3 grid grid-cols-2 gap-3" id="historyImages"></div>
                            <div class="mt-3 rounded-md border border-slate-200 dark:border-slate-700 p-3 bg-slate-50 dark:bg-slate-950/40">
                                <div class="text-sm font-medium mb-1" id="historyTitle"></div>
                                <div class="flex flex-wrap gap-1.5 mb-2" id="historyMeta"></div>
                                <div class="text-xs clinic-subtle whitespace-pre-wrap" id="historyNotes"></div>
                            </div>
                        </div>

                        <div class="mt-6">
                            <div class="flex items-center justify-between mb-2">
                                <div class="font-medium text-sm">Procedures</div>
                                <div class="flex items-center gap-2">
                                    <div class="text-xs clinic-subtle" id="procedureCount"></div>
                                    <button id="newProcedureBtn" type="button" class="btn">New</button>
                                </div>
                            </div>
                            <div id="proceduresList" class="space-y-2"></div>
                            <div id="proceduresEmpty" class="text-sm clinic-subtle hidden rounded-xl border p-3" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                                No procedures yet.
                            </div>
                        </div>

                        <div class="mt-6">
                            <div class="flex items-center justify-between mb-2">
                                <div class="font-medium text-sm">X-Rays</div>
                                <div class="flex items-center gap-2">
                                    <div class="text-xs clinic-subtle" id="xrayCount"></div>
                                    <button id="uploadXrayBtn" type="button" class="btn">Upload</button>
                                </div>
                            </div>
                            <div id="xrayCompareBar" class="rounded-xl border p-3 mb-2" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                    <div class="text-xs clinic-subtle min-w-0">
                                        <div class="truncate">
                                            <span class="font-medium">Compare</span>
                                            <span class="ml-2">Before = earlier (pre-op)</span>
                                            <span class="mx-1">•</span>
                                            <span>After = later (post-op)</span>
                                        </div>
                                        <div class="mt-1 flex flex-wrap gap-2">
                                            <span class="pill tone-blue">Before</span>
                                            <span id="xrayCompareBeforeLabel" class="text-xs font-mono clinic-subtle">None</span>
                                            <span class="pill tone-green">After</span>
                                            <span id="xrayCompareAfterLabel" class="text-xs font-mono clinic-subtle">None</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button id="xrayCompareOpenBtn" type="button" class="btn" disabled>Compare</button>
                                        <button id="xrayCompareClearBtn" type="button" class="btn">Clear</button>
                                    </div>
                                </div>
                            </div>
                            <div id="xraysList" class="space-y-2"></div>
                            <div id="xraysEmpty" class="text-sm clinic-subtle hidden rounded-xl border p-3" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                                No X-rays yet.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="imageModal" class="fixed inset-0 bg-black/70 hidden items-center justify-center p-4">
                <div class="rounded-xl shadow-lg border w-full max-w-3xl p-3" style="background: var(--color-clinic-surface); border-color: var(--color-clinic-border);">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-sm font-medium" id="imageModalTitle"></div>
                        <button id="closeImageModal" type="button" class="btn btn-link">Close</button>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 mb-2">
                        <button id="zoomOutBtn" type="button" class="btn">Zoom -</button>
                        <button id="zoomInBtn" type="button" class="btn">Zoom +</button>
                        <button id="rotateLeftBtn" type="button" class="btn">Rotate -90°</button>
                        <button id="rotateRightBtn" type="button" class="btn">Rotate +90°</button>
                        <button id="resetViewBtn" type="button" class="btn">Reset</button>
                    </div>
                    <div id="imageSingle" class="rounded-lg border overflow-hidden" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                        <img id="imageModalImg" src="" alt="" class="w-full h-auto max-h-[75vh] object-contain bg-black transition-transform duration-150" />
                    </div>
                    <div id="fileFrameWrap" class="hidden rounded-lg border overflow-hidden" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                        <iframe id="fileFrame" class="w-full h-[75vh]" src="" title="Preview"></iframe>
                    </div>
                    <div id="imageCompare" class="hidden">
                        <div class="flex items-center justify-between gap-3 px-2 py-2 text-xs clinic-subtle">
                            <button id="compareLeftBtn" type="button" class="btn btn-link">Before</button>
                            <input id="compareSlider" type="range" min="0" max="100" value="50" class="flex-1" />
                            <button id="compareRightBtn" type="button" class="btn btn-link">After</button>
                        </div>
                        <div class="rounded-lg border bg-black overflow-hidden relative select-none" style="border-color: var(--color-clinic-border);">
                            <img id="compareBeforeImg" src="" alt="Before" class="w-full h-auto max-h-[75vh] object-contain bg-black block transition-transform duration-150" />
                            <div id="compareAfterWrap" class="absolute inset-y-0 left-0 overflow-hidden" style="width: 50%;">
                                <img id="compareAfterImg" src="" alt="After" class="w-full h-auto max-h-[75vh] object-contain bg-black block transition-transform duration-150" />
                            </div>
                            <div id="compareHandle" class="absolute inset-y-0 w-0.5 bg-white/80" style="left: 50%;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="patientModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center p-4">
                <div class="rounded-xl shadow-lg border w-full max-w-md p-5" style="background: var(--color-clinic-surface); border-color: var(--color-clinic-border);">
                    <div class="flex items-center justify-between mb-4">
                        <div class="font-medium">New Patient</div>
                        <button id="closePatientModal" type="button" class="btn btn-link">Close</button>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium mb-1" for="pName">Full Name</label>
                            <input id="pName" class="clinic-input" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium mb-1" for="pPhone">Phone</label>
                                <input id="pPhone" class="clinic-input" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" for="pEmail">Email</label>
                                <input id="pEmail" type="email" class="clinic-input" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" for="pDob">Date of Birth</label>
                            <input id="pDob" type="date" class="clinic-input" />
                        </div>
                        <button id="createPatientBtn" class="w-full btn btn-primary">Create</button>
                        <div id="patientModalMsg" class="text-sm"></div>
                    </div>
                </div>
            </div>

            <div id="allergyModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center p-4">
                <div class="rounded-xl shadow-lg border w-full max-w-md p-5" style="background: var(--color-clinic-surface); border-color: var(--color-clinic-border);">
                    <div class="flex items-center justify-between mb-4">
                        <div class="font-medium">Add Allergy</div>
                        <button id="closeAllergyModal" type="button" class="btn btn-link">Close</button>
                    </div>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium mb-1" for="aTag">Tag</label>
                            <input id="aTag" class="clinic-input" placeholder="e.g. Penicillin, Latex, Anesthesia" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium mb-1" for="aSeverity">Severity</label>
                                <select id="aSeverity" class="clinic-input">
                                    <option value="mild">Mild</option>
                                    <option value="moderate">Moderate</option>
                                    <option value="severe">Severe</option>
                                </select>
                            </div>
                            <div class="flex items-end">
                                <label class="inline-flex items-center gap-2 text-sm">
                                    <input id="aActive" type="checkbox" class="rounded border-slate-300 dark:border-slate-600" checked />
                                    Active
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" for="aNotes">Notes</label>
                            <textarea id="aNotes" class="clinic-input min-h-20" placeholder="Optional notes..."></textarea>
                        </div>
                        <button id="createAllergyBtn" class="w-full btn btn-primary">Save Allergy</button>
                        <div id="allergyModalMsg" class="text-sm"></div>
                    </div>
                </div>
            </div>

            <div id="medicalModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center p-4">
                <div class="rounded-xl shadow-lg border w-full max-w-xl p-5" style="background: var(--color-clinic-surface); border-color: var(--color-clinic-border);">
                    <div class="flex items-center justify-between mb-4">
                        <div class="font-medium">Update Medical History</div>
                        <button id="closeMedicalModal" type="button" class="btn btn-link">Close</button>
                    </div>
                    <div class="space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium mb-1" for="mConditions">Conditions</label>
                                <input id="mConditions" class="clinic-input" placeholder="e.g. diabetes, hypertension" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" for="mMedications">Medications</label>
                                <input id="mMedications" class="clinic-input" placeholder="e.g. metformin, amlodipine" />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input id="mSmoker" type="checkbox" class="rounded border-slate-300 dark:border-slate-600" />
                                Smoker
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input id="mPregnant" type="checkbox" class="rounded border-slate-300 dark:border-slate-600" />
                                Pregnant
                            </label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" for="mNotes">Notes</label>
                            <textarea id="mNotes" class="clinic-input min-h-24" placeholder="Optional notes..."></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" for="mRecordedAt">Recorded At</label>
                            <input id="mRecordedAt" type="datetime-local" class="clinic-input" />
                        </div>
                        <button id="saveMedicalBtn" class="w-full btn btn-primary">Save Medical History</button>
                        <div id="medicalModalMsg" class="text-sm"></div>
                    </div>
                </div>
            </div>

            <div id="procedureModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center p-4">
                <div class="rounded-xl shadow-lg border w-full max-w-xl p-5" style="background: var(--color-clinic-surface); border-color: var(--color-clinic-border);">
                    <div class="flex items-center justify-between mb-4">
                        <div class="font-medium">New Procedure</div>
                        <button id="closeProcedureModal" type="button" class="btn btn-link">Close</button>
                    </div>
                    <div class="space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium mb-1" for="prType">Procedure Type</label>
                                <input id="prType" class="clinic-input" placeholder="e.g. cleaning, filling" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" for="prPerformedAt">Performed At</label>
                                <input id="prPerformedAt" type="datetime-local" class="clinic-input" />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium mb-1" for="prDescription">Description</label>
                                <input id="prDescription" class="clinic-input" placeholder="Optional" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" for="prCost">Cost (PHP)</label>
                                <input id="prCost" type="number" min="0" step="0.01" class="clinic-input" placeholder="0.00" />
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <div class="text-sm font-medium">Teeth</div>
                                <button id="prAddSelectedToothBtn" type="button" class="btn">Add Selected Tooth</button>
                            </div>
                            <div id="prTeethChips" class="flex flex-wrap gap-2"></div>
                            <div class="text-xs clinic-subtle mt-1">Linking teeth enables tooth-chart highlights from the timeline.</div>
                        </div>
                        <div id="prConflictBox" class="hidden rounded-xl border p-3 text-sm tone-red"></div>
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input id="prConfirmConflict" type="checkbox" class="rounded border-slate-300 dark:border-slate-600" />
                            Confirm allergy conflicts (dentist)
                        </label>
                        <button id="createProcedureBtn" class="w-full btn btn-primary">Save Procedure</button>
                        <div id="procedureModalMsg" class="text-sm"></div>
                    </div>
                </div>
            </div>

            <div id="xrayModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center p-4">
                <div class="rounded-xl shadow-lg border w-full max-w-lg p-5" style="background: var(--color-clinic-surface); border-color: var(--color-clinic-border);">
                    <div class="flex items-center justify-between mb-4">
                        <div class="font-medium">Upload X-Ray</div>
                        <button id="closeXrayModal" type="button" class="btn btn-link">Close</button>
                    </div>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium mb-1" for="xFile">File (JPG/PNG/PDF)</label>
                            <input id="xFile" type="file" accept="image/*,application/pdf" class="w-full text-sm" />
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium mb-1" for="xRecordedAt">Recorded At</label>
                                <input id="xRecordedAt" type="datetime-local" class="clinic-input" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" for="xToothCode">Tooth Code (optional)</label>
                                <input id="xToothCode" class="clinic-input" placeholder="e.g. 11" />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium mb-1" for="xProcedureId">Procedure (optional)</label>
                                <select id="xProcedureId" class="clinic-input">
                                    <option value="">None</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" for="xVisitId">Visit (optional)</label>
                                <select id="xVisitId" class="clinic-input">
                                    <option value="">None</option>
                                </select>
                            </div>
                        </div>
                        <button id="uploadXraySubmitBtn" class="w-full btn btn-primary">Upload</button>
                        <div id="xrayModalMsg" class="text-sm"></div>
                    </div>
                </div>
            </div>
        </div>

        <a class="clinic-fab" href="/" aria-label="Book appointment">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 2v3M16 2v3M3 9h18"/><path d="M5 5h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"/><path d="M8 13h4M8 17h7"/></svg>
            <span class="text-sm font-semibold">Book</span>
        </a>

        <script>
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const canEditClinical = @json((bool) (auth()->user()?->hasPermission('clinical.edit') ?? false));
            const canCreatePatients = @json((bool) (auth()->user()?->hasPermission('patients.create') ?? false));

            const patientEl = document.getElementById('patient');
            const dentitionEl = document.getElementById('dentition');
            const patientMetaEl = document.getElementById('patientMeta');
            const allergyBannerEl = document.getElementById('allergyBanner');
            const allergyIconEl = document.getElementById('allergyIcon');
            const upperRightRow = document.getElementById('upperRightRow');
            const upperLeftRow = document.getElementById('upperLeftRow');
            const lowerRightRow = document.getElementById('lowerRightRow');
            const lowerLeftRow = document.getElementById('lowerLeftRow');

            const quadUR = document.getElementById('quadUR');
            const quadUL = document.getElementById('quadUL');
            const quadLR = document.getElementById('quadLR');
            const quadLL = document.getElementById('quadLL');

            const emptyPanel = document.getElementById('emptyPanel');
            const panel = document.getElementById('panel');
            const selectedToothLabel = document.getElementById('selectedToothLabel');
            const conditionEl = document.getElementById('condition');
            const recordedAtEl = document.getElementById('recordedAt');
            const procedureEl = document.getElementById('procedure');
            const cdtCodeEl = document.getElementById('cdtCode');
            const surfacesWrap = document.getElementById('surfacesWrap');
            const notesEl = document.getElementById('notes');
            const beforeEl = document.getElementById('before');
            const afterEl = document.getElementById('after');
            const saveBtn = document.getElementById('saveBtn');
            const msgEl = document.getElementById('msg');

            const historyCountEl = document.getElementById('historyCount');
            const timelineEl = document.getElementById('timeline');
            const timelineLabelEl = document.getElementById('timelineLabel');
            const historyTitleEl = document.getElementById('historyTitle');
            const historyMetaEl = document.getElementById('historyMeta');
            const historyNotesEl = document.getElementById('historyNotes');
            const historyImagesEl = document.getElementById('historyImages');

            const allergiesListEl = document.getElementById('allergiesList');
            const allergiesEmptyEl = document.getElementById('allergiesEmpty');
            const allergyCountEl = document.getElementById('allergyCount');
            const newAllergyBtn = document.getElementById('newAllergyBtn');

            const medicalSummaryEl = document.getElementById('medicalSummary');
            const medicalEmptyEl = document.getElementById('medicalEmpty');
            const medicalCountEl = document.getElementById('medicalCount');
            const editMedicalBtn = document.getElementById('editMedicalBtn');

            const proceduresListEl = document.getElementById('proceduresList');
            const proceduresEmptyEl = document.getElementById('proceduresEmpty');
            const procedureCountEl = document.getElementById('procedureCount');
            const newProcedureBtn = document.getElementById('newProcedureBtn');

            const xraysListEl = document.getElementById('xraysList');
            const xraysEmptyEl = document.getElementById('xraysEmpty');
            const xrayCountEl = document.getElementById('xrayCount');
            const uploadXrayBtn = document.getElementById('uploadXrayBtn');
            const xrayCompareBeforeLabel = document.getElementById('xrayCompareBeforeLabel');
            const xrayCompareAfterLabel = document.getElementById('xrayCompareAfterLabel');
            const xrayCompareOpenBtn = document.getElementById('xrayCompareOpenBtn');
            const xrayCompareClearBtn = document.getElementById('xrayCompareClearBtn');

            const imageModal = document.getElementById('imageModal');
            const imageModalTitle = document.getElementById('imageModalTitle');
            const imageModalImg = document.getElementById('imageModalImg');
            const imageSingleEl = document.getElementById('imageSingle');
            const imageCompareEl = document.getElementById('imageCompare');
            const fileFrameWrap = document.getElementById('fileFrameWrap');
            const fileFrame = document.getElementById('fileFrame');
            const zoomOutBtn = document.getElementById('zoomOutBtn');
            const zoomInBtn = document.getElementById('zoomInBtn');
            const rotateLeftBtn = document.getElementById('rotateLeftBtn');
            const rotateRightBtn = document.getElementById('rotateRightBtn');
            const resetViewBtn = document.getElementById('resetViewBtn');
            const compareSlider = document.getElementById('compareSlider');
            const compareBeforeImg = document.getElementById('compareBeforeImg');
            const compareAfterImg = document.getElementById('compareAfterImg');
            const compareAfterWrap = document.getElementById('compareAfterWrap');
            const compareHandle = document.getElementById('compareHandle');
            const compareLeftBtn = document.getElementById('compareLeftBtn');
            const compareRightBtn = document.getElementById('compareRightBtn');
            const closeImageModal = document.getElementById('closeImageModal');

            const patientModal = document.getElementById('patientModal');
            const newPatientBtn = document.getElementById('newPatientBtn');
            const closePatientModal = document.getElementById('closePatientModal');
            const createPatientBtn = document.getElementById('createPatientBtn');
            const pName = document.getElementById('pName');
            const pPhone = document.getElementById('pPhone');
            const pEmail = document.getElementById('pEmail');
            const pDob = document.getElementById('pDob');
            const patientModalMsg = document.getElementById('patientModalMsg');

            const allergyModal = document.getElementById('allergyModal');
            const closeAllergyModal = document.getElementById('closeAllergyModal');
            const createAllergyBtn = document.getElementById('createAllergyBtn');
            const aTag = document.getElementById('aTag');
            const aSeverity = document.getElementById('aSeverity');
            const aActive = document.getElementById('aActive');
            const aNotes = document.getElementById('aNotes');
            const allergyModalMsg = document.getElementById('allergyModalMsg');

            const medicalModal = document.getElementById('medicalModal');
            const closeMedicalModal = document.getElementById('closeMedicalModal');
            const saveMedicalBtn = document.getElementById('saveMedicalBtn');
            const mConditions = document.getElementById('mConditions');
            const mMedications = document.getElementById('mMedications');
            const mSmoker = document.getElementById('mSmoker');
            const mPregnant = document.getElementById('mPregnant');
            const mNotes = document.getElementById('mNotes');
            const mRecordedAt = document.getElementById('mRecordedAt');
            const medicalModalMsg = document.getElementById('medicalModalMsg');

            const procedureModal = document.getElementById('procedureModal');
            const closeProcedureModal = document.getElementById('closeProcedureModal');
            const createProcedureBtn = document.getElementById('createProcedureBtn');
            const prType = document.getElementById('prType');
            const prPerformedAt = document.getElementById('prPerformedAt');
            const prDescription = document.getElementById('prDescription');
            const prCost = document.getElementById('prCost');
            const prTeethChips = document.getElementById('prTeethChips');
            const prAddSelectedToothBtn = document.getElementById('prAddSelectedToothBtn');
            const prConflictBox = document.getElementById('prConflictBox');
            const prConfirmConflict = document.getElementById('prConfirmConflict');
            const procedureModalMsg = document.getElementById('procedureModalMsg');

            const xrayModal = document.getElementById('xrayModal');
            const closeXrayModal = document.getElementById('closeXrayModal');
            const uploadXraySubmitBtn = document.getElementById('uploadXraySubmitBtn');
            const xFile = document.getElementById('xFile');
            const xRecordedAt = document.getElementById('xRecordedAt');
            const xToothCode = document.getElementById('xToothCode');
            const xProcedureId = document.getElementById('xProcedureId');
            const xVisitId = document.getElementById('xVisitId');
            const xrayModalMsg = document.getElementById('xrayModalMsg');

            const procedureQuick = document.getElementById('procedureQuick');

            let teethMap = {};
            let selectedToothCode = null;
            let currentHistory = [];
            let selectedSurfaces = new Set();
            let activeQuadrant = null;
            let highlightedToothCodes = new Set();
            let xrayCompareBefore = null;
            let xrayCompareAfter = null;
            let viewScale = 1;
            let viewRotate = 0;
            let procedureDraftToothCodes = new Set();
            let lastAllergies = [];
            let lastMedicalHistory = null;
            let lastProcedures = [];
            let lastVisits = [];
            let lastXrays = [];

            function quadrantFromToothCode(code) {
                const q = String(code || '')[0];
                if (q === '1' || q === '5') return 'UR';
                if (q === '2' || q === '6') return 'UL';
                if (q === '3' || q === '7') return 'LL';
                if (q === '4' || q === '8') return 'LR';
                return null;
            }

            function setActiveQuadrant(q) {
                if (activeQuadrant === q) return;
                [quadUR, quadUL, quadLR, quadLL].forEach((el) => el?.classList.remove('ring-2', 'ring-[var(--color-clinic-teal)]'));
                activeQuadrant = q;
                const el = q === 'UR' ? quadUR : (q === 'UL' ? quadUL : (q === 'LR' ? quadLR : (q === 'LL' ? quadLL : null)));
                if (el) el.classList.add('ring-2', 'ring-[var(--color-clinic-teal)]');
            }

            function buildSurfaceButtons() {
                const items = [
                    { key: 'M', label: 'Mesial' },
                    { key: 'D', label: 'Distal' },
                    { key: 'B', label: 'Buccal' },
                    { key: 'L', label: 'Lingual' },
                    { key: 'O', label: 'Occlusal' },
                ];
                surfacesWrap.innerHTML = '';
                for (const it of items) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'btn justify-center py-2 font-mono';
                    btn.textContent = it.key;
                    btn.title = it.label;
                    btn.dataset.surface = it.key;
                    btn.addEventListener('click', () => {
                        if (selectedSurfaces.has(it.key)) {
                            selectedSurfaces.delete(it.key);
                        } else {
                            selectedSurfaces.add(it.key);
                        }
                        syncSurfaceButtons();
                    });
                    surfacesWrap.appendChild(btn);
                }
                syncSurfaceButtons();
            }

            function syncSurfaceButtons() {
                surfacesWrap.querySelectorAll('[data-surface]').forEach((el) => {
                    const k = el.dataset.surface;
                    const isOn = k && selectedSurfaces.has(k);
                    el.classList.add('btn');
                    el.classList.toggle('btn-teal', Boolean(isOn));
                });
            }

            function setFormMetaFromEntry(entry) {
                const meta = entry?.meta || null;
                const surfaces = Array.isArray(meta?.surfaces) ? meta.surfaces : [];
                selectedSurfaces = new Set(surfaces.filter((v) => typeof v === 'string'));
                cdtCodeEl.value = typeof meta?.cdt_code === 'string' ? meta.cdt_code : '';
                syncSurfaceButtons();
            }

            function nowForDatetimeLocal() {
                const d = new Date();
                const pad = (n) => String(n).padStart(2, '0');
                const yyyy = d.getFullYear();
                const mm = pad(d.getMonth() + 1);
                const dd = pad(d.getDate());
                const hh = pad(d.getHours());
                const mi = pad(d.getMinutes());
                return `${yyyy}-${mm}-${dd}T${hh}:${mi}`;
            }

            function quadrantCodes(dentition, q) {
                const isPed = dentition === 'pediatric';
                if (q === 'UR') return isPed ? ['55','54','53','52','51'] : ['18','17','16','15','14','13','12','11'];
                if (q === 'UL') return isPed ? ['61','62','63','64','65'] : ['21','22','23','24','25','26','27','28'];
                if (q === 'LR') return isPed ? ['85','84','83','82','81'] : ['48','47','46','45','44','43','42','41'];
                if (q === 'LL') return isPed ? ['71','72','73','74','75'] : ['31','32','33','34','35','36','37','38'];
                return [];
            }

            function clearMsg() {
                msgEl.textContent = '';
                msgEl.className = 'text-sm';
            }

            function setMsg(text, type) {
                msgEl.textContent = text;
                msgEl.className = type === 'success' ? 'text-sm text-emerald-700 dark:text-emerald-300' : 'text-sm text-red-700 dark:text-red-300';
            }

            function setModalMsg(text, type) {
                patientModalMsg.textContent = text;
                patientModalMsg.className = type === 'success' ? 'text-sm text-emerald-700 dark:text-emerald-300' : 'text-sm text-red-700 dark:text-red-300';
            }

            async function fetchJson(url, options) {
                const baseHeaders = { 'Accept': 'application/json' };
                if (csrfToken) baseHeaders['X-CSRF-TOKEN'] = csrfToken;
                const headers = { ...baseHeaders, ...(options?.headers || {}) };
                const res = await fetch(url, { credentials: 'same-origin', ...options, headers });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    throw new Error(data?.message || 'Request failed');
                }
                return data;
            }

            function setAllergyModalMsg(text, type) {
                allergyModalMsg.textContent = text;
                allergyModalMsg.className = type === 'success' ? 'text-sm text-emerald-700 dark:text-emerald-300' : 'text-sm text-red-700 dark:text-red-300';
            }

            function setProcedureModalMsg(text, type) {
                procedureModalMsg.textContent = text;
                procedureModalMsg.className = type === 'success' ? 'text-sm text-emerald-700 dark:text-emerald-300' : 'text-sm text-red-700 dark:text-red-300';
            }

            function setXrayModalMsg(text, type) {
                xrayModalMsg.textContent = text;
                xrayModalMsg.className = type === 'success' ? 'text-sm text-emerald-700 dark:text-emerald-300' : 'text-sm text-red-700 dark:text-red-300';
            }

            function setMedicalModalMsg(text, type) {
                medicalModalMsg.textContent = text;
                medicalModalMsg.className = type === 'success' ? 'text-sm text-emerald-700 dark:text-emerald-300' : 'text-sm text-red-700 dark:text-red-300';
            }

            function canEditAllergies() {
                return canEditClinical;
            }

            function canEditMedicalHistory() {
                return canEditClinical;
            }

            function canCreateProcedures() {
                return canEditClinical;
            }

            function canUploadXrays() {
                return canEditClinical;
            }

            function applyViewTransform() {
                const t = `scale(${viewScale}) rotate(${viewRotate}deg)`;
                imageModalImg.style.transform = t;
                compareBeforeImg.style.transform = t;
                compareAfterImg.style.transform = t;
            }

            function resetViewTransform() {
                viewScale = 1;
                viewRotate = 0;
                applyViewTransform();
            }

            function severityClass(severity) {
                if (severity === 'urgent') return 'bg-red-500 text-white border-red-600';
                if (severity === 'attention') return 'bg-amber-500 text-white border-amber-600';
                return 'bg-emerald-500 text-white border-emerald-600';
            }

            function buildToothButton(tooth) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = `relative rounded-md border px-2 py-2 text-xs font-medium transition hover:scale-[1.02] hover:shadow-sm ring-2 ring-transparent ring-offset-2 ring-offset-transparent ${severityClass(tooth.severity)}`;
                btn.dataset.code = tooth.tooth_code;
                btn.title = `Tooth ${tooth.tooth_code}`;

                const proc = (tooth.procedure || '').toLowerCase();
                const icon = proc.includes('filling') ? 'F' : (proc.includes('crown') ? 'C' : (proc.includes('extract') ? 'X' : ''));
                btn.innerHTML = `
                    <div class="flex items-center justify-between gap-1">
                        <span class="font-mono">${tooth.tooth_code}</span>
                        ${icon ? `<span class="text-[10px] opacity-90 rounded bg-white/20 px-1">${icon}</span>` : ''}
                    </div>
                    <div class="absolute inset-0 rounded-md ring-2 ring-transparent pointer-events-none" data-ring></div>
                `;

                btn.addEventListener('click', () => selectTooth(tooth.tooth_code));
                return btn;
            }

            function highlightSelected() {
                document.querySelectorAll('[data-ring]').forEach((el) => el.classList.remove('ring-white', 'ring-offset-2', 'ring-offset-transparent'));
                if (!selectedToothCode) return;
                const active = document.querySelector(`[data-code="${CSS.escape(selectedToothCode)}"] [data-ring]`);
                if (active) active.classList.add('ring-white', 'ring-offset-2', 'ring-offset-transparent');
            }

            function syncProcedureHighlights() {
                document.querySelectorAll('button[data-code]').forEach((btn) => {
                    const code = btn.dataset.code;
                    const isOn = code && highlightedToothCodes.has(code);
                    btn.classList.toggle('ring-[var(--color-clinic-teal)]', Boolean(isOn));
                });
            }

            function renderTeeth(teeth) {
                teethMap = {};
                upperRightRow.innerHTML = '';
                upperLeftRow.innerHTML = '';
                lowerRightRow.innerHTML = '';
                lowerLeftRow.innerHTML = '';

                for (const t of teeth) {
                    teethMap[t.tooth_code] = t;
                }

                const upperRightCodes = dentitionEl.value === 'pediatric'
                    ? ['55','54','53','52','51']
                    : ['18','17','16','15','14','13','12','11'];
                const upperLeftCodes = dentitionEl.value === 'pediatric'
                    ? ['61','62','63','64','65']
                    : ['21','22','23','24','25','26','27','28'];
                const lowerRightCodes = dentitionEl.value === 'pediatric'
                    ? ['85','84','83','82','81']
                    : ['48','47','46','45','44','43','42','41'];
                const lowerLeftCodes = dentitionEl.value === 'pediatric'
                    ? ['71','72','73','74','75']
                    : ['31','32','33','34','35','36','37','38'];

                for (const code of upperRightCodes) upperRightRow.appendChild(buildToothButton(teethMap[code]));
                for (const code of upperLeftCodes) upperLeftRow.appendChild(buildToothButton(teethMap[code]));
                for (const code of lowerRightCodes) lowerRightRow.appendChild(buildToothButton(teethMap[code]));
                for (const code of lowerLeftCodes) lowerLeftRow.appendChild(buildToothButton(teethMap[code]));

                highlightSelected();
                syncProcedureHighlights();
            }

            async function loadPatients() {
                const { data } = await fetchJson('/api/patients');
                patientEl.innerHTML = '<option value="">Select patient...</option>';
                for (const p of data) {
                    const opt = document.createElement('option');
                    opt.value = String(p.id);
                    opt.textContent = p.full_name;
                    opt.dataset.phone = p.phone || '';
                    opt.dataset.email = p.email || '';
                    patientEl.appendChild(opt);
                }
            }

            function updatePatientMeta() {
                const opt = patientEl.options[patientEl.selectedIndex];
                if (!patientEl.value) {
                    patientMetaEl.textContent = '';
                    return;
                }
                const phone = opt?.dataset?.phone || '';
                const email = opt?.dataset?.email || '';
                patientMetaEl.textContent = [phone, email].filter(Boolean).join(' • ');
            }

            async function loadTeeth() {
                clearMsg();
                selectedToothCode = null;
                selectedToothLabel.textContent = 'None';
                panel.classList.add('hidden');
                emptyPanel.classList.remove('hidden');
                saveBtn.disabled = true;
                highlightedToothCodes = new Set();
                xrayCompareBefore = null;
                xrayCompareAfter = null;

                if (!patientEl.value) {
                    upperRightRow.innerHTML = '';
                    upperLeftRow.innerHTML = '';
                    lowerRightRow.innerHTML = '';
                    lowerLeftRow.innerHTML = '';
                    setActiveQuadrant(null);
                    allergyBannerEl.classList.add('hidden');
                    allergyBannerEl.textContent = '';
                    allergyIconEl.classList.add('hidden');
                    allergyCountEl.textContent = '';
                    allergiesListEl.innerHTML = '';
                    allergiesEmptyEl.classList.add('hidden');
                    medicalCountEl.textContent = '';
                    medicalSummaryEl.innerHTML = '';
                    medicalEmptyEl.classList.add('hidden');
                    proceduresListEl.innerHTML = '';
                    proceduresEmptyEl.classList.add('hidden');
                    procedureCountEl.textContent = '';
                    xraysListEl.innerHTML = '';
                    xraysEmptyEl.classList.add('hidden');
                    xrayCountEl.textContent = '';
                    return;
                }

                const url = `/api/patients/${encodeURIComponent(patientEl.value)}/teeth?dentition=${encodeURIComponent(dentitionEl.value)}`;
                const { data } = await fetchJson(url);
                renderTeeth(data.teeth);
                await loadAllergies();
                await loadMedicalHistory();
                await loadProcedures();
                await loadVisits();
                await loadXrays();
            }

            function severityOrder(sev) {
                if (sev === 'severe') return 3;
                if (sev === 'moderate') return 2;
                return 1;
            }

            async function loadAllergies() {
                if (!patientEl.value) return;
                const url = `/api/patients/${encodeURIComponent(patientEl.value)}/allergies`;
                const { data } = await fetchJson(url);
                lastAllergies = Array.isArray(data) ? data : [];
                const active = lastAllergies.filter((a) => a && a.is_active);
                if (!active.length) {
                    allergyBannerEl.classList.add('hidden');
                    allergyBannerEl.textContent = '';
                    allergyIconEl.classList.toggle('hidden', !lastAllergies.length);
                    renderAllergies(lastAllergies);
                    return;
                }

                const max = active.reduce((acc, cur) => (severityOrder(cur.severity) > severityOrder(acc.severity) ? cur : acc), active[0]);
                const label = active
                    .slice()
                    .sort((a, b) => severityOrder(b.severity) - severityOrder(a.severity))
                    .map((a) => `${String(a.tag || '').toUpperCase()} (${String(a.severity || '').toUpperCase()})`)
                    .join(' • ');

                allergyBannerEl.classList.remove('hidden');
                allergyBannerEl.className = 'mt-2 rounded-xl border p-3 text-sm';
                if (max.severity === 'severe') {
                    allergyBannerEl.classList.add('border-red-200', 'dark:border-red-800', 'bg-red-50', 'dark:bg-red-950/40', 'text-red-800', 'dark:text-red-200');
                } else if (max.severity === 'moderate') {
                    allergyBannerEl.classList.add('border-amber-200', 'dark:border-amber-800', 'bg-amber-50', 'dark:bg-amber-950/35', 'text-amber-800', 'dark:text-amber-200');
                } else {
                    allergyBannerEl.classList.add('border-slate-200', 'dark:border-slate-700', 'bg-slate-50', 'dark:bg-slate-950/40', 'text-slate-800', 'dark:text-slate-100');
                }
                allergyBannerEl.textContent = `ALLERGIES: ${label}`;
                allergyIconEl.classList.remove('hidden');

                allergyCountEl.textContent = `${active.length} active`;
                renderAllergies(lastAllergies);
            }

            async function loadMedicalHistory() {
                if (!patientEl.value) return;
                const url = `/api/patients/${encodeURIComponent(patientEl.value)}/medical-history?limit=1`;
                const { data } = await fetchJson(url);
                const list = Array.isArray(data) ? data : [];
                lastMedicalHistory = list[0] || null;
                renderMedicalHistory();
            }

            function renderMedicalHistory() {
                medicalSummaryEl.innerHTML = '';
                medicalCountEl.textContent = '';

                const r = lastMedicalHistory;
                if (!r) {
                    medicalEmptyEl.classList.remove('hidden');
                    return;
                }

                medicalEmptyEl.classList.add('hidden');

                const dt = r.recorded_at ? new Date(r.recorded_at) : null;
                const when = dt && !Number.isNaN(dt.getTime()) ? dt.toLocaleString() : (r.recorded_at || '');
                const data = r.data && typeof r.data === 'object' ? r.data : {};
                const conditions = Array.isArray(data.conditions) ? data.conditions : [];
                const meds = Array.isArray(data.medications) ? data.medications : [];

                medicalCountEl.textContent = when ? `Updated ${when}` : '';

                const pills = [];
                if (data.smoker === true) pills.push('<span class="pill tone-red">Smoker</span>');
                if (data.pregnant === true) pills.push('<span class="pill tone-amber">Pregnant</span>');

                medicalSummaryEl.innerHTML = `
                    <div class="rounded-xl border p-3" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                        ${pills.length ? `<div class="flex flex-wrap gap-1.5 mb-2">${pills.join('')}</div>` : ''}
                        <div class="text-xs clinic-subtle">Conditions</div>
                        <div class="text-sm mt-1">${conditions.length ? conditions.map((x) => `<span class="pill">${String(x)}</span>`).join(' ') : '<span class="text-sm clinic-subtle">None</span>'}</div>
                        <div class="text-xs clinic-subtle mt-3">Medications</div>
                        <div class="text-sm mt-1">${meds.length ? meds.map((x) => `<span class="pill">${String(x)}</span>`).join(' ') : '<span class="text-sm clinic-subtle">None</span>'}</div>
                        ${data.notes ? `<div class="text-xs clinic-subtle mt-3">Notes</div><div class="text-sm mt-1 whitespace-pre-wrap">${String(data.notes)}</div>` : ''}
                    </div>
                `;
            }

            function renderAllergies(allergies) {
                const list = Array.isArray(allergies) ? allergies : [];
                allergiesListEl.innerHTML = '';
                const active = list.filter((a) => a && a.is_active);
                allergiesEmptyEl.classList.toggle('hidden', list.length > 0);
                allergyCountEl.textContent = active.length ? `${active.length} active` : (list.length ? `${list.length} total` : '');

                for (const a of list) {
                    const wrap = document.createElement('div');
                    wrap.className = 'rounded-xl border p-3 flex items-start justify-between gap-3';
                    wrap.style.borderColor = 'var(--color-clinic-border)';
                    wrap.style.background = 'var(--color-clinic-surface-2)';

                    const sev = String(a.severity || 'mild');
                    const isActive = Boolean(a.is_active);
                    const sevPill = sev === 'severe'
                        ? '<span class="pill tone-red">Severe</span>'
                        : (sev === 'moderate'
                            ? '<span class="pill tone-amber">Moderate</span>'
                            : '<span class="pill">Mild</span>');
                    const activePill = isActive
                        ? '<span class="pill tone-green">Active</span>'
                        : '<span class="pill">Inactive</span>';

                    wrap.innerHTML = `
                        <div class="min-w-0">
                            <div class="text-sm font-medium">${String(a.tag || '').toUpperCase()}</div>
                            <div class="text-xs clinic-subtle mt-1">${a.notes ? String(a.notes) : ''}</div>
                            <div class="flex flex-wrap gap-1.5 mt-2">${sevPill}${activePill}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            ${canEditAllergies() ? `<button type="button" class="btn" data-allergy-toggle="${a.id}">${isActive ? 'Deactivate' : 'Activate'}</button>` : ''}
                        </div>
                    `;

                    const btn = wrap.querySelector('[data-allergy-toggle]');
                    if (btn) {
                        btn.addEventListener('click', async () => {
                            if (!patientEl.value) return;
                            btn.disabled = true;
                            try {
                                const res = await fetchJson(`/api/patients/${encodeURIComponent(patientEl.value)}/allergies/${encodeURIComponent(a.id)}`, {
                                    method: 'PATCH',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({ is_active: !isActive }),
                                });
                                const updated = res?.data;
                                lastAllergies = lastAllergies.map((x) => (x && x.id === updated?.id ? updated : x));
                                await loadAllergies();
                            } catch (e) {
                                setMsg(e.message || 'Failed to update allergy.', 'error');
                            } finally {
                                btn.disabled = false;
                            }
                        });
                    }

                    allergiesListEl.appendChild(wrap);
                }
            }

            function renderProcedures(procedures) {
                const list = Array.isArray(procedures) ? procedures : [];
                lastProcedures = list;
                procedureCountEl.textContent = list.length ? `${list.length} item(s)` : '';
                proceduresListEl.innerHTML = '';
                highlightedToothCodes = new Set();
                syncProcedureHighlights();

                if (!list.length) {
                    proceduresEmptyEl.classList.remove('hidden');
                    return;
                }
                proceduresEmptyEl.classList.add('hidden');

                for (const p of list) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'w-full text-left rounded-xl border p-3 hover:shadow-sm transition';
                    btn.style.borderColor = 'var(--color-clinic-border)';
                    btn.style.background = 'var(--color-clinic-surface-2)';

                    const dt = p.performed_at ? new Date(p.performed_at) : null;
                    const when = dt && !Number.isNaN(dt.getTime()) ? dt.toLocaleDateString() : (p.performed_at || '');
                    const toothCodes = Array.isArray(p.tooth_codes) ? p.tooth_codes : [];
                    const teethLabel = toothCodes.length ? ` • Teeth ${toothCodes.join(', ')}` : '';
                    const conflicts = Array.isArray(p.allergy_conflicts) ? p.allergy_conflicts : [];
                    const conflictPill = conflicts.length ? `<span class="pill tone-red">Allergy Alert</span>` : '';
                    const followUpAt = p?.meta?.follow_up_suggested_at ? String(p.meta.follow_up_suggested_at) : '';
                    const followUpDt = followUpAt ? new Date(`${followUpAt}T00:00:00`) : null;
                    const isDue = followUpDt && !Number.isNaN(followUpDt.getTime()) ? followUpDt.getTime() <= Date.now() : false;
                    const followUpPill = followUpAt
                        ? (isDue
                            ? `<span class="pill tone-red">Follow-up due</span>`
                            : `<span class="pill tone-blue">Follow-up ${followUpAt}</span>`)
                        : '';

                    btn.innerHTML = `
                        <div class="flex items-center justify-between gap-3">
                            <div class="text-sm font-medium capitalize">${p.procedure_type || 'procedure'}${teethLabel}</div>
                            <div class="flex items-center gap-1.5">${conflictPill}${followUpPill}</div>
                        </div>
                        <div class="text-xs clinic-subtle mt-1">${when}${p.description ? ` • ${p.description}` : ''}</div>
                    `;

                    btn.addEventListener('click', async () => {
                        if (!patientEl.value || !p.id) return;
                        try {
                            const url = `/api/patients/${encodeURIComponent(patientEl.value)}/procedures/${encodeURIComponent(p.id)}/highlights`;
                            const res = await fetchJson(url);
                            const codes = Array.isArray(res?.data?.tooth_codes) ? res.data.tooth_codes : [];
                            highlightedToothCodes = new Set(codes.map(String));
                            syncProcedureHighlights();
                            if (codes[0]) {
                                await selectTooth(String(codes[0]));
                            }
                        } catch (e) {
                            setMsg(e.message || 'Failed to load procedure highlights.', 'error');
                        }
                    });

                    proceduresListEl.appendChild(btn);
                }
            }

            async function loadProcedures() {
                if (!patientEl.value) return;
                const url = `/api/patients/${encodeURIComponent(patientEl.value)}/procedures?limit=20`;
                const { data } = await fetchJson(url);
                renderProcedures(data);
                syncProcedureSelects();
            }

            async function loadVisits() {
                if (!patientEl.value) return;
                const url = `/api/patients/${encodeURIComponent(patientEl.value)}/visits?limit=30`;
                const { data } = await fetchJson(url);
                lastVisits = Array.isArray(data) ? data : [];
                syncVisitSelects();
            }

            async function loadXrays() {
                if (!patientEl.value) return;
                const url = `/api/patients/${encodeURIComponent(patientEl.value)}/xrays?limit=30`;
                const { data } = await fetchJson(url);
                lastXrays = Array.isArray(data) ? data : [];
                renderXrays(lastXrays);
            }

            function syncProcedureSelects() {
                const current = xProcedureId.value;
                xProcedureId.innerHTML = '<option value="">None</option>';
                for (const p of Array.isArray(lastProcedures) ? lastProcedures : []) {
                    const opt = document.createElement('option');
                    opt.value = String(p.id);
                    opt.textContent = `${String(p.procedure_type || 'procedure')} • ${String(p.performed_at || '').slice(0, 10)}`;
                    xProcedureId.appendChild(opt);
                }
                xProcedureId.value = current;
            }

            function syncVisitSelects() {
                const current = xVisitId.value;
                xVisitId.innerHTML = '<option value="">None</option>';
                for (const v of Array.isArray(lastVisits) ? lastVisits : []) {
                    const dt = v.start_at ? new Date(v.start_at) : null;
                    const when = dt && !Number.isNaN(dt.getTime()) ? dt.toLocaleString() : (v.start_at || '');
                    const opt = document.createElement('option');
                    opt.value = String(v.id);
                    opt.textContent = `Visit #${v.id}${when ? ` • ${when}` : ''}`;
                    xVisitId.appendChild(opt);
                }
                xVisitId.value = current;
            }

            function renderXrays(xrays) {
                const list = Array.isArray(xrays) ? xrays : [];
                const sorted = [...list].sort((a, b) => {
                    const aTime = a?.recorded_at ? new Date(a.recorded_at).getTime() : Number.POSITIVE_INFINITY;
                    const bTime = b?.recorded_at ? new Date(b.recorded_at).getTime() : Number.POSITIVE_INFINITY;
                    const aOk = Number.isFinite(aTime);
                    const bOk = Number.isFinite(bTime);
                    if (aOk && bOk && aTime !== bTime) return aTime - bTime;
                    if (aOk && !bOk) return -1;
                    if (!aOk && bOk) return 1;
                    return Number(a?.id || 0) - Number(b?.id || 0);
                });

                xrayCountEl.textContent = sorted.length ? `${sorted.length} file(s)` : '';
                xraysListEl.innerHTML = '';
                syncXrayCompareBar();

                if (!sorted.length) {
                    xraysEmptyEl.classList.remove('hidden');
                    return;
                }
                xraysEmptyEl.classList.add('hidden');

                for (const x of sorted) {
                    const wrap = document.createElement('div');
                    wrap.className = 'rounded-xl border p-3';
                    wrap.style.borderColor = 'var(--color-clinic-border)';
                    wrap.style.background = 'var(--color-clinic-surface-2)';

                    const dt = x.recorded_at ? new Date(x.recorded_at) : null;
                    const when = dt && !Number.isNaN(dt.getTime()) ? dt.toLocaleString() : (x.recorded_at || '');
                    const tooth = x.tooth_code ? `Tooth ${x.tooth_code}` : '';
                    const visitId = x.visit_id ? String(x.visit_id) : '';
                    const visit = visitId ? (Array.isArray(lastVisits) ? lastVisits : []).find((v) => String(v?.id) === visitId) : null;
                    const visitWhen = visit?.start_at ? new Date(visit.start_at) : null;
                    const visitLabel = visit
                        ? `Visit #${visitId}${visitWhen && !Number.isNaN(visitWhen.getTime()) ? ` (${visitWhen.toLocaleString()})` : ''}`
                        : (visitId ? `Visit #${visitId}` : '');

                    const procedureId = x.procedure_id ? String(x.procedure_id) : '';
                    const proc = procedureId ? (Array.isArray(lastProcedures) ? lastProcedures : []).find((p) => String(p?.id) === procedureId) : null;
                    const procType = proc?.procedure_type ? String(proc.procedure_type) : '';
                    const procWhen = proc?.performed_at ? String(proc.performed_at).slice(0, 10) : '';
                    const procLabel = proc
                        ? `${procType || `Procedure #${procedureId}`}${procWhen ? ` (${procWhen})` : ''}`
                        : (procedureId ? `Procedure #${procedureId}` : '');

                    const mime = String(x.mime_type || '');
                    const isPdf = mime.includes('pdf');
                    const isImage = mime.startsWith('image/');
                    const isBefore = xrayCompareBefore?.id === x.id;
                    const isAfter = xrayCompareAfter?.id === x.id;

                    if (isBefore) {
                        wrap.style.borderColor = 'rgba(59,130,246,.65)';
                        wrap.style.background = 'rgba(59,130,246,.06)';
                    } else if (isAfter) {
                        wrap.style.borderColor = 'rgba(16,185,129,.65)';
                        wrap.style.background = 'rgba(16,185,129,.06)';
                    }

                    wrap.innerHTML = `
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-sm font-medium truncate">${String(x.original_name || 'X-Ray')}</div>
                                <div class="text-xs clinic-subtle mt-1">${[when, tooth, visitLabel, procLabel].filter(Boolean).join(' • ')}</div>
                                <div class="flex flex-wrap gap-1.5 mt-2">${isPdf ? '<span class="pill">PDF</span>' : '<span class="pill">Image</span>'}</div>
                            </div>
                            <div class="flex items-center gap-2 flex-wrap justify-end">
                                <button type="button" class="btn" data-xray-view="${x.id}">View</button>
                            </div>
                        </div>
                        ${isImage ? `
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button type="button" class="btn ${isBefore ? 'btn-primary' : ''}" data-xray-before="${x.id}">${isBefore ? 'Before ✓' : 'Set as Before'}</button>
                                <button type="button" class="btn ${isAfter ? 'btn-teal' : ''}" data-xray-after="${x.id}">${isAfter ? 'After ✓' : 'Set as After'}</button>
                            </div>
                        ` : ''}
                    `;

                    const viewBtn = wrap.querySelector('[data-xray-view]');
                    if (viewBtn) {
                        viewBtn.addEventListener('click', () => {
                            if (!x.preview_url) return;
                            if (isPdf) {
                                openFrameModal(x.preview_url, x.original_name || 'PDF');
                                return;
                            }
                            openImageModal(x.preview_url, x.original_name || 'X-Ray');
                        });
                    }

                    const beforeBtn = wrap.querySelector('[data-xray-before]');
                    if (beforeBtn) {
                        beforeBtn.addEventListener('click', () => {
                            if (xrayCompareBefore?.id === x.id) {
                                xrayCompareBefore = null;
                            } else {
                                xrayCompareBefore = x;
                                if (xrayCompareAfter?.id === x.id) xrayCompareAfter = null;
                            }
                            renderXrays(lastXrays);
                        });
                    }
                    const afterBtn = wrap.querySelector('[data-xray-after]');
                    if (afterBtn) {
                        afterBtn.addEventListener('click', () => {
                            if (xrayCompareAfter?.id === x.id) {
                                xrayCompareAfter = null;
                            } else {
                                xrayCompareAfter = x;
                                if (xrayCompareBefore?.id === x.id) xrayCompareBefore = null;
                            }
                            renderXrays(lastXrays);
                        });
                    }

                    xraysListEl.appendChild(wrap);
                }
            }

            function labelFromXray(xray) {
                if (!xray) return 'None';
                const dt = xray.recorded_at ? new Date(xray.recorded_at) : null;
                const when = dt && !Number.isNaN(dt.getTime()) ? dt.toLocaleString() : (xray.recorded_at || '');
                const name = String(xray.original_name || 'X-Ray');
                return when ? `${name} • ${when}` : name;
            }

            function syncXrayCompareBar() {
                if (!xrayCompareBeforeLabel || !xrayCompareAfterLabel || !xrayCompareOpenBtn || !xrayCompareClearBtn) return;

                xrayCompareBeforeLabel.textContent = labelFromXray(xrayCompareBefore);
                xrayCompareAfterLabel.textContent = labelFromXray(xrayCompareAfter);

                const canCompare = Boolean(xrayCompareBefore?.preview_url && xrayCompareAfter?.preview_url);
                xrayCompareOpenBtn.disabled = !canCompare;

                if (xrayCompareBefore?.recorded_at && xrayCompareAfter?.recorded_at) {
                    const beforeDt = new Date(xrayCompareBefore.recorded_at);
                    const afterDt = new Date(xrayCompareAfter.recorded_at);
                    if (!Number.isNaN(beforeDt.getTime()) && !Number.isNaN(afterDt.getTime()) && beforeDt > afterDt) {
                        setMsg('Tip: "Before" is usually earlier than "After". Swap them or adjust "Recorded At".', 'success');
                    }
                }
            }

            async function selectTooth(code) {
                selectedToothCode = code;
                selectedToothLabel.textContent = code;
                emptyPanel.classList.add('hidden');
                panel.classList.remove('hidden');
                saveBtn.disabled = !patientEl.value || !selectedToothCode || !canEditClinical;
                clearMsg();
                highlightSelected();
                setActiveQuadrant(quadrantFromToothCode(code));

                const tooth = teethMap[code];
                conditionEl.value = tooth?.condition || 'healthy';
                recordedAtEl.value = nowForDatetimeLocal();
                procedureEl.value = tooth?.procedure || '';
                notesEl.value = tooth?.notes || '';
                cdtCodeEl.value = '';
                selectedSurfaces = new Set();
                syncSurfaceButtons();
                beforeEl.value = '';
                afterEl.value = '';

                await loadHistory();
                setFormMetaFromEntry(currentHistory[0]);
            }

            function renderHistory() {
                const count = currentHistory.length;
                historyCountEl.textContent = count ? `${count} record(s)` : 'No records yet';
                timelineEl.min = 0;
                timelineEl.max = Math.max(0, count - 1);
                timelineEl.value = 0;

                if (!count) {
                    timelineLabelEl.textContent = '';
                    historyTitleEl.textContent = '';
                    historyMetaEl.innerHTML = '';
                    historyNotesEl.textContent = '';
                    historyImagesEl.innerHTML = '';
                    return;
                }

                renderHistoryIndex(0);
            }

            function openImageModal(url, title) {
                imageModalTitle.textContent = title || 'Image';
                imageSingleEl.classList.remove('hidden');
                imageCompareEl.classList.add('hidden');
                fileFrameWrap.classList.add('hidden');
                fileFrame.src = '';
                imageModalImg.src = url;
                imageModal.classList.remove('hidden');
                imageModal.classList.add('flex');
                resetViewTransform();
            }

            function setComparePercent(pct) {
                const p = Math.max(0, Math.min(100, Number(pct)));
                compareAfterWrap.style.width = `${p}%`;
                compareHandle.style.left = `${p}%`;
                compareSlider.value = String(p);
            }

            function openCompareModal(beforeUrl, afterUrl, title, initialPercent = 50) {
                imageModalTitle.textContent = title || 'Compare';
                imageSingleEl.classList.add('hidden');
                imageCompareEl.classList.remove('hidden');
                fileFrameWrap.classList.add('hidden');
                fileFrame.src = '';
                compareBeforeImg.src = beforeUrl;
                compareAfterImg.src = afterUrl;
                setComparePercent(initialPercent);
                imageModal.classList.remove('hidden');
                imageModal.classList.add('flex');
                resetViewTransform();
            }

            function openFrameModal(url, title) {
                imageModalTitle.textContent = title || 'Preview';
                imageSingleEl.classList.add('hidden');
                imageCompareEl.classList.add('hidden');
                fileFrameWrap.classList.remove('hidden');
                fileFrame.src = url;
                imageModal.classList.remove('hidden');
                imageModal.classList.add('flex');
                resetViewTransform();
            }

            function renderHistoryIndex(idx) {
                const entry = currentHistory[idx];
                if (!entry) return;
                const dt = new Date(entry.recorded_at);
                timelineLabelEl.textContent = `Viewing: ${dt.toLocaleString()}`;
                const proc = entry.procedure ? ` • ${entry.procedure}` : '';
                historyTitleEl.textContent = `${entry.condition}${proc}`;
                historyNotesEl.textContent = entry.notes || '';

                const pills = [];
                const cdt = entry?.meta?.cdt_code;
                const surfaces = Array.isArray(entry?.meta?.surfaces) ? entry.meta.surfaces : [];
                if (cdt) pills.push({ text: `CDT ${cdt}` });
                if (surfaces.length) pills.push({ text: `Surfaces ${surfaces.join('')}` });
                historyMetaEl.innerHTML = pills.map((p) => `<span class="pill font-mono">${p.text}</span>`).join('');

                const parts = [];
                if (entry.image_before_url) {
                    parts.push({ label: 'Before', url: entry.image_before_url });
                }
                if (entry.image_after_url) {
                    parts.push({ label: 'After', url: entry.image_after_url });
                }

                historyImagesEl.innerHTML = '';
                for (const p of parts) {
                    const wrap = document.createElement('button');
                    wrap.type = 'button';
                    wrap.className = 'group rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950/40 overflow-hidden hover:shadow-sm';
                    wrap.innerHTML = `
                        <div class="px-2 py-1 text-xs text-slate-600 dark:text-slate-300 flex items-center justify-between">
                            <span>${p.label}</span>
                            <span class="opacity-0 group-hover:opacity-100 transition">View</span>
                        </div>
                        <div class="h-28 bg-black/5 dark:bg-white/5">
                            <img src="${p.url}" alt="${p.label}" class="w-full h-full object-cover" />
                        </div>
                    `;
                    wrap.addEventListener('click', () => {
                        if (entry.image_before_url && entry.image_after_url) {
                            const pct = p.label === 'After' ? 100 : 0;
                            openCompareModal(entry.image_before_url, entry.image_after_url, `Compare • Tooth ${selectedToothCode}`, pct);
                            return;
                        }
                        openImageModal(p.url, `${p.label} • Tooth ${selectedToothCode}`);
                    });
                    historyImagesEl.appendChild(wrap);
                }
            }

            async function loadHistory() {
                if (!patientEl.value || !selectedToothCode) return;
                const url = `/api/patients/${encodeURIComponent(patientEl.value)}/teeth/${encodeURIComponent(selectedToothCode)}/history`;
                const { data } = await fetchJson(url);
                currentHistory = data.history || [];
                renderHistory();
            }

            timelineEl.addEventListener('input', () => {
                renderHistoryIndex(Number(timelineEl.value || 0));
            });

            closeImageModal.addEventListener('click', () => {
                imageModal.classList.add('hidden');
                imageModal.classList.remove('flex');
                imageModalImg.src = '';
                compareBeforeImg.src = '';
                compareAfterImg.src = '';
                fileFrame.src = '';
                resetViewTransform();
            });

            imageModal.addEventListener('click', (e) => {
                if (e.target === imageModal) {
                    closeImageModal.click();
                }
            });

            compareSlider.addEventListener('input', () => {
                setComparePercent(compareSlider.value);
            });

            compareLeftBtn.addEventListener('click', () => setComparePercent(0));
            compareRightBtn.addEventListener('click', () => setComparePercent(100));

            xrayCompareOpenBtn?.addEventListener('click', () => {
                if (!xrayCompareBefore?.preview_url || !xrayCompareAfter?.preview_url) return;
                openCompareModal(xrayCompareBefore.preview_url, xrayCompareAfter.preview_url, 'X-Ray Compare', 50);
            });

            xrayCompareClearBtn?.addEventListener('click', () => {
                xrayCompareBefore = null;
                xrayCompareAfter = null;
                renderXrays(lastXrays);
            });

            zoomInBtn.addEventListener('click', () => {
                viewScale = Math.min(5, Math.round((viewScale + 0.2) * 10) / 10);
                applyViewTransform();
            });
            zoomOutBtn.addEventListener('click', () => {
                viewScale = Math.max(0.2, Math.round((viewScale - 0.2) * 10) / 10);
                applyViewTransform();
            });
            rotateLeftBtn.addEventListener('click', () => {
                viewRotate = (viewRotate - 90) % 360;
                applyViewTransform();
            });
            rotateRightBtn.addEventListener('click', () => {
                viewRotate = (viewRotate + 90) % 360;
                applyViewTransform();
            });
            resetViewBtn.addEventListener('click', () => {
                resetViewTransform();
            });

            quadUR.addEventListener('click', async (e) => {
                if (e.target.closest('button')) return;
                setActiveQuadrant('UR');
                if (patientEl.value) await selectTooth(quadrantCodes(dentitionEl.value, 'UR')[0]);
            });
            quadUL.addEventListener('click', async (e) => {
                if (e.target.closest('button')) return;
                setActiveQuadrant('UL');
                if (patientEl.value) await selectTooth(quadrantCodes(dentitionEl.value, 'UL')[0]);
            });
            quadLR.addEventListener('click', async (e) => {
                if (e.target.closest('button')) return;
                setActiveQuadrant('LR');
                if (patientEl.value) await selectTooth(quadrantCodes(dentitionEl.value, 'LR')[0]);
            });
            quadLL.addEventListener('click', async (e) => {
                if (e.target.closest('button')) return;
                setActiveQuadrant('LL');
                if (patientEl.value) await selectTooth(quadrantCodes(dentitionEl.value, 'LL')[0]);
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    if (!imageModal.classList.contains('hidden')) {
                        closeImageModal.click();
                        return;
                    }
                    if (!patientModal.classList.contains('hidden')) {
                        closePatientModal.click();
                        return;
                    }
                    if (!allergyModal.classList.contains('hidden')) {
                        closeAllergyModal.click();
                        return;
                    }
                    if (!medicalModal.classList.contains('hidden')) {
                        closeMedicalModal.click();
                        return;
                    }
                    if (!procedureModal.classList.contains('hidden')) {
                        closeProcedureModal.click();
                        return;
                    }
                    if (!xrayModal.classList.contains('hidden')) {
                        closeXrayModal.click();
                        return;
                    }
                }

                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
                    if (!saveBtn.disabled && !panel.classList.contains('hidden')) {
                        e.preventDefault();
                        saveBtn.click();
                    }
                }
            });

            function buildProcedureQuick() {
                const items = [
                    { label: 'Filling', value: 'filling', note: 'Composite filling' },
                    { label: 'Crown', value: 'crown', note: 'Crown recommended' },
                    { label: 'Extraction', value: 'extraction', note: 'Tooth extraction' },
                    { label: 'Cleaning', value: 'cleaning', note: 'Prophylaxis' },
                    { label: 'Root Canal', value: 'root canal', note: 'RCT' },
                    { label: 'Braces', value: 'braces', note: 'Ortho adjustment' },
                ];

                procedureQuick.innerHTML = '';
                for (const it of items) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'clinic-quick-btn';
                    btn.textContent = it.label;
                    btn.addEventListener('click', () => {
                        procedureEl.value = it.value;
                        if (!notesEl.value) notesEl.value = it.note;
                    });
                    procedureQuick.appendChild(btn);
                }
            }

            saveBtn.addEventListener('click', async () => {
                clearMsg();
                if (!patientEl.value || !selectedToothCode) return;

                const form = new FormData();
                form.append('dentition', dentitionEl.value);
                form.append('condition', conditionEl.value);
                if (procedureEl.value) form.append('procedure', procedureEl.value);
                if (notesEl.value) form.append('notes', notesEl.value);
                if (recordedAtEl.value) form.append('recorded_at', recordedAtEl.value);
                if (cdtCodeEl.value) form.append('cdt_code', cdtCodeEl.value);
                for (const s of Array.from(selectedSurfaces)) {
                    form.append('surfaces[]', s);
                }
                if (beforeEl.files[0]) form.append('image_before', beforeEl.files[0]);
                if (afterEl.files[0]) form.append('image_after', afterEl.files[0]);

                saveBtn.disabled = true;
                saveBtn.textContent = 'Saving...';
                try {
                    const url = `/api/patients/${encodeURIComponent(patientEl.value)}/teeth/${encodeURIComponent(selectedToothCode)}/records`;
                    const headers = { 'Accept': 'application/json' };
                    if (csrfToken) headers['X-CSRF-TOKEN'] = csrfToken;
                    const res = await fetch(url, { method: 'POST', body: form, credentials: 'same-origin', headers });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) throw new Error(data?.message || 'Save failed');
                    setMsg('Saved.', 'success');
                    await loadTeeth();
                    await selectTooth(selectedToothCode);
                } catch (e) {
                    setMsg(e.message || 'Save failed', 'error');
                } finally {
                    saveBtn.textContent = 'Save Record';
                    saveBtn.disabled = !patientEl.value || !selectedToothCode || !canEditClinical;
                }
            });

            newPatientBtn.addEventListener('click', () => {
                if (!canCreatePatients) {
                    setMsg('You do not have permission to create patients.', 'error');
                    return;
                }
                setModalMsg('', 'success');
                pName.value = '';
                pPhone.value = '';
                pEmail.value = '';
                pDob.value = '';
                patientModal.classList.remove('hidden');
                patientModal.classList.add('flex');
            });

            closePatientModal.addEventListener('click', () => {
                patientModal.classList.add('hidden');
                patientModal.classList.remove('flex');
            });

            createPatientBtn.addEventListener('click', async () => {
                setModalMsg('', 'success');
                if (!canCreatePatients) {
                    setModalMsg('You do not have permission to create patients.', 'error');
                    return;
                }
                if (!pName.value.trim()) {
                    setModalMsg('Full name is required.', 'error');
                    return;
                }
                const payload = {
                    full_name: pName.value.trim(),
                    phone: pPhone.value.trim() || null,
                    email: pEmail.value.trim() || null,
                    date_of_birth: pDob.value || null,
                };

                createPatientBtn.disabled = true;
                createPatientBtn.textContent = 'Creating...';
                try {
                    await fetchJson('/api/patients', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    setModalMsg('Created.', 'success');
                    await loadPatients();
                } catch (e) {
                    setModalMsg(e.message || 'Create failed.', 'error');
                } finally {
                    createPatientBtn.disabled = false;
                    createPatientBtn.textContent = 'Create';
                }
            });

            function openAllergyModal() {
                setAllergyModalMsg('', 'success');
                aTag.value = '';
                aSeverity.value = 'mild';
                aActive.checked = true;
                aNotes.value = '';
                allergyModal.classList.remove('hidden');
                allergyModal.classList.add('flex');
            }

            function closeAllergyModalUi() {
                allergyModal.classList.add('hidden');
                allergyModal.classList.remove('flex');
            }

            newAllergyBtn.addEventListener('click', () => {
                if (!canEditAllergies()) {
                    setMsg('You do not have permission to edit allergies.', 'error');
                    return;
                }
                if (!patientEl.value) return;
                openAllergyModal();
            });

            closeAllergyModal.addEventListener('click', closeAllergyModalUi);
            allergyModal.addEventListener('click', (e) => {
                if (e.target === allergyModal) closeAllergyModalUi();
            });

            createAllergyBtn.addEventListener('click', async () => {
                setAllergyModalMsg('', 'success');
                if (!patientEl.value) return;
                if (!aTag.value.trim()) {
                    setAllergyModalMsg('Tag is required.', 'error');
                    return;
                }

                createAllergyBtn.disabled = true;
                createAllergyBtn.textContent = 'Saving...';
                try {
                    await fetchJson(`/api/patients/${encodeURIComponent(patientEl.value)}/allergies`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            tag: aTag.value.trim(),
                            severity: aSeverity.value,
                            is_active: aActive.checked,
                            notes: aNotes.value.trim() || null,
                        }),
                    });
                    setAllergyModalMsg('Saved.', 'success');
                    await loadAllergies();
                    closeAllergyModalUi();
                } catch (e) {
                    setAllergyModalMsg(e.message || 'Save failed.', 'error');
                } finally {
                    createAllergyBtn.disabled = false;
                    createAllergyBtn.textContent = 'Save Allergy';
                }
            });

            function parseCsvTokens(text) {
                return String(text || '')
                    .split(/[,\n]/g)
                    .map((v) => v.trim())
                    .filter((v) => v);
            }

            function openMedicalModal() {
                if (!patientEl.value) return;
                setMedicalModalMsg('', 'success');

                const r = lastMedicalHistory;
                const data = r?.data && typeof r.data === 'object' ? r.data : {};

                const conditions = Array.isArray(data.conditions) ? data.conditions : [];
                const medications = Array.isArray(data.medications) ? data.medications : [];

                mConditions.value = conditions.map(String).join(', ');
                mMedications.value = medications.map(String).join(', ');
                mSmoker.checked = data.smoker === true;
                mPregnant.checked = data.pregnant === true;
                mNotes.value = typeof data.notes === 'string' ? data.notes : '';

                const dt = r?.recorded_at ? new Date(r.recorded_at) : null;
                if (dt && !Number.isNaN(dt.getTime())) {
                    const pad = (n) => String(n).padStart(2, '0');
                    const yyyy = dt.getFullYear();
                    const mm = pad(dt.getMonth() + 1);
                    const dd = pad(dt.getDate());
                    const hh = pad(dt.getHours());
                    const mi = pad(dt.getMinutes());
                    mRecordedAt.value = `${yyyy}-${mm}-${dd}T${hh}:${mi}`;
                } else {
                    mRecordedAt.value = nowForDatetimeLocal();
                }

                saveMedicalBtn.disabled = !canEditMedicalHistory();
                medicalModal.classList.remove('hidden');
                medicalModal.classList.add('flex');
            }

            function closeMedicalModalUi() {
                medicalModal.classList.add('hidden');
                medicalModal.classList.remove('flex');
            }

            editMedicalBtn.addEventListener('click', () => {
                if (!canEditMedicalHistory()) {
                    setMsg('Only dentists/admins can update medical history.', 'error');
                    return;
                }
                openMedicalModal();
            });

            closeMedicalModal.addEventListener('click', closeMedicalModalUi);
            medicalModal.addEventListener('click', (e) => {
                if (e.target === medicalModal) closeMedicalModalUi();
            });

            saveMedicalBtn.addEventListener('click', async () => {
                setMedicalModalMsg('', 'success');
                if (!patientEl.value) return;
                if (!canEditMedicalHistory()) {
                    setMedicalModalMsg('Only dentists/admins can update medical history.', 'error');
                    return;
                }

                const payload = {
                    conditions: parseCsvTokens(mConditions.value),
                    medications: parseCsvTokens(mMedications.value),
                    smoker: mSmoker.checked,
                    pregnant: mPregnant.checked,
                    notes: mNotes.value.trim() || null,
                };

                const recordedAt = mRecordedAt.value ? new Date(mRecordedAt.value).toISOString() : null;

                saveMedicalBtn.disabled = true;
                saveMedicalBtn.textContent = 'Saving...';
                try {
                    const res = await fetchJson(`/api/patients/${encodeURIComponent(patientEl.value)}/medical-history`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            data: payload,
                            recorded_at: recordedAt,
                        }),
                    });
                    lastMedicalHistory = res?.data || null;
                    renderMedicalHistory();
                    setMedicalModalMsg('Saved.', 'success');
                    closeMedicalModalUi();
                } catch (e) {
                    setMedicalModalMsg(e.message || 'Save failed.', 'error');
                } finally {
                    saveMedicalBtn.disabled = false;
                    saveMedicalBtn.textContent = 'Save Medical History';
                }
            });

            function renderProcedureDraftTeeth() {
                prTeethChips.innerHTML = '';
                const codes = Array.from(procedureDraftToothCodes);
                for (const code of codes) {
                    const chip = document.createElement('button');
                    chip.type = 'button';
                    chip.className = 'pill font-mono';
                    chip.textContent = `Tooth ${code} ×`;
                    chip.addEventListener('click', () => {
                        procedureDraftToothCodes.delete(code);
                        renderProcedureDraftTeeth();
                    });
                    prTeethChips.appendChild(chip);
                }
                if (!codes.length) {
                    const t = document.createElement('div');
                    t.className = 'text-xs clinic-subtle';
                    t.textContent = 'No teeth linked yet.';
                    prTeethChips.appendChild(t);
                }
            }

            function openProcedureModal() {
                if (!patientEl.value) return;
                setProcedureModalMsg('', 'success');
                prType.value = (procedureEl.value || '').trim();
                prPerformedAt.value = nowForDatetimeLocal();
                prDescription.value = '';
                prCost.value = '';
                prConfirmConflict.checked = false;
                prConflictBox.classList.add('hidden');
                prConflictBox.textContent = '';
                procedureDraftToothCodes = new Set();
                if (selectedToothCode) {
                    procedureDraftToothCodes.add(String(selectedToothCode));
                }
                renderProcedureDraftTeeth();
                procedureModal.classList.remove('hidden');
                procedureModal.classList.add('flex');
            }

            function closeProcedureModalUi() {
                procedureModal.classList.add('hidden');
                procedureModal.classList.remove('flex');
            }

            newProcedureBtn.addEventListener('click', () => {
                if (!canCreateProcedures()) {
                    setMsg('Only dentists/admins can create procedures.', 'error');
                    return;
                }
                openProcedureModal();
            });

            closeProcedureModal.addEventListener('click', closeProcedureModalUi);
            procedureModal.addEventListener('click', (e) => {
                if (e.target === procedureModal) closeProcedureModalUi();
            });

            prAddSelectedToothBtn.addEventListener('click', () => {
                if (!selectedToothCode) return;
                procedureDraftToothCodes.add(String(selectedToothCode));
                renderProcedureDraftTeeth();
            });

            createProcedureBtn.addEventListener('click', async () => {
                setProcedureModalMsg('', 'success');
                if (!patientEl.value) return;
                const type = prType.value.trim();
                if (!type) {
                    setProcedureModalMsg('Procedure type is required.', 'error');
                    return;
                }
                if (!prPerformedAt.value) {
                    setProcedureModalMsg('Performed at is required.', 'error');
                    return;
                }

                const php = prCost.value ? Number(prCost.value) : null;
                const costCents = php === null || Number.isNaN(php) ? null : Math.round(php * 100);
                const toothCodes = Array.from(procedureDraftToothCodes);

                createProcedureBtn.disabled = true;
                createProcedureBtn.textContent = 'Saving...';
                try {
                    const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
                    if (csrfToken) headers['X-CSRF-TOKEN'] = csrfToken;
                    const res = await fetch(`/api/patients/${encodeURIComponent(patientEl.value)}/procedures`, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers,
                        body: JSON.stringify({
                            procedure_type: type,
                            performed_at: new Date(prPerformedAt.value).toISOString(),
                            description: prDescription.value.trim() || null,
                            cost_cents: costCents,
                            tooth_codes: toothCodes.length ? toothCodes : null,
                            surfaces: Array.from(selectedSurfaces),
                            confirm_allergy_conflicts: prConfirmConflict.checked,
                        }),
                    });
                    const payload = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        const msg = payload?.message || 'Save failed.';
                        if (res.status === 409 && Array.isArray(payload?.data?.conflicts)) {
                            prConflictBox.classList.remove('hidden');
                            prConflictBox.textContent = `Allergy conflict: ${payload.data.conflicts.map(String).join(', ').toUpperCase()}. Dentist confirmation is required.`;
                        }
                        throw new Error(msg);
                    }
                    setProcedureModalMsg('Saved.', 'success');
                    await loadProcedures();
                    closeProcedureModalUi();
                } catch (e) {
                    const msg = e.message || 'Save failed.';
                    setProcedureModalMsg(msg, 'error');
                    if (msg.toLowerCase().includes('allergy conflict')) {
                        prConflictBox.classList.remove('hidden');
                        prConflictBox.textContent = 'Allergy conflict detected. Dentist confirmation is required to proceed.';
                    }
                } finally {
                    createProcedureBtn.disabled = false;
                    createProcedureBtn.textContent = 'Save Procedure';
                }
            });

            function openXrayModal() {
                if (!patientEl.value) return;
                setXrayModalMsg('', 'success');
                xFile.value = '';
                xRecordedAt.value = nowForDatetimeLocal();
                xToothCode.value = selectedToothCode ? String(selectedToothCode) : '';
                syncProcedureSelects();
                syncVisitSelects();
                xrayModal.classList.remove('hidden');
                xrayModal.classList.add('flex');
            }

            function closeXrayModalUi() {
                xrayModal.classList.add('hidden');
                xrayModal.classList.remove('flex');
            }

            uploadXrayBtn.addEventListener('click', () => {
                if (!canUploadXrays()) {
                    setMsg('Only dentists/admins can upload X-rays.', 'error');
                    return;
                }
                openXrayModal();
            });

            closeXrayModal.addEventListener('click', closeXrayModalUi);
            xrayModal.addEventListener('click', (e) => {
                if (e.target === xrayModal) closeXrayModalUi();
            });

            uploadXraySubmitBtn.addEventListener('click', async () => {
                setXrayModalMsg('', 'success');
                if (!patientEl.value) return;
                if (!xFile.files || !xFile.files[0]) {
                    setXrayModalMsg('File is required.', 'error');
                    return;
                }

                const form = new FormData();
                form.append('file', xFile.files[0]);
                if (xRecordedAt.value) form.append('recorded_at', new Date(xRecordedAt.value).toISOString());
                if (xToothCode.value.trim()) form.append('tooth_code', xToothCode.value.trim());
                if (xProcedureId.value) form.append('procedure_id', xProcedureId.value);
                if (xVisitId.value) form.append('visit_id', xVisitId.value);

                uploadXraySubmitBtn.disabled = true;
                uploadXraySubmitBtn.textContent = 'Uploading...';
                try {
                    const headers = { 'Accept': 'application/json' };
                    if (csrfToken) headers['X-CSRF-TOKEN'] = csrfToken;
                    const res = await fetch(`/api/patients/${encodeURIComponent(patientEl.value)}/xrays`, {
                        method: 'POST',
                        body: form,
                        credentials: 'same-origin',
                        headers,
                    });
                    const payload = await res.json().catch(() => ({}));
                    if (!res.ok) throw new Error(payload?.message || 'Upload failed.');
                    setXrayModalMsg('Uploaded.', 'success');
                    await loadXrays();
                    closeXrayModalUi();
                } catch (e) {
                    setXrayModalMsg(e.message || 'Upload failed.', 'error');
                } finally {
                    uploadXraySubmitBtn.disabled = false;
                    uploadXraySubmitBtn.textContent = 'Upload';
                }
            });

            patientEl.addEventListener('change', async () => {
                updatePatientMeta();
                await loadTeeth();
            });
            dentitionEl.addEventListener('change', loadTeeth);

            newPatientBtn.classList.toggle('hidden', !canCreatePatients);
            newAllergyBtn.classList.toggle('hidden', !canEditAllergies());
            newProcedureBtn.classList.toggle('hidden', !canCreateProcedures());
            uploadXrayBtn.classList.toggle('hidden', !canUploadXrays());

            buildProcedureQuick();
            buildSurfaceButtons();

            loadPatients()
                .then(async () => {
                    const params = new URLSearchParams(window.location.search);
                    const patientId = params.get('patient_id');
                    if (patientId) {
                        patientEl.value = String(patientId);
                    }
                    updatePatientMeta();
                    if (patientId && patientEl.value) {
                        await loadTeeth();
                    }
                })
                .catch((e) => setMsg(e.message || 'Failed to load patients.', 'error'));
        </script>
    </body>
</html>
