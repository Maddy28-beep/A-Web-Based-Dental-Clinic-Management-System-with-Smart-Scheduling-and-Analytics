<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\ProcedurePrice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class BillingService
{
    public function resolvePrice(string $procedureType, ?int $dentistId): ?ProcedurePrice
    {
        $t = strtolower(trim($procedureType));

        if ($dentistId) {
            $specific = ProcedurePrice::query()
                ->where('is_active', true)
                ->where('procedure_type', $t)
                ->where('dentist_id', $dentistId)
                ->orderByDesc('id')
                ->first();

            if ($specific) {
                return $specific;
            }
        }

        return ProcedurePrice::query()
            ->where('is_active', true)
            ->where('procedure_type', $t)
            ->whereNull('dentist_id')
            ->orderByDesc('id')
            ->first();
    }

    public function computeBaseTotalCents(ProcedurePrice $price, int $toothCount): int
    {
        $count = max(1, $toothCount);
        $base = (int) $price->base_price_cents;
        $perTooth = (int) $price->per_tooth_cents;

        if ($perTooth > 0) {
            return $base + ($perTooth * $count);
        }

        return $base * $count;
    }

    public function recomputeBillTotals(Bill $bill): Bill
    {
        return DB::transaction(function () use ($bill) {
            $lockedBill = Bill::query()->whereKey($bill->id)->lockForUpdate()->firstOrFail();

            $subtotal = (int) $lockedBill->items()->sum('total_cents');
            $total = max(0, $subtotal + (int) $lockedBill->add_ons_cents - (int) $lockedBill->discount_cents);

            $paid = (int) $lockedBill->payments()->sum('amount_cents');
            $refunds = (int) DB::table('refunds')
                ->join('payments', 'refunds.payment_id', '=', 'payments.id')
                ->where('payments.bill_id', $lockedBill->id)
                ->sum('refunds.amount_cents');

            $netPaid = max(0, $paid - $refunds);
            $balance = max(0, $total - $netPaid);

            $status = $this->statusFromAmounts(
                totalCents: $total,
                netPaidCents: $netPaid,
                balanceCents: $balance,
                dueAt: $lockedBill->due_at ? CarbonImmutable::parse($lockedBill->due_at) : null,
            );

            $lockedBill->update([
                'subtotal_cents' => $subtotal,
                'total_cents' => $total,
                'paid_cents' => $netPaid,
                'balance_cents' => $balance,
                'status' => $status,
            ]);

            return $lockedBill->fresh();
        });
    }

    public function computeItemTotalCents(int $baseTotalCents, int $addOnsCents, int $discountCents, ?int $overrideTotalCents = null): int
    {
        $base = $overrideTotalCents !== null ? max(0, (int) $overrideTotalCents) : max(0, (int) $baseTotalCents);

        return max(0, $base + max(0, $addOnsCents) - max(0, $discountCents));
    }

    public function statusFromAmounts(int $totalCents, int $netPaidCents, int $balanceCents, ?CarbonImmutable $dueAt = null): string
    {
        if ($balanceCents <= 0 && $totalCents > 0) {
            return 'paid';
        }

        if ($totalCents <= 0) {
            return 'paid';
        }

        if ($netPaidCents > 0 && $balanceCents > 0) {
            $status = 'partial';
        } else {
            $status = 'unpaid';
        }

        if ($dueAt && $dueAt->lessThanOrEqualTo(CarbonImmutable::now()) && $balanceCents > 0) {
            return 'overdue';
        }

        return $status;
    }
}
