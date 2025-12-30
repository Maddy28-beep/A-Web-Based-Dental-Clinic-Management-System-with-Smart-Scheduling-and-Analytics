<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\Receipt;
use App\Services\BillingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillPaymentController extends Controller
{
    use AuthorizesPermissions;

    public function store(Request $request, Bill $bill, BillingService $billing): JsonResponse
    {
        $this->requirePermission($request, 'payments.record');

        $validated = $request->validate([
            'method' => ['required', 'string', 'max:50'],
            'amount_cents' => ['required', 'integer', 'min:1', 'max:100000000'],
            'paid_at' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $paidAt = isset($validated['paid_at']) ? CarbonImmutable::parse($validated['paid_at']) : CarbonImmutable::now();

        $result = DB::transaction(function () use ($request, $bill, $validated, $paidAt, $billing) {
            $lockedBill = Bill::query()->whereKey($bill->id)->lockForUpdate()->firstOrFail();

            if (! $lockedBill->locked_at) {
                $lockedBill->update([
                    'locked_at' => now(),
                    'locked_by_user_id' => $request->user()?->id,
                ]);
            }

            $lockedBill = $billing->recomputeBillTotals($lockedBill);

            if ($lockedBill->total_cents <= 0) {
                throw new \RuntimeException('Bill total is zero.');
            }

            if ($lockedBill->balance_cents <= 0) {
                throw new \RuntimeException('Bill is already fully paid.');
            }

            $beforeBill = $lockedBill->only([
                'status',
                'total_cents',
                'paid_cents',
                'balance_cents',
                'locked_at',
            ]);

            $amount = (int) $validated['amount_cents'];
            if ($amount > $lockedBill->balance_cents) {
                $amount = (int) $lockedBill->balance_cents;
            }

            $payment = Payment::create([
                'bill_id' => $lockedBill->id,
                'method' => strtolower(trim($validated['method'])),
                'amount_cents' => $amount,
                'paid_at' => $paidAt,
                'recorded_by_user_id' => $request->user()?->id,
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'meta' => null,
            ]);

            $nextReceiptNumber = ((int) Receipt::query()->lockForUpdate()->max('receipt_number')) + 1;
            $receipt = Receipt::create([
                'payment_id' => $payment->id,
                'receipt_number' => $nextReceiptNumber,
                'issued_at' => $paidAt,
                'issued_by_user_id' => $request->user()?->id,
                'meta' => null,
            ]);

            $newPaid = (int) $lockedBill->paid_cents + $amount;
            $newBalance = max(0, (int) $lockedBill->total_cents - $newPaid);
            $newStatus = $billing->statusFromAmounts(
                totalCents: (int) $lockedBill->total_cents,
                netPaidCents: $newPaid,
                balanceCents: $newBalance,
                dueAt: $lockedBill->due_at ? CarbonImmutable::parse($lockedBill->due_at) : null,
            );

            $lockedBill->update([
                'paid_cents' => $newPaid,
                'balance_cents' => $newBalance,
                'status' => $newStatus,
            ]);

            $afterBill = $lockedBill->fresh()->only([
                'status',
                'total_cents',
                'paid_cents',
                'balance_cents',
                'locked_at',
            ]);

            ActivityLog::create([
                'actor_user_id' => $request->user()?->id,
                'patient_id' => $lockedBill->patient_id,
                'action' => 'payment.recorded',
                'subject_type' => Payment::class,
                'subject_id' => $payment->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'meta' => [
                    'actor_role' => $request->user()?->role,
                    'bill_id' => $lockedBill->id,
                    'before' => $beforeBill,
                    'after' => $afterBill,
                    'amount_cents' => $amount,
                    'method' => $payment->method,
                    'receipt_number' => $receipt->receipt_number,
                ],
                'created_at' => now(),
            ]);

            return [
                'bill' => $lockedBill->fresh(),
                'payment' => $payment->load('receipt'),
            ];
        });

        return response()->json([
            'data' => $result,
        ], 201);
    }
}
