<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\BillingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentRefundController extends Controller
{
    use AuthorizesPermissions;

    public function store(Request $request, Payment $payment, BillingService $billing): JsonResponse
    {
        $this->requirePermission($request, 'refunds.create');

        $validated = $request->validate([
            'amount_cents' => ['required', 'integer', 'min:1', 'max:100000000'],
            'reason' => ['required', 'string', 'max:255'],
            'refunded_at' => ['nullable', 'date'],
        ]);

        $refundedAt = isset($validated['refunded_at']) ? CarbonImmutable::parse($validated['refunded_at']) : CarbonImmutable::now();

        $result = DB::transaction(function () use ($request, $payment, $validated, $refundedAt, $billing) {
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            $bill = Bill::query()->whereKey($lockedPayment->bill_id)->lockForUpdate()->firstOrFail();

            $alreadyRefunded = (int) Refund::query()->where('payment_id', $lockedPayment->id)->sum('amount_cents');
            $remaining = max(0, (int) $lockedPayment->amount_cents - $alreadyRefunded);
            if ($remaining <= 0) {
                throw new \RuntimeException('Payment is fully refunded.');
            }

            $amount = min((int) $validated['amount_cents'], $remaining);

            $refund = Refund::create([
                'payment_id' => $lockedPayment->id,
                'amount_cents' => $amount,
                'reason' => $validated['reason'],
                'refunded_at' => $refundedAt,
                'refunded_by_user_id' => $request->user()?->id,
                'meta' => null,
            ]);

            $bill = $billing->recomputeBillTotals($bill);

            ActivityLog::create([
                'actor_user_id' => $request->user()?->id,
                'patient_id' => $bill->patient_id,
                'action' => 'refund.created',
                'subject_type' => Refund::class,
                'subject_id' => $refund->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'meta' => [
                    'bill_id' => $bill->id,
                    'payment_id' => $lockedPayment->id,
                    'amount_cents' => $amount,
                ],
                'created_at' => now(),
            ]);

            return [
                'bill' => $bill,
                'payment' => $lockedPayment->fresh()->load('receipt'),
                'refund' => $refund,
            ];
        });

        return response()->json([
            'data' => $result,
        ], 201);
    }
}
