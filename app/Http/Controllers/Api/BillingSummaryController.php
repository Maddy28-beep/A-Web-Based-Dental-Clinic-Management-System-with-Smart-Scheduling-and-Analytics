<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Controller;
use App\Models\Bill;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillingSummaryController extends Controller
{
    use AuthorizesPermissions;

    public function show(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'billing.view');

        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $day = isset($validated['date']) ? CarbonImmutable::parse($validated['date'])->startOfDay() : CarbonImmutable::now()->startOfDay();
        $to = $day->endOfDay();

        $payments = DB::table('payments')
            ->where('paid_at', '>=', $day)
            ->where('paid_at', '<=', $to)
            ->selectRaw('method, SUM(amount_cents) as total_cents, COUNT(*) as count')
            ->groupBy('method')
            ->orderBy('method')
            ->get();

        $refunds = DB::table('refunds')
            ->where('refunded_at', '>=', $day)
            ->where('refunded_at', '<=', $to)
            ->selectRaw('SUM(amount_cents) as total_cents, COUNT(*) as count')
            ->first();

        $outstanding = Bill::query()
            ->where('balance_cents', '>', 0)
            ->selectRaw('status, SUM(balance_cents) as total_cents, COUNT(*) as count')
            ->groupBy('status')
            ->orderBy('status')
            ->get();

        return response()->json([
            'data' => [
                'date' => $day->toDateString(),
                'payments' => $payments,
                'refunds' => [
                    'total_cents' => (int) ($refunds?->total_cents ?? 0),
                    'count' => (int) ($refunds?->count ?? 0),
                ],
                'outstanding' => $outstanding,
            ],
        ]);
    }
}
