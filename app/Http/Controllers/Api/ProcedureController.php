<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Allergy;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\ProcedureTooth;
use App\Models\Visit;
use App\Services\BillingService;
use App\Services\InventoryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProcedureController extends Controller
{
    use AuthorizesPermissions;

    public function index(Request $request, Patient $patient): JsonResponse
    {
        $this->requirePermission($request, 'clinical.view');

        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $limit = (int) ($validated['limit'] ?? 50);

        $procedures = Procedure::query()
            ->where('patient_id', $patient->id)
            ->orderByDesc('performed_at')
            ->with(['dentist', 'teeth'])
            ->limit($limit)
            ->get()
            ->map(function (Procedure $p) {
                $meta = $p->meta ?? [];
                if (! isset($meta['follow_up_suggested_at'])) {
                    $suggested = $this->followUpSuggestedAt($p->procedure_type, CarbonImmutable::parse($p->performed_at));
                    if ($suggested) {
                        $meta['follow_up_suggested_at'] = $suggested;
                    }
                }

                return array_merge($p->toArray(), [
                    'tooth_codes' => $p->teeth->pluck('tooth_code')->values(),
                    'meta' => empty($meta) ? null : $meta,
                ]);
            })
            ->values();

        ActivityLog::create([
            'actor_user_id' => $request->user()?->id,
            'patient_id' => $patient->id,
            'action' => 'procedure.viewed',
            'subject_type' => Patient::class,
            'subject_id' => $patient->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => ['count' => $procedures->count()],
            'created_at' => now(),
        ]);

        return response()->json([
            'data' => $procedures,
        ]);
    }

    public function followUps(Request $request, Patient $patient): JsonResponse
    {
        $this->requirePermission($request, 'clinical.view');

        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            'only_due' => ['nullable', 'boolean'],
        ]);

        $limit = (int) ($validated['limit'] ?? 50);
        $onlyDue = (bool) ($validated['only_due'] ?? false);
        $today = CarbonImmutable::now()->startOfDay();

        $procedures = Procedure::query()
            ->where('patient_id', $patient->id)
            ->whereNotNull('meta->follow_up_suggested_at')
            ->orderByDesc('performed_at')
            ->limit($limit)
            ->get()
            ->map(function (Procedure $p) use ($today) {
                $meta = $p->meta ?? [];
                $followUpAt = isset($meta['follow_up_suggested_at']) ? (string) $meta['follow_up_suggested_at'] : null;
                $followUpDate = $followUpAt ? CarbonImmutable::parse($followUpAt)->startOfDay() : null;
                $isDue = $followUpDate ? $followUpDate->lessThanOrEqualTo($today) : false;

                return [
                    'id' => $p->id,
                    'procedure_type' => $p->procedure_type,
                    'performed_at' => $p->performed_at,
                    'follow_up_suggested_at' => $followUpAt,
                    'is_due' => $isDue,
                ];
            })
            ->when($onlyDue, fn ($c) => $c->filter(fn ($x) => $x['is_due'])->values(), fn ($c) => $c->values());

        ActivityLog::create([
            'actor_user_id' => $request->user()?->id,
            'patient_id' => $patient->id,
            'action' => 'procedure.followups_viewed',
            'subject_type' => Patient::class,
            'subject_id' => $patient->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => ['count' => $procedures->count(), 'only_due' => $onlyDue],
            'created_at' => now(),
        ]);

        return response()->json([
            'data' => $procedures,
        ]);
    }

    public function dueFollowUps(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'clinical.view');

        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'only_due' => ['nullable', 'boolean'],
        ]);

        $limit = (int) ($validated['limit'] ?? 50);
        $today = CarbonImmutable::now()->startOfDay();
        $onlyDue = ! array_key_exists('only_due', $validated) || (bool) $validated['only_due'];
        $from = isset($validated['from']) ? CarbonImmutable::parse($validated['from'])->startOfDay() : null;
        $to = isset($validated['to']) ? CarbonImmutable::parse($validated['to'])->endOfDay() : null;

        $base = Procedure::query()
            ->whereNotNull('meta->follow_up_suggested_at')
            ->when($onlyDue, fn ($q) => $q->where('meta->follow_up_suggested_at', '<=', $today->toDateString()))
            ->when($from, fn ($q) => $q->where('meta->follow_up_suggested_at', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->where('meta->follow_up_suggested_at', '<=', $to->toDateString()));

        $total = (clone $base)->count();

        $items = (clone $base)
            ->orderByDesc('performed_at')
            ->with(['patient', 'dentist'])
            ->limit($limit)
            ->get()
            ->map(function (Procedure $p) use ($today) {
                $meta = $p->meta ?? [];
                $followUpAt = isset($meta['follow_up_suggested_at']) ? (string) $meta['follow_up_suggested_at'] : null;
                $followUpDate = $followUpAt ? CarbonImmutable::parse($followUpAt)->startOfDay() : null;
                $isDue = $followUpDate ? $followUpDate->lessThanOrEqualTo($today) : false;

                return [
                    'procedure_id' => $p->id,
                    'patient_id' => $p->patient_id,
                    'patient_name' => $p->patient?->full_name,
                    'dentist_id' => $p->dentist_id,
                    'dentist_name' => $p->dentist?->name,
                    'procedure_type' => $p->procedure_type,
                    'performed_at' => $p->performed_at,
                    'follow_up_suggested_at' => $followUpAt,
                    'is_due' => $isDue,
                ];
            })
            ->values();

        ActivityLog::create([
            'actor_user_id' => $request->user()?->id,
            'patient_id' => null,
            'action' => 'procedure.followups_due_viewed',
            'subject_type' => Procedure::class,
            'subject_id' => null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => [
                'total' => $total,
                'count' => $items->count(),
                'only_due' => $onlyDue,
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
            ],
            'created_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'total' => $total,
                'items' => $items,
            ],
        ]);
    }

    public function store(Request $request, Patient $patient, InventoryService $inventory, BillingService $billing): JsonResponse
    {
        $this->requirePermission($request, 'clinical.edit');

        $validated = $request->validate([
            'visit_id' => ['nullable', 'integer', 'exists:visits,id'],
            'dentist_id' => ['nullable', 'integer', 'exists:dentists,id'],
            'procedure_type' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'cost_cents' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'performed_at' => ['required', 'date'],
            'requires_allergy_tags' => ['nullable', 'array', 'max:25'],
            'requires_allergy_tags.*' => ['string', 'max:50'],
            'confirm_allergy_conflicts' => ['nullable', 'boolean'],
            'tooth_codes' => ['nullable', 'array', 'max:32'],
            'tooth_codes.*' => ['string', 'max:10'],
            'surfaces' => ['nullable', 'array', 'max:5'],
            'surfaces.*' => ['string', 'in:M,D,B,L,O'],
            'meta' => ['nullable', 'array'],
        ]);

        if (isset($validated['visit_id']) && ! Visit::query()->where('id', (int) $validated['visit_id'])->where('patient_id', $patient->id)->exists()) {
            return response()->json([
                'message' => 'Visit does not belong to patient.',
            ], 422);
        }

        $requiresTags = collect($validated['requires_allergy_tags'] ?? $this->defaultAllergyRequirements($validated['procedure_type']))
            ->filter(fn ($v) => is_string($v) && trim($v) !== '')
            ->map(fn ($v) => strtolower(trim($v)))
            ->values()
            ->all();

        $activeAllergyTags = Allergy::query()
            ->where('patient_id', $patient->id)
            ->where('is_active', true)
            ->pluck('tag')
            ->map(fn ($v) => strtolower(trim((string) $v)))
            ->values();

        $conflicts = collect($requiresTags)->intersect($activeAllergyTags)->values()->all();
        $confirm = (bool) ($validated['confirm_allergy_conflicts'] ?? false);

        if ($conflicts && ! $confirm) {
            return response()->json([
                'message' => 'Allergy conflict requires dentist confirmation.',
                'data' => [
                    'patient_id' => $patient->id,
                    'conflicts' => $conflicts,
                ],
            ], 409);
        }

        $performedAt = CarbonImmutable::parse($validated['performed_at']);

        $procedure = DB::transaction(function () use ($request, $patient, $validated, $requiresTags, $conflicts, $confirm, $performedAt, $inventory, $billing) {
            $meta = is_array($validated['meta'] ?? null) ? $validated['meta'] : [];
            if (! isset($meta['follow_up_suggested_at'])) {
                $suggested = $this->followUpSuggestedAt($validated['procedure_type'], $performedAt);
                if ($suggested) {
                    $meta['follow_up_suggested_at'] = $suggested;
                }
            }

            $procedure = Procedure::create([
                'patient_id' => $patient->id,
                'visit_id' => $validated['visit_id'] ?? null,
                'dentist_id' => $validated['dentist_id'] ?? null,
                'procedure_type' => strtolower(trim($validated['procedure_type'])),
                'description' => $validated['description'] ?? null,
                'cost_cents' => $validated['cost_cents'] ?? null,
                'performed_at' => $performedAt,
                'requires_allergy_tags' => empty($requiresTags) ? null : $requiresTags,
                'allergy_conflicts' => empty($conflicts) ? null : $conflicts,
                'confirmed_by_user_id' => ($conflicts && $confirm) ? $request->user()?->id : null,
                'confirmed_at' => ($conflicts && $confirm) ? now() : null,
                'created_by_user_id' => $request->user()?->id,
                'meta' => empty($meta) ? null : $meta,
            ]);

            $toothCodes = collect($validated['tooth_codes'] ?? [])
                ->filter(fn ($v) => is_string($v) && trim($v) !== '')
                ->map(fn ($v) => trim($v))
                ->unique()
                ->values();

            $surfaces = collect($validated['surfaces'] ?? [])
                ->filter(fn ($v) => is_string($v))
                ->unique()
                ->values()
                ->all();

            if ($toothCodes->isNotEmpty()) {
                $rows = $toothCodes->map(function (string $code) use ($procedure, $surfaces) {
                    return [
                        'procedure_id' => $procedure->id,
                        'tooth_code' => $code,
                        'surfaces' => empty($surfaces) ? null : $surfaces,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                })->all();

                ProcedureTooth::insert($rows);
            }

            $inventory->deductForProcedure(
                $procedure,
                $toothCodes->count(),
                $request->user()?->id,
                $request->ip(),
                $request->userAgent()
            );

            ActivityLog::create([
                'actor_user_id' => $request->user()?->id,
                'patient_id' => $patient->id,
                'action' => 'procedure.created',
                'subject_type' => Procedure::class,
                'subject_id' => $procedure->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'meta' => [
                    'actor_role' => $request->user()?->role,
                    'procedure_type' => $procedure->procedure_type,
                    'tooth_codes' => $toothCodes->values()->all(),
                    'conflicts' => $conflicts,
                ],
                'created_at' => now(),
            ]);

            if (! BillItem::query()->where('procedure_id', $procedure->id)->exists()) {
                $bill = null;
                if ($procedure->visit_id) {
                    $bill = Bill::query()
                        ->where('visit_id', $procedure->visit_id)
                        ->lockForUpdate()
                        ->orderByDesc('id')
                        ->first();
                }

                if (! $bill) {
                    $bill = Bill::create([
                        'patient_id' => $patient->id,
                        'visit_id' => $procedure->visit_id,
                        'dentist_id' => $procedure->dentist_id,
                        'status' => 'unpaid',
                        'currency' => 'PHP',
                        'subtotal_cents' => 0,
                        'add_ons_cents' => 0,
                        'discount_cents' => 0,
                        'total_cents' => 0,
                        'paid_cents' => 0,
                        'balance_cents' => 0,
                        'locked_at' => null,
                        'locked_by_user_id' => null,
                        'due_at' => null,
                        'meta' => [
                            'source' => 'procedure_auto',
                            'procedure_id' => $procedure->id,
                        ],
                    ]);
                }

                $toothCount = max(1, (int) $toothCodes->count());
                $price = $billing->resolvePrice((string) $procedure->procedure_type, $procedure->dentist_id);

                $baseTotal = 0;
                if ($price) {
                    $baseTotal = $billing->computeBaseTotalCents($price, $toothCount);
                } elseif ($procedure->cost_cents !== null) {
                    $baseTotal = max(0, (int) $procedure->cost_cents);
                }

                $total = $billing->computeItemTotalCents($baseTotal, 0, 0, null);

                BillItem::create([
                    'bill_id' => $bill->id,
                    'procedure_id' => $procedure->id,
                    'procedure_type' => $procedure->procedure_type,
                    'description' => $procedure->description,
                    'tooth_count' => $toothCount,
                    'base_price_cents' => $baseTotal,
                    'add_ons_cents' => 0,
                    'discount_cents' => 0,
                    'override_price_cents' => null,
                    'total_cents' => $total,
                    'meta' => $price ? [
                        'price_id' => $price->id,
                        'dentist_id' => $price->dentist_id,
                        'per_tooth_cents' => $price->per_tooth_cents,
                    ] : null,
                ]);

                $billing->recomputeBillTotals($bill);
            }

            return $procedure;
        });

        return response()->json([
            'data' => $procedure->load(['dentist', 'teeth']),
        ], 201);
    }

    private function defaultAllergyRequirements(string $procedureType): array
    {
        $t = strtolower(trim($procedureType));

        if (in_array($t, ['extraction', 'root canal'], true)) {
            return ['anesthesia', 'latex'];
        }

        if (in_array($t, ['filling', 'cleaning'], true)) {
            return ['latex'];
        }

        return [];
    }

    private function followUpSuggestedAt(string $procedureType, CarbonImmutable $performedAt): ?string
    {
        $t = strtolower(trim($procedureType));

        if ($t === 'cleaning') {
            return $performedAt->addMonthsNoOverflow(6)->toDateString();
        }

        if ($t === 'extraction') {
            return $performedAt->addDays(7)->toDateString();
        }

        if ($t === 'braces') {
            return $performedAt->addMonthNoOverflow()->toDateString();
        }

        return null;
    }
}
