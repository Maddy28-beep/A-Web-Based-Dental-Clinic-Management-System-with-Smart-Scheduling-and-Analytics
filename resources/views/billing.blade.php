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

        <title>{{ config('app.name', 'Skye Dental') }} - Billing</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="clinic-shell">
        <div class="max-w-6xl mx-auto p-4 md:p-8">
            <div class="clinic-header mb-6">
                <div class="clinic-brand">
                    <img src="{{ asset('logo.png') }}" alt="{{ config('app.name', 'Skye Dental') }} logo" class="h-11 w-11 rounded-2xl" style="box-shadow: var(--shadow-clinic-card);" />
                    <div>
                        <div class="text-xs clinic-subtle tracking-wide uppercase">{{ config('app.name', 'Skye Dental') }}</div>
                        <h1 class="text-2xl md:text-3xl font-semibold leading-tight">Billing Dashboard</h1>
                        <p class="text-sm clinic-subtle mt-1">Track balances, record payments, and review receipts.</p>
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
                                <h2 class="text-base font-medium">Daily Summary</h2>
                                <div class="text-sm clinic-subtle">Payments grouped by method, refunds, and outstanding balances.</div>
                            </div>
                            <div class="flex gap-2 items-end">
                                <div>
                                    <label class="block text-xs clinic-subtle mb-1" for="summaryDate">Date</label>
                                    <input id="summaryDate" type="date" class="clinic-input" />
                                </div>
                                <button id="refreshSummaryBtn" class="btn">Refresh</button>
                            </div>
                        </div>

                        <div id="summaryMsg" class="text-sm"></div>

                        <div class="grid md:grid-cols-2 gap-4 mt-4">
                            <div class="rounded-xl border p-4" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                                <div class="text-sm font-medium mb-2">Payments</div>
                                <div id="paymentsSummary" class="text-sm clinic-subtle">Loading...</div>
                            </div>
                            <div class="rounded-xl border p-4" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                                <div class="text-sm font-medium mb-2">Outstanding</div>
                                <div id="outstandingSummary" class="text-sm clinic-subtle">Loading...</div>
                            </div>
                        </div>
                    </div>

                    <div class="clinic-card">
                        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-4">
                            <div>
                                <h2 class="text-base font-medium">Bills</h2>
                                <div class="text-sm clinic-subtle">Latest bills with quick status filters.</div>
                            </div>
                            <div class="flex gap-2 items-end">
                                <div>
                                    <label class="block text-xs clinic-subtle mb-1" for="billStatus">Status</label>
                                    <select id="billStatus" class="clinic-input">
                                        <option value="">All</option>
                                        <option value="unpaid">Unpaid</option>
                                        <option value="partial">Partial</option>
                                        <option value="paid">Paid</option>
                                        <option value="overdue">Overdue</option>
                                    </select>
                                </div>
                                <div class="flex items-center gap-2 pb-2">
                                    <input id="overdueOnly" type="checkbox" class="h-4 w-4" />
                                    <label for="overdueOnly" class="text-sm">Overdue only</label>
                                </div>
                                <button id="refreshBillsBtn" class="btn">Refresh</button>
                            </div>
                        </div>

                        <div id="billsMsg" class="text-sm"></div>
                        <div class="overflow-x-auto rounded-xl border" style="border-color: var(--color-clinic-border);">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50/80 dark:bg-slate-950/40">
                                    <tr class="text-left">
                                        <th class="px-3 py-2 font-medium">Bill</th>
                                        <th class="px-3 py-2 font-medium">Patient</th>
                                        <th class="px-3 py-2 font-medium">Status</th>
                                        <th class="px-3 py-2 font-medium">Total</th>
                                        <th class="px-3 py-2 font-medium">Balance</th>
                                        <th class="px-3 py-2 font-medium">Due</th>
                                    </tr>
                                </thead>
                                <tbody id="billsTable"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="clinic-card lg:sticky lg:top-6">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="font-medium">Bill Details</h2>
                        <div class="text-xs clinic-subtle">Selected: <span id="selectedBillLabel" class="font-mono">None</span></div>
                    </div>

                    <div id="billDetailsEmpty" class="text-sm clinic-subtle">
                        Select a bill from the list to view items, payments, receipts, and refunds.
                    </div>

                    <div id="billDetails" class="hidden space-y-4">
                        <div class="rounded-xl border p-3" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                            <div class="grid grid-cols-2 gap-2 text-sm">
                                <div class="clinic-subtle">Status</div>
                                <div id="detailStatus" class="text-right font-medium"></div>
                                <div class="clinic-subtle">Total</div>
                                <div id="detailTotal" class="text-right font-medium"></div>
                                <div class="clinic-subtle">Paid</div>
                                <div id="detailPaid" class="text-right font-medium"></div>
                                <div class="clinic-subtle">Balance</div>
                                <div id="detailBalance" class="text-right font-medium"></div>
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <div class="text-sm font-medium">Record Payment</div>
                                <div id="lockBadge" class="text-[11px] px-2 py-0.5 rounded-full border hidden" style="border-color: rgba(16,185,129,.25); background: rgba(16,185,129,.12); color: rgb(6,95,70);">Locked</div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div class="col-span-2">
                                    <label class="block text-xs clinic-subtle mb-1" for="payMethod">Method</label>
                                    <select id="payMethod" class="clinic-input">
                                        <option value="cash">Cash</option>
                                        <option value="gcash">GCash</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="installment">Installment</option>
                                    </select>
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-xs clinic-subtle mb-1" for="payAmount">Amount (PHP)</label>
                                    <input id="payAmount" type="number" min="1" step="0.01" class="clinic-input" placeholder="0.00" />
                                </div>
                                <button id="recordPaymentBtn" class="w-full btn btn-primary col-span-2">Record Payment</button>
                            </div>
                            <div id="paymentMsg" class="text-sm mt-2"></div>
                        </div>

                        <div>
                            <div class="text-sm font-medium mb-2">Items</div>
                            <div id="itemsList" class="space-y-2"></div>
                        </div>

                        <div>
                            <div class="text-sm font-medium mb-2">Payments & Receipts</div>
                            <div id="paymentsList" class="space-y-2"></div>
                        </div>

                        <div>
                            <div class="text-sm font-medium mb-2">Refunds</div>
                            <div id="refundsList" class="space-y-2 text-sm clinic-subtle"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="refundModal" class="fixed inset-0 bg-black/70 hidden items-center justify-center p-4">
                <div class="clinic-card w-full max-w-md">
                    <div class="flex items-center justify-between mb-2">
                        <div class="font-medium">Refund Payment</div>
                        <button id="closeRefundModal" class="btn">Close</button>
                    </div>
                    <div class="grid gap-3">
                        <div class="text-xs clinic-subtle">Payment: <span id="refundPaymentLabel" class="font-mono"></span></div>
                        <div>
                            <label class="block text-xs clinic-subtle mb-1" for="refundAmount">Amount (PHP)</label>
                            <input id="refundAmount" type="number" min="1" step="0.01" class="clinic-input" placeholder="0.00" />
                        </div>
                        <div>
                            <label class="block text-xs clinic-subtle mb-1" for="refundReason">Reason</label>
                            <input id="refundReason" type="text" class="clinic-input" maxlength="255" placeholder="e.g. Duplicate charge" />
                        </div>
                        <button id="submitRefundBtn" class="w-full btn btn-primary">Submit Refund</button>
                        <div id="refundMsg" class="text-sm"></div>
                    </div>
                </div>
            </div>
        </div>

        <a class="clinic-fab" href="/" aria-label="Book appointment">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 2v3M16 2v3M3 9h18"/><path d="M5 5h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"/><path d="M8 13h4M8 17h7"/></svg>
            <span>Book</span>
        </a>

        <script>
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const canRefund = @json((bool) (auth()->user()?->hasPermission('refunds.create') ?? false));

            const summaryDateEl = document.getElementById('summaryDate');
            const refreshSummaryBtn = document.getElementById('refreshSummaryBtn');
            const summaryMsgEl = document.getElementById('summaryMsg');
            const paymentsSummaryEl = document.getElementById('paymentsSummary');
            const outstandingSummaryEl = document.getElementById('outstandingSummary');

            const billStatusEl = document.getElementById('billStatus');
            const overdueOnlyEl = document.getElementById('overdueOnly');
            const refreshBillsBtn = document.getElementById('refreshBillsBtn');
            const billsMsgEl = document.getElementById('billsMsg');
            const billsTableEl = document.getElementById('billsTable');

            const selectedBillLabelEl = document.getElementById('selectedBillLabel');
            const billDetailsEmptyEl = document.getElementById('billDetailsEmpty');
            const billDetailsEl = document.getElementById('billDetails');
            const detailStatusEl = document.getElementById('detailStatus');
            const detailTotalEl = document.getElementById('detailTotal');
            const detailPaidEl = document.getElementById('detailPaid');
            const detailBalanceEl = document.getElementById('detailBalance');
            const lockBadgeEl = document.getElementById('lockBadge');

            const payMethodEl = document.getElementById('payMethod');
            const payAmountEl = document.getElementById('payAmount');
            const recordPaymentBtn = document.getElementById('recordPaymentBtn');
            const paymentMsgEl = document.getElementById('paymentMsg');
            const itemsListEl = document.getElementById('itemsList');
            const paymentsListEl = document.getElementById('paymentsList');
            const refundsListEl = document.getElementById('refundsList');

            const refundModalEl = document.getElementById('refundModal');
            const closeRefundModalBtn = document.getElementById('closeRefundModal');
            const refundPaymentLabelEl = document.getElementById('refundPaymentLabel');
            const refundAmountEl = document.getElementById('refundAmount');
            const refundReasonEl = document.getElementById('refundReason');
            const submitRefundBtn = document.getElementById('submitRefundBtn');
            const refundMsgEl = document.getElementById('refundMsg');

            let selectedBillId = null;
            let selectedPaymentId = null;

            function todayForDateInput() {
                const d = new Date();
                const pad = (n) => String(n).padStart(2, '0');
                return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
            }

            function fmtMoney(cents) {
                const n = Number(cents || 0) / 100;
                return `₱${n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            }

            function escapeHtml(s) {
                return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
            }

            async function api(url, opts = {}) {
                const res = await fetch(url, {
                    credentials: 'same-origin',
                    ...opts,
                    headers: {
                        'Accept': 'application/json',
                        ...(opts.body ? { 'Content-Type': 'application/json' } : {}),
                        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                        ...(opts.headers || {}),
                    },
                });

                const text = await res.text();
                let json = null;
                try {
                    json = text ? JSON.parse(text) : null;
                } catch {
                    json = null;
                }

                if (!res.ok) {
                    const message = json?.message || (json?.errors ? Object.values(json.errors).flat().join(' ') : null) || res.statusText;
                    throw new Error(message || 'Request failed.');
                }
                return json;
            }

            function setMsg(el, msg, tone = 'info') {
                el.textContent = msg || '';
                el.classList.remove('text-red-700', 'dark:text-red-300', 'text-emerald-700', 'dark:text-emerald-300', 'clinic-subtle');
                if (!msg) {
                    el.classList.add('clinic-subtle');
                    return;
                }
                if (tone === 'error') el.classList.add('text-red-700', 'dark:text-red-300');
                else if (tone === 'success') el.classList.add('text-emerald-700', 'dark:text-emerald-300');
                else el.classList.add('clinic-subtle');
            }

            async function loadSummary() {
                setMsg(summaryMsgEl, '');
                paymentsSummaryEl.textContent = 'Loading...';
                outstandingSummaryEl.textContent = 'Loading...';
                const date = summaryDateEl.value || todayForDateInput();

                try {
                    const data = await api(`/api/billing/summary?date=${encodeURIComponent(date)}`);
                    const payments = Array.isArray(data?.data?.payments) ? data.data.payments : [];
                    const refunds = data?.data?.refunds || { total_cents: 0, count: 0 };
                    const outstanding = Array.isArray(data?.data?.outstanding) ? data.data.outstanding : [];

                    if (payments.length === 0) {
                        paymentsSummaryEl.textContent = 'No payments recorded.';
                    } else {
                        paymentsSummaryEl.innerHTML = payments.map((p) => {
                            const method = escapeHtml(p.method || 'unknown');
                            return `<div class="flex items-center justify-between gap-2"><div class="font-mono text-xs">${method}</div><div class="text-right">${fmtMoney(p.total_cents)} <span class="clinic-subtle">(${Number(p.count || 0)})</span></div></div>`;
                        }).join('');
                    }

                    const refundLine = `<div class="flex items-center justify-between gap-2"><div>Refunds</div><div class="text-right">${fmtMoney(refunds.total_cents)} <span class="clinic-subtle">(${Number(refunds.count || 0)})</span></div></div>`;
                    const outLines = outstanding.map((o) => {
                        const status = escapeHtml(o.status || 'unknown');
                        return `<div class="flex items-center justify-between gap-2"><div class="font-mono text-xs">${status}</div><div class="text-right">${fmtMoney(o.total_cents)} <span class="clinic-subtle">(${Number(o.count || 0)})</span></div></div>`;
                    }).join('');
                    outstandingSummaryEl.innerHTML = refundLine + (outLines ? `<div class="mt-2">${outLines}</div>` : `<div class="mt-2 clinic-subtle">No outstanding bills.</div>`);
                } catch (e) {
                    paymentsSummaryEl.textContent = 'Failed to load.';
                    outstandingSummaryEl.textContent = 'Failed to load.';
                    setMsg(summaryMsgEl, e.message || 'Failed to load summary.', 'error');
                }
            }

            function statusBadge(status) {
                const v = String(status || '').toLowerCase();
                const base = 'inline-flex items-center px-2 py-0.5 rounded-full border text-[11px] font-medium';
                if (v === 'paid') return `<span class="${base}" style="border-color: rgba(16,185,129,.25); background: rgba(16,185,129,.12); color: rgb(6,95,70);">PAID</span>`;
                if (v === 'partial') return `<span class="${base}" style="border-color: rgba(234,179,8,.35); background: rgba(234,179,8,.12); color: rgb(113,63,18);">PARTIAL</span>`;
                if (v === 'overdue') return `<span class="${base}" style="border-color: rgba(239,68,68,.35); background: rgba(239,68,68,.12); color: rgb(153,27,27);">OVERDUE</span>`;
                return `<span class="${base}" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">UNPAID</span>`;
            }

            async function loadBills() {
                billsTableEl.innerHTML = '';
                setMsg(billsMsgEl, '');

                const params = new URLSearchParams();
                params.set('limit', '50');
                if (billStatusEl.value) params.set('status', billStatusEl.value);
                if (overdueOnlyEl.checked) params.set('overdue_only', '1');

                try {
                    const res = await api(`/api/bills?${params.toString()}`);
                    const bills = Array.isArray(res?.data) ? res.data : [];

                    if (bills.length === 0) {
                        billsTableEl.innerHTML = `<tr><td colspan="6" class="px-3 py-3 clinic-subtle">No bills found.</td></tr>`;
                        return;
                    }

                    billsTableEl.innerHTML = bills.map((b) => {
                        const id = Number(b.id);
                        const patient = escapeHtml(b.patient?.full_name || 'Unknown');
                        const due = b.due_at ? escapeHtml(String(b.due_at).slice(0, 10)) : '—';
                        const isActive = selectedBillId === id;
                        const rowClass = isActive ? 'bg-slate-100/70 dark:bg-slate-900/40' : '';

                        return `
                            <tr data-bill-id="${id}" class="cursor-pointer ${rowClass} hover:bg-slate-100/70 dark:hover:bg-slate-900/40">
                                <td class="px-3 py-2 font-mono text-xs">#${id}</td>
                                <td class="px-3 py-2">${patient}</td>
                                <td class="px-3 py-2">${statusBadge(b.status)}</td>
                                <td class="px-3 py-2">${fmtMoney(b.total_cents)}</td>
                                <td class="px-3 py-2">${fmtMoney(b.balance_cents)}</td>
                                <td class="px-3 py-2">${due}</td>
                            </tr>
                        `;
                    }).join('');

                    billsTableEl.querySelectorAll('tr[data-bill-id]').forEach((tr) => {
                        tr.addEventListener('click', () => {
                            const id = Number(tr.dataset.billId);
                            selectBill(id);
                        });
                    });
                } catch (e) {
                    setMsg(billsMsgEl, e.message || 'Failed to load bills.', 'error');
                    billsTableEl.innerHTML = `<tr><td colspan="6" class="px-3 py-3 clinic-subtle">Failed to load.</td></tr>`;
                }
            }

            function setBillDetailsVisible(isOn) {
                billDetailsEmptyEl.classList.toggle('hidden', isOn);
                billDetailsEl.classList.toggle('hidden', !isOn);
            }

            function renderBillDetails(payload) {
                const bill = payload?.bill || null;
                const refunds = Array.isArray(payload?.refunds) ? payload.refunds : [];
                if (!bill) {
                    selectedBillLabelEl.textContent = 'None';
                    setBillDetailsVisible(false);
                    return;
                }

                selectedBillLabelEl.textContent = `#${bill.id}`;
                setBillDetailsVisible(true);

                detailStatusEl.innerHTML = statusBadge(bill.status);
                detailTotalEl.textContent = fmtMoney(bill.total_cents);
                detailPaidEl.textContent = fmtMoney(bill.paid_cents);
                detailBalanceEl.textContent = fmtMoney(bill.balance_cents);
                lockBadgeEl.classList.toggle('hidden', !bill.locked_at);

                const items = Array.isArray(bill.items) ? bill.items : [];
                itemsListEl.innerHTML = items.length === 0
                    ? `<div class="text-sm clinic-subtle">No items.</div>`
                    : items.map((it) => {
                        const t = escapeHtml(it.procedure_type || '');
                        const toothCount = Number(it.tooth_count || 0);
                        const total = fmtMoney(it.total_cents);
                        return `<div class="rounded-xl border p-3" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <div class="text-sm font-medium">${t}</div>
                                    <div class="text-xs clinic-subtle">Teeth: ${toothCount}</div>
                                </div>
                                <div class="text-sm font-medium">${total}</div>
                            </div>
                        </div>`;
                    }).join('');

                const payments = Array.isArray(bill.payments) ? bill.payments : [];
                paymentsListEl.innerHTML = payments.length === 0
                    ? `<div class="text-sm clinic-subtle">No payments.</div>`
                    : payments.map((p) => {
                        const id = Number(p.id);
                        const receiptNo = p.receipt?.receipt_number ? `#${p.receipt.receipt_number}` : '—';
                        const method = escapeHtml(p.method || '');
                        const amount = fmtMoney(p.amount_cents);
                        const paidAt = p.paid_at ? escapeHtml(String(p.paid_at).replace('T', ' ').slice(0, 19)) : '—';
                        const showRefund = canRefund;
                        return `<div class="rounded-xl border p-3" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <div class="text-sm font-medium">${amount} <span class="clinic-subtle text-xs">(${method})</span></div>
                                    <div class="text-xs clinic-subtle">Receipt ${escapeHtml(receiptNo)} • ${paidAt}</div>
                                </div>
                                ${showRefund ? `<button class="btn refundBtn" data-payment-id="${id}" data-payment-label="#${id} ${amount}">Refund</button>` : ''}
                            </div>
                        </div>`;
                    }).join('');

                paymentsListEl.querySelectorAll('.refundBtn').forEach((btn) => {
                    btn.addEventListener('click', () => {
                        selectedPaymentId = Number(btn.dataset.paymentId);
                        refundPaymentLabelEl.textContent = btn.dataset.paymentLabel || '';
                        refundAmountEl.value = '';
                        refundReasonEl.value = '';
                        refundMsgEl.textContent = '';
                        refundModalEl.classList.remove('hidden');
                        refundModalEl.classList.add('flex');
                    });
                });

                refundsListEl.innerHTML = refunds.length === 0
                    ? `<div class="text-sm clinic-subtle">No refunds.</div>`
                    : refunds.map((r) => {
                        const amount = fmtMoney(r.amount_cents);
                        const reason = escapeHtml(r.reason || '');
                        const at = r.refunded_at ? escapeHtml(String(r.refunded_at).replace('T', ' ').slice(0, 19)) : '—';
                        return `<div class="rounded-xl border p-3" style="border-color: var(--color-clinic-border); background: var(--color-clinic-surface-2);">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <div class="text-sm font-medium">${amount}</div>
                                    <div class="text-xs clinic-subtle">${reason} • ${at}</div>
                                </div>
                                <div class="text-xs clinic-subtle font-mono">#${Number(r.id)}</div>
                            </div>
                        </div>`;
                    }).join('');
            }

            async function selectBill(id) {
                selectedBillId = id;
                setMsg(paymentMsgEl, '');
                await loadBills();

                try {
                    const res = await api(`/api/bills/${id}`);
                    renderBillDetails(res?.data || null);
                } catch (e) {
                    selectedBillLabelEl.textContent = `#${id}`;
                    setBillDetailsVisible(true);
                    setMsg(paymentMsgEl, e.message || 'Failed to load bill.', 'error');
                }
            }

            function phpToCents(value) {
                const v = Number(value || 0);
                if (!Number.isFinite(v) || v <= 0) return 0;
                return Math.round(v * 100);
            }

            recordPaymentBtn.addEventListener('click', async () => {
                if (!selectedBillId) {
                    setMsg(paymentMsgEl, 'Select a bill first.', 'error');
                    return;
                }
                const amountCents = phpToCents(payAmountEl.value);
                if (amountCents <= 0) {
                    setMsg(paymentMsgEl, 'Enter a valid amount.', 'error');
                    return;
                }

                recordPaymentBtn.disabled = true;
                setMsg(paymentMsgEl, 'Recording payment...');
                try {
                    await api(`/api/bills/${selectedBillId}/payments`, {
                        method: 'POST',
                        body: JSON.stringify({
                            method: payMethodEl.value,
                            amount_cents: amountCents,
                        }),
                    });
                    setMsg(paymentMsgEl, 'Payment recorded.', 'success');
                    payAmountEl.value = '';
                    await selectBill(selectedBillId);
                    await loadSummary();
                } catch (e) {
                    setMsg(paymentMsgEl, e.message || 'Failed to record payment.', 'error');
                } finally {
                    recordPaymentBtn.disabled = false;
                }
            });

            function closeRefundModal() {
                refundModalEl.classList.add('hidden');
                refundModalEl.classList.remove('flex');
                selectedPaymentId = null;
            }

            closeRefundModalBtn.addEventListener('click', closeRefundModal);
            refundModalEl.addEventListener('click', (e) => {
                if (e.target === refundModalEl) closeRefundModal();
            });

            submitRefundBtn.addEventListener('click', async () => {
                if (!selectedBillId || !selectedPaymentId) {
                    setMsg(refundMsgEl, 'Missing payment selection.', 'error');
                    return;
                }
                const amountCents = phpToCents(refundAmountEl.value);
                const reason = String(refundReasonEl.value || '').trim();
                if (amountCents <= 0 || !reason) {
                    setMsg(refundMsgEl, 'Enter amount and reason.', 'error');
                    return;
                }
                submitRefundBtn.disabled = true;
                setMsg(refundMsgEl, 'Submitting refund...');
                try {
                    await api(`/api/payments/${selectedPaymentId}/refunds`, {
                        method: 'POST',
                        body: JSON.stringify({
                            amount_cents: amountCents,
                            reason,
                        }),
                    });
                    setMsg(refundMsgEl, 'Refund created.', 'success');
                    await selectBill(selectedBillId);
                    await loadSummary();
                    setTimeout(() => closeRefundModal(), 400);
                } catch (e) {
                    setMsg(refundMsgEl, e.message || 'Failed to refund.', 'error');
                } finally {
                    submitRefundBtn.disabled = false;
                }
            });

            refreshSummaryBtn.addEventListener('click', loadSummary);
            refreshBillsBtn.addEventListener('click', loadBills);
            billStatusEl.addEventListener('change', loadBills);
            overdueOnlyEl.addEventListener('change', loadBills);

            summaryDateEl.value = todayForDateInput();
            loadSummary();
            loadBills();
        </script>
    </body>
</html>
