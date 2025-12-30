<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Dentist;
use App\Models\Patient;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    use AuthorizesPermissions;

    public function summary(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'analytics.view_widgets');

        $filters = $this->validatedFilters($request);

        $data = $this->cached($request, 'summary', $filters, function () use ($filters) {
            $totalPatients = (int) Patient::query()->count();

            $appointmentsThisRange = Appointment::query()
                ->where('start_at', '>=', $filters['from'])
                ->where('start_at', '<=', $filters['to'])
                ->whereIn('status', ['booked', 'checked_in', 'in_treatment', 'completed'])
                ->when($filters['dentist_id'], fn ($q) => $q->where('dentist_id', $filters['dentist_id']))
                ->count();

            [$paymentsCents, $refundsCents] = $this->revenueAndRefundsCents(
                from: $filters['from'],
                to: $filters['to'],
                dentistId: $filters['dentist_id'],
                billStatus: $filters['bill_status'],
            );

            $retention = $this->retentionBreakdown(
                from: $filters['from'],
                to: $filters['to'],
                dentistId: $filters['dentist_id'],
            );

            $totalInRange = (int) $retention['total'];
            $returningRate = $totalInRange > 0 ? round(((int) $retention['returning'] / $totalInRange) * 100, 1) : 0.0;

            return [
                'total_patients' => $totalPatients,
                'appointments_in_range' => (int) $appointmentsThisRange,
                'revenue_cents' => (int) ($paymentsCents - $refundsCents),
                'returning_patient_rate' => $returningRate,
                'returning_patient_breakdown' => $retention,
                'range' => [
                    'from' => $filters['from']->toDateString(),
                    'to' => $filters['to']->toDateString(),
                ],
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function topProcedures(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'analytics.view_widgets');

        $filters = $this->validatedFilters($request);

        $data = $this->cached($request, 'top_procedures', $filters, function () use ($filters) {
            $base = DB::table('procedures')
                ->where('performed_at', '>=', $filters['from'])
                ->where('performed_at', '<=', $filters['to'])
                ->when($filters['dentist_id'], fn ($q) => $q->where('dentist_id', $filters['dentist_id']))
                ->when($filters['procedure_type'], fn ($q) => $q->where('procedure_type', $filters['procedure_type']));

            $total = (int) (clone $base)->count();

            $rows = (clone $base)
                ->selectRaw('procedure_type, COUNT(*) as count')
                ->groupBy('procedure_type')
                ->orderByDesc('count')
                ->limit(5)
                ->get();

            $top = $rows->map(function ($r) use ($total) {
                $count = (int) $r->count;

                return [
                    'procedure_type' => (string) $r->procedure_type,
                    'count' => $count,
                    'share_percent' => $total > 0 ? round(($count / $total) * 100, 1) : 0.0,
                ];
            })->values();

            return [
                'total' => $total,
                'top' => $top,
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function peakDays(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'analytics.view_widgets');

        $filters = $this->validatedFilters($request);

        $data = $this->cached($request, 'peak_days', $filters, function () use ($filters) {
            $driver = DB::getDriverName();
            $dowExpr = $driver === 'mysql'
                ? 'DAYOFWEEK(start_at)'
                : "CAST(strftime('%w', start_at) AS INTEGER)";

            $rows = DB::table('appointments')
                ->where('start_at', '>=', $filters['from'])
                ->where('start_at', '<=', $filters['to'])
                ->whereIn('status', ['booked', 'checked_in', 'in_treatment', 'completed'])
                ->when($filters['dentist_id'], fn ($q) => $q->where('dentist_id', $filters['dentist_id']))
                ->selectRaw($dowExpr.' as dow, COUNT(*) as count')
                ->groupBy('dow')
                ->get();

            $byDow = [];
            foreach ($rows as $r) {
                $raw = (int) $r->dow;
                $count = (int) $r->count;

                $normalized = $driver === 'mysql' ? (($raw + 5) % 7) + 1 : (($raw + 6) % 7) + 1;
                $byDow[$normalized] = $count;
            }

            $days = collect(range(1, 7))->map(fn (int $dow) => [
                'day_of_week' => $dow,
                'count' => (int) ($byDow[$dow] ?? 0),
            ])->values();

            $max = (int) $days->max('count');

            return [
                'days' => $days,
                'max' => $max,
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function revenueMonthly(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'analytics.view_widgets');

        $validated = $request->validate([
            'to' => ['nullable', 'date_format:Y-m-d'],
            'months' => ['nullable', 'integer', 'min:1', 'max:24'],
            'dentist_id' => ['nullable', 'integer', 'exists:dentists,id'],
            'bill_status' => ['nullable', 'string', 'max:50'],
        ]);

        $to = isset($validated['to']) ? CarbonImmutable::parse($validated['to'])->endOfDay() : CarbonImmutable::now()->endOfDay();
        $months = (int) ($validated['months'] ?? 12);

        $dentistId = $this->effectiveDentistId($request, $validated['dentist_id'] ?? null);
        $billStatus = isset($validated['bill_status']) ? strtolower(trim((string) $validated['bill_status'])) : null;

        $from = $to->startOfMonth()->subMonthsNoOverflow($months - 1)->startOfMonth();

        $filters = [
            'from' => $from,
            'to' => $to,
            'dentist_id' => $dentistId,
            'bill_status' => $billStatus,
            'months' => $months,
        ];

        $data = $this->cached($request, 'revenue_monthly', $filters, function () use ($filters) {
            $driver = DB::getDriverName();
            $monthExpr = $driver === 'mysql'
                ? "DATE_FORMAT(paid_at, '%Y-%m')"
                : "strftime('%Y-%m', paid_at)";

            $payments = DB::table('payments')
                ->join('bills', 'payments.bill_id', '=', 'bills.id')
                ->where('payments.paid_at', '>=', $filters['from'])
                ->where('payments.paid_at', '<=', $filters['to'])
                ->when($filters['dentist_id'], fn ($q) => $q->where('bills.dentist_id', $filters['dentist_id']))
                ->when($filters['bill_status'], fn ($q) => $q->where('bills.status', $filters['bill_status']))
                ->selectRaw($monthExpr.' as ym, SUM(payments.amount_cents) as total_cents')
                ->groupBy('ym')
                ->orderBy('ym')
                ->get();

            $refunds = DB::table('refunds')
                ->join('payments', 'refunds.payment_id', '=', 'payments.id')
                ->join('bills', 'payments.bill_id', '=', 'bills.id')
                ->where('refunds.refunded_at', '>=', $filters['from'])
                ->where('refunds.refunded_at', '<=', $filters['to'])
                ->when($filters['dentist_id'], fn ($q) => $q->where('bills.dentist_id', $filters['dentist_id']))
                ->when($filters['bill_status'], fn ($q) => $q->where('bills.status', $filters['bill_status']))
                ->selectRaw(($driver === 'mysql' ? "DATE_FORMAT(refunds.refunded_at, '%Y-%m')" : "strftime('%Y-%m', refunds.refunded_at)").' as ym, SUM(refunds.amount_cents) as total_cents')
                ->groupBy('ym')
                ->orderBy('ym')
                ->get();

            $byMonth = [];
            foreach ($payments as $p) {
                $byMonth[(string) $p->ym] = (int) $p->total_cents;
            }
            foreach ($refunds as $r) {
                $ym = (string) $r->ym;
                $byMonth[$ym] = (int) ($byMonth[$ym] ?? 0) - (int) $r->total_cents;
            }

            $months = [];
            $cursor = $filters['from']->startOfMonth();
            $end = $filters['to']->startOfMonth();
            while ($cursor->lessThanOrEqualTo($end)) {
                $ym = $cursor->format('Y-m');
                $months[] = [
                    'month' => $ym,
                    'total_cents' => (int) ($byMonth[$ym] ?? 0),
                ];
                $cursor = $cursor->addMonthNoOverflow();
            }

            $max = max(array_map(fn (array $m) => (int) $m['total_cents'], $months) ?: [0]);

            return [
                'from' => $filters['from']->toDateString(),
                'to' => $filters['to']->toDateString(),
                'months' => $months,
                'max_cents' => $max,
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function retention(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'analytics.view_widgets');

        $filters = $this->validatedFilters($request);

        $data = $this->cached($request, 'retention', $filters, function () use ($filters) {
            return $this->retentionBreakdown(
                from: $filters['from'],
                to: $filters['to'],
                dentistId: $filters['dentist_id'],
            );
        });

        return response()->json(['data' => $data]);
    }

    public function procedurePatients(Request $request, string $procedureType): JsonResponse
    {
        $this->requirePermission($request, 'analytics.view_drilldowns');

        $filters = $this->validatedFilters($request);
        $procedureType = strtolower(trim($procedureType));

        $data = $this->cached($request, 'procedure_patients_'.$procedureType, $filters, function () use ($filters, $procedureType) {
            $rows = DB::table('procedures')
                ->join('patients', 'procedures.patient_id', '=', 'patients.id')
                ->where('procedures.performed_at', '>=', $filters['from'])
                ->where('procedures.performed_at', '<=', $filters['to'])
                ->where('procedures.procedure_type', $procedureType)
                ->when($filters['dentist_id'], fn ($q) => $q->where('procedures.dentist_id', $filters['dentist_id']))
                ->selectRaw('patients.id as patient_id, patients.full_name as patient_name, COUNT(*) as count, MAX(procedures.performed_at) as last_performed_at')
                ->groupBy('patients.id', 'patients.full_name')
                ->orderByDesc('count')
                ->limit(100)
                ->get();

            return [
                'procedure_type' => $procedureType,
                'patients' => $rows,
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function peakDayAppointments(Request $request, int $dayOfWeek): JsonResponse
    {
        $this->requirePermission($request, 'analytics.view_drilldowns');

        if ($dayOfWeek < 1 || $dayOfWeek > 7) {
            abort(422, 'Invalid day_of_week.');
        }

        $filters = $this->validatedFilters($request);

        $data = $this->cached($request, 'peak_day_'.$dayOfWeek, $filters, function () use ($filters, $dayOfWeek) {
            $driver = DB::getDriverName();
            $dowExpr = $driver === 'mysql'
                ? 'DAYOFWEEK(start_at)'
                : "CAST(strftime('%w', start_at) AS INTEGER)";

            $targetRaw = $driver === 'mysql' ? (($dayOfWeek % 7) + 1) : ($dayOfWeek % 7);

            $rows = DB::table('appointments')
                ->leftJoin('dentists', 'appointments.dentist_id', '=', 'dentists.id')
                ->leftJoin('services', 'appointments.service_id', '=', 'services.id')
                ->where('appointments.start_at', '>=', $filters['from'])
                ->where('appointments.start_at', '<=', $filters['to'])
                ->whereIn('appointments.status', ['booked', 'checked_in', 'in_treatment', 'completed'])
                ->whereRaw($dowExpr.' = ?', [$targetRaw])
                ->when($filters['dentist_id'], fn ($q) => $q->where('appointments.dentist_id', $filters['dentist_id']))
                ->orderBy('appointments.start_at')
                ->limit(200)
                ->get([
                    'appointments.id',
                    'appointments.start_at',
                    'appointments.end_at',
                    'appointments.status',
                    'appointments.booking_reference_code',
                    'appointments.patient_name',
                    'dentists.name as dentist_name',
                    'services.name as service_name',
                ]);

            return [
                'day_of_week' => $dayOfWeek,
                'appointments' => $rows,
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function revenueReceipts(Request $request, string $month): JsonResponse
    {
        $this->requirePermission($request, 'analytics.view_drilldowns');

        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            abort(422, 'Invalid month.');
        }

        $validated = $request->validate([
            'dentist_id' => ['nullable', 'integer', 'exists:dentists,id'],
            'bill_status' => ['nullable', 'string', 'max:50'],
        ]);

        $dentistId = $this->effectiveDentistId($request, $validated['dentist_id'] ?? null);
        $billStatus = isset($validated['bill_status']) ? strtolower(trim((string) $validated['bill_status'])) : null;

        $start = CarbonImmutable::parse($month.'-01')->startOfMonth();
        $end = $start->endOfMonth();

        $filters = [
            'month' => $month,
            'from' => $start,
            'to' => $end,
            'dentist_id' => $dentistId,
            'bill_status' => $billStatus,
        ];

        $data = $this->cached($request, 'revenue_receipts_'.$month, $filters, function () use ($filters) {
            $rows = DB::table('payments')
                ->join('bills', 'payments.bill_id', '=', 'bills.id')
                ->join('patients', 'bills.patient_id', '=', 'patients.id')
                ->leftJoin('receipts', 'receipts.payment_id', '=', 'payments.id')
                ->where('payments.paid_at', '>=', $filters['from'])
                ->where('payments.paid_at', '<=', $filters['to'])
                ->when($filters['dentist_id'], fn ($q) => $q->where('bills.dentist_id', $filters['dentist_id']))
                ->when($filters['bill_status'], fn ($q) => $q->where('bills.status', $filters['bill_status']))
                ->orderByDesc('payments.paid_at')
                ->limit(200)
                ->get([
                    'payments.id as payment_id',
                    'payments.method',
                    'payments.amount_cents',
                    'payments.paid_at',
                    'patients.id as patient_id',
                    'patients.full_name as patient_name',
                    'bills.id as bill_id',
                    'bills.status as bill_status',
                    'receipts.receipt_number',
                ]);

            $total = (int) $rows->sum('amount_cents');

            return [
                'month' => $filters['month'],
                'total_cents' => $total,
                'receipts' => $rows,
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function procedureTypes(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'analytics.view_widgets');

        $filters = $this->validatedFilters($request);

        $data = $this->cached($request, 'procedure_types', $filters, function () use ($filters) {
            $rows = DB::table('procedures')
                ->where('performed_at', '>=', $filters['from'])
                ->where('performed_at', '<=', $filters['to'])
                ->when($filters['dentist_id'], fn ($q) => $q->where('dentist_id', $filters['dentist_id']))
                ->when($filters['procedure_type'], fn ($q) => $q->where('procedure_type', $filters['procedure_type']))
                ->selectRaw('procedure_type, COUNT(*) as count')
                ->groupBy('procedure_type')
                ->orderByDesc('count')
                ->limit(100)
                ->get();

            return $rows;
        });

        return response()->json(['data' => $data]);
    }

    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'dentist_id' => ['nullable', 'integer', 'exists:dentists,id'],
            'procedure_type' => ['nullable', 'string', 'max:100'],
            'bill_status' => ['nullable', 'string', 'max:50'],
        ]);

        $from = isset($validated['from']) ? CarbonImmutable::parse($validated['from'])->startOfDay() : CarbonImmutable::now()->startOfMonth()->startOfDay();
        $to = isset($validated['to']) ? CarbonImmutable::parse($validated['to'])->endOfDay() : CarbonImmutable::now()->endOfMonth()->endOfDay();

        $dentistId = $this->effectiveDentistId($request, $validated['dentist_id'] ?? null);
        $procedureType = isset($validated['procedure_type']) ? strtolower(trim((string) $validated['procedure_type'])) : null;
        $billStatus = isset($validated['bill_status']) ? strtolower(trim((string) $validated['bill_status'])) : null;

        return [
            'from' => $from,
            'to' => $to,
            'dentist_id' => $dentistId,
            'procedure_type' => $procedureType,
            'bill_status' => $billStatus,
        ];
    }

    private function effectiveDentistId(Request $request, ?int $requestedDentistId): ?int
    {
        $user = $request->user();
        if ($user && $user->hasPermission('analytics.scope_self')) {
            $byEmail = Dentist::query()->where('email', $user->email)->value('id');
            if ($byEmail) {
                return (int) $byEmail;
            }

            $byName = Dentist::query()->where('name', $user->name)->value('id');
            if ($byName) {
                return (int) $byName;
            }

            abort(403);
        }

        return $requestedDentistId ? (int) $requestedDentistId : null;
    }

    private function retentionBreakdown(CarbonImmutable $from, CarbonImmutable $to, ?int $dentistId): array
    {
        $driver = DB::getDriverName();
        $cast = $driver === 'mysql' ? 'CHAR' : 'TEXT';
        $identityExpr = "COALESCE(CAST(patient_id AS {$cast}), NULLIF(TRIM(LOWER(patient_email)), ''), NULLIF(TRIM(patient_phone), ''), NULLIF(TRIM(LOWER(patient_name)), ''))";

        $baseInRange = DB::table('appointments')
            ->where('start_at', '>=', $from)
            ->where('start_at', '<=', $to)
            ->whereIn('status', ['booked', 'checked_in', 'in_treatment', 'completed'])
            ->when($dentistId, fn ($q) => $q->where('dentist_id', $dentistId));

        $identities = (clone $baseInRange)
            ->selectRaw($identityExpr.' as ident')
            ->distinct()
            ->pluck('ident')
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->values()
            ->all();

        if (empty($identities)) {
            return [
                'new' => 0,
                'returning' => 0,
                'total' => 0,
            ];
        }

        $returning = DB::table('appointments')
            ->where('start_at', '<', $from)
            ->whereIn('status', ['booked', 'checked_in', 'in_treatment', 'completed'])
            ->when($dentistId, fn ($q) => $q->where('dentist_id', $dentistId))
            ->whereIn(DB::raw($identityExpr), $identities)
            ->selectRaw(DB::raw($identityExpr).' as ident')
            ->distinct()
            ->count();

        $total = count($identities);
        $returning = (int) $returning;
        $new = max(0, $total - $returning);

        return [
            'new' => $new,
            'returning' => $returning,
            'total' => $total,
        ];
    }

    private function revenueAndRefundsCents(CarbonImmutable $from, CarbonImmutable $to, ?int $dentistId, ?string $billStatus): array
    {
        $payments = DB::table('payments')
            ->join('bills', 'payments.bill_id', '=', 'bills.id')
            ->where('payments.paid_at', '>=', $from)
            ->where('payments.paid_at', '<=', $to)
            ->when($dentistId, fn ($q) => $q->where('bills.dentist_id', $dentistId))
            ->when($billStatus, fn ($q) => $q->where('bills.status', $billStatus))
            ->sum('payments.amount_cents');

        $refunds = DB::table('refunds')
            ->join('payments', 'refunds.payment_id', '=', 'payments.id')
            ->join('bills', 'payments.bill_id', '=', 'bills.id')
            ->where('refunds.refunded_at', '>=', $from)
            ->where('refunds.refunded_at', '<=', $to)
            ->when($dentistId, fn ($q) => $q->where('bills.dentist_id', $dentistId))
            ->when($billStatus, fn ($q) => $q->where('bills.status', $billStatus))
            ->sum('refunds.amount_cents');

        return [(int) $payments, (int) $refunds];
    }

    private function cached(Request $request, string $name, array $filters, \Closure $fn): mixed
    {
        $user = $request->user();
        $key = 'analytics:'.$name.':'.md5(json_encode([
            'role' => $user?->role,
            'user_id' => $user?->id,
            'filters' => array_map(function ($v) {
                if ($v instanceof CarbonImmutable) {
                    return $v->toIso8601String();
                }

                return $v;
            }, $filters),
        ]));

        return Cache::remember($key, now()->addMinutes(5), $fn);
    }
}
