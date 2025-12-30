<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Patient;
use App\Models\Procedure;
use App\Services\BillingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillController extends Controller
{
    use AuthorizesPermissions;

    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'billing.view');

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:50'],
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'overdue_only' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $limit = (int) ($validated['limit'] ?? 50);

        $query = Bill::query()->orderByDesc('created_at');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['patient_id'])) {
            $query->where('patient_id', (int) $validated['patient_id']);
        }
        if (! empty($validated['overdue_only'])) {
            $query->where('balance_cents', '>', 0)->whereNotNull('due_at')->where('due_at', '<=', now());
        }

        $bills = $query
            ->with(['patient', 'dentist', 'visit'])
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $bills,
        ]);
    }

    public function show(Request $request, Bill $bill): JsonResponse
    {
        $this->requirePermission($request, 'billing.view');

        $bill = $bill->load([
            'patient',
            'dentist',
            'visit',
            'items.procedure.teeth',
            'payments.receipt',
        ]);

        $refunds = DB::table('refunds')
            ->join('payments', 'refunds.payment_id', '=', 'payments.id')
            ->where('payments.bill_id', $bill->id)
            ->orderByDesc('refunds.refunded_at')
            ->get([
                'refunds.id',
                'refunds.payment_id',
                'refunds.amount_cents',
                'refunds.reason',
                'refunds.refunded_at',
                'refunds.refunded_by_user_id',
                'refunds.created_at',
            ]);

        return response()->json([
            'data' => [
                'bill' => $bill,
                'refunds' => $refunds,
            ],
        ]);
    }

    public function store(Request $request, Patient $patient, BillingService $billing): JsonResponse
    {
        $this->requirePermission($request, 'billing.create');

        $validated = $request->validate([
            'visit_id' => ['nullable', 'integer', 'exists:visits,id'],
            'dentist_id' => ['nullable', 'integer', 'exists:dentists,id'],
            'procedure_ids' => ['nullable', 'array', 'max:200'],
            'procedure_ids.*' => ['integer', 'exists:procedures,id'],
            'add_ons_cents' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'discount_cents' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'due_at' => ['nullable', 'date'],
            'lock' => ['nullable', 'boolean'],
            'item_overrides' => ['nullable', 'array', 'max:200'],
            'item_overrides.*.procedure_id' => ['required_with:item_overrides', 'integer', 'exists:procedures,id'],
            'item_overrides.*.override_total_cents' => ['required_with:item_overrides', 'integer', 'min:0', 'max:100000000'],
        ]);

        $visitId = $validated['visit_id'] ?? null;
        $dentistId = $validated['dentist_id'] ?? null;

        if ($visitId && ! DB::table('visits')->where('id', (int) $visitId)->where('patient_id', $patient->id)->exists()) {
            return response()->json([
                'message' => 'Visit does not belong to patient.',
            ], 422);
        }

        $overrideMap = collect($validated['item_overrides'] ?? [])
            ->mapWithKeys(fn (array $row) => [(int) $row['procedure_id'] => (int) $row['override_total_cents']])
            ->all();

        $procedureIds = collect($validated['procedure_ids'] ?? [])
            ->map(fn ($v) => (int) $v)
            ->values();

        $proceduresQuery = Procedure::query()
            ->where('patient_id', $patient->id)
            ->when($visitId, fn ($q) => $q->where('visit_id', (int) $visitId))
            ->when($procedureIds->isNotEmpty(), fn ($q) => $q->whereIn('id', $procedureIds->all()))
            ->with(['teeth'])
            ->orderBy('performed_at');

        $procedures = $proceduresQuery->get();

        if ($procedures->isEmpty()) {
            return response()->json([
                'message' => 'No procedures found for billing.',
            ], 422);
        }

        $alreadyBilled = DB::table('bill_items')
            ->whereIn('procedure_id', $procedures->pluck('id')->values())
            ->pluck('procedure_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        if (! empty($alreadyBilled)) {
            return response()->json([
                'message' => 'One or more procedures are already billed.',
                'data' => [
                    'procedure_ids' => $alreadyBilled,
                ],
            ], 409);
        }

        $lock = (bool) ($validated['lock'] ?? true);
        $addOnsCents = (int) ($validated['add_ons_cents'] ?? 0);
        $discountCents = (int) ($validated['discount_cents'] ?? 0);
        $dueAt = isset($validated['due_at']) ? CarbonImmutable::parse($validated['due_at']) : null;

        $bill = DB::transaction(function () use ($request, $patient, $visitId, $dentistId, $procedures, $overrideMap, $lock, $addOnsCents, $discountCents, $dueAt, $billing) {
            $bill = Bill::create([
                'patient_id' => $patient->id,
                'visit_id' => $visitId,
                'dentist_id' => $dentistId,
                'status' => 'unpaid',
                'currency' => 'PHP',
                'subtotal_cents' => 0,
                'add_ons_cents' => $addOnsCents,
                'discount_cents' => $discountCents,
                'total_cents' => 0,
                'paid_cents' => 0,
                'balance_cents' => 0,
                'locked_at' => $lock ? now() : null,
                'locked_by_user_id' => $lock ? $request->user()?->id : null,
                'due_at' => $dueAt,
                'meta' => null,
            ]);

            $items = [];
            foreach ($procedures as $p) {
                $toothCount = max(1, (int) $p->teeth->count());
                $price = $billing->resolvePrice((string) $p->procedure_type, $p->dentist_id ?: $dentistId);

                $baseTotal = 0;
                if ($price) {
                    $baseTotal = $billing->computeBaseTotalCents($price, $toothCount);
                } elseif ($p->cost_cents !== null) {
                    $baseTotal = max(0, (int) $p->cost_cents);
                }

                $overrideTotal = array_key_exists((int) $p->id, $overrideMap) ? (int) $overrideMap[(int) $p->id] : null;
                $total = $billing->computeItemTotalCents($baseTotal, 0, 0, $overrideTotal);

                $items[] = [
                    'bill_id' => $bill->id,
                    'procedure_id' => $p->id,
                    'procedure_type' => $p->procedure_type,
                    'description' => $p->description,
                    'tooth_count' => $toothCount,
                    'base_price_cents' => $baseTotal,
                    'add_ons_cents' => 0,
                    'discount_cents' => 0,
                    'override_price_cents' => $overrideTotal,
                    'total_cents' => $total,
                    'meta' => $price ? json_encode([
                        'price_id' => $price->id,
                        'dentist_id' => $price->dentist_id,
                        'per_tooth_cents' => $price->per_tooth_cents,
                    ]) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            BillItem::insert($items);

            $bill = $billing->recomputeBillTotals($bill);

            ActivityLog::create([
                'actor_user_id' => $request->user()?->id,
                'patient_id' => $patient->id,
                'action' => 'bill.created',
                'subject_type' => Bill::class,
                'subject_id' => $bill->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'meta' => [
                    'visit_id' => $visitId,
                    'procedure_ids' => $procedures->pluck('id')->values()->all(),
                    'locked' => $lock,
                ],
                'created_at' => now(),
            ]);

            return $bill;
        });

        return response()->json([
            'data' => $bill->load(['items', 'patient', 'dentist', 'visit']),
        ], 201);
    }

    public function lock(Request $request, Bill $bill, BillingService $billing): JsonResponse
    {
        $this->requirePermission($request, 'billing.lock');

        if ($bill->locked_at) {
            return response()->json([
                'message' => 'Bill is already locked.',
            ], 409);
        }

        $bill = DB::transaction(function () use ($request, $bill, $billing) {
            $locked = Bill::query()->whereKey($bill->id)->lockForUpdate()->firstOrFail();

            if ($locked->locked_at) {
                throw new \RuntimeException('Bill already locked.');
            }

            $locked->update([
                'locked_at' => now(),
                'locked_by_user_id' => $request->user()?->id,
            ]);

            $locked = $billing->recomputeBillTotals($locked);

            ActivityLog::create([
                'actor_user_id' => $request->user()?->id,
                'patient_id' => $locked->patient_id,
                'action' => 'bill.locked',
                'subject_type' => Bill::class,
                'subject_id' => $locked->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'meta' => null,
                'created_at' => now(),
            ]);

            return $locked;
        });

        return response()->json([
            'data' => $bill->fresh(),
        ]);
    }
}
