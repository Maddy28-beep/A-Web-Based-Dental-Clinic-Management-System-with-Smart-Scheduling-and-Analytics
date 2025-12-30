<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\InventoryItem;
use App\Models\InventoryLog;
use App\Models\Procedure;
use App\Models\ProcedureMaterial;
use App\Models\StockAdjustment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function deductForProcedure(Procedure $procedure, int $toothCount, ?int $recordedByUserId, ?string $ipAddress = null, ?string $userAgent = null): void
    {
        DB::transaction(function () use ($procedure, $toothCount, $recordedByUserId, $ipAddress, $userAgent) {
            $materials = ProcedureMaterial::query()
                ->where('is_active', true)
                ->where('procedure_type', strtolower(trim((string) $procedure->procedure_type)))
                ->with('item')
                ->get();

            if ($materials->isEmpty()) {
                return;
            }

            $count = max(1, $toothCount);
            $occurredAt = $procedure->performed_at ? CarbonImmutable::parse($procedure->performed_at) : CarbonImmutable::now();

            foreach ($materials as $pm) {
                $item = InventoryItem::query()->whereKey($pm->inventory_item_id)->lockForUpdate()->first();
                if (! $item || ! $item->is_active) {
                    continue;
                }

                $baseQty = (float) $pm->quantity;
                $deductQty = $pm->is_per_tooth ? ($baseQty * $count) : $baseQty;
                if ($deductQty <= 0) {
                    continue;
                }

                $before = (float) $item->current_stock;
                $afterRaw = $before - $deductQty;
                $after = max(0, $afterRaw);

                $item->update([
                    'current_stock' => number_format($after, 2, '.', ''),
                ]);

                InventoryLog::create([
                    'inventory_item_id' => $item->id,
                    'action' => 'usage',
                    'quantity_change' => number_format(-1 * $deductQty, 2, '.', ''),
                    'unit' => $item->unit,
                    'unit_cost_cents' => $item->cost_per_unit_cents,
                    'patient_id' => $procedure->patient_id,
                    'dentist_id' => $procedure->dentist_id,
                    'procedure_id' => $procedure->id,
                    'stock_before' => number_format($before, 2, '.', ''),
                    'stock_after' => number_format($after, 2, '.', ''),
                    'occurred_at' => $occurredAt,
                    'recorded_by_user_id' => $recordedByUserId,
                    'meta' => $afterRaw < 0 ? [
                        'shortage' => number_format(abs($afterRaw), 2, '.', ''),
                        'attempted_deduct' => number_format($deductQty, 2, '.', ''),
                    ] : null,
                ]);

                ActivityLog::create([
                    'actor_user_id' => $recordedByUserId,
                    'patient_id' => $procedure->patient_id,
                    'action' => 'inventory.deducted',
                    'subject_type' => Procedure::class,
                    'subject_id' => $procedure->id,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'meta' => [
                        'inventory_item_id' => $item->id,
                        'quantity' => number_format($deductQty, 2, '.', ''),
                        'unit' => $item->unit,
                        'tooth_count' => $count,
                    ],
                    'created_at' => now(),
                ]);
            }
        });
    }

    public function adjustStock(InventoryItem $item, float $quantityChange, string $reason, CarbonImmutable $adjustedAt, ?int $adjustedByUserId, ?string $ipAddress = null, ?string $userAgent = null): StockAdjustment
    {
        return DB::transaction(function () use ($item, $quantityChange, $reason, $adjustedAt, $adjustedByUserId, $ipAddress, $userAgent) {
            $locked = InventoryItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();

            $before = (float) $locked->current_stock;
            $after = max(0, $before + $quantityChange);

            $locked->update([
                'current_stock' => number_format($after, 2, '.', ''),
            ]);

            $adj = StockAdjustment::create([
                'inventory_item_id' => $locked->id,
                'quantity_change' => number_format($quantityChange, 2, '.', ''),
                'reason' => $reason,
                'adjusted_at' => $adjustedAt,
                'adjusted_by_user_id' => $adjustedByUserId,
                'meta' => null,
            ]);

            InventoryLog::create([
                'inventory_item_id' => $locked->id,
                'action' => 'adjustment',
                'quantity_change' => number_format($quantityChange, 2, '.', ''),
                'unit' => $locked->unit,
                'unit_cost_cents' => $locked->cost_per_unit_cents,
                'patient_id' => null,
                'dentist_id' => null,
                'procedure_id' => null,
                'stock_before' => number_format($before, 2, '.', ''),
                'stock_after' => number_format($after, 2, '.', ''),
                'occurred_at' => $adjustedAt,
                'recorded_by_user_id' => $adjustedByUserId,
                'meta' => [
                    'stock_adjustment_id' => $adj->id,
                    'reason' => $reason,
                ],
            ]);

            ActivityLog::create([
                'actor_user_id' => $adjustedByUserId,
                'patient_id' => null,
                'action' => 'inventory.adjusted',
                'subject_type' => InventoryItem::class,
                'subject_id' => $locked->id,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'meta' => [
                    'quantity_change' => number_format($quantityChange, 2, '.', ''),
                    'unit' => $locked->unit,
                    'reason' => $reason,
                ],
                'created_at' => now(),
            ]);

            return $adj;
        });
    }

    public function restock(InventoryItem $item, float $quantityAdded, CarbonImmutable $purchasedAt, ?int $recordedByUserId, ?int $supplierId = null, ?int $costPerUnitCents = null, ?string $ipAddress = null, ?string $userAgent = null): InventoryLog
    {
        return DB::transaction(function () use ($item, $quantityAdded, $purchasedAt, $recordedByUserId, $supplierId, $costPerUnitCents, $ipAddress, $userAgent) {
            $locked = InventoryItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();

            $qty = max(0, $quantityAdded);
            $before = (float) $locked->current_stock;
            $after = $before + $qty;

            $locked->update([
                'current_stock' => number_format($after, 2, '.', ''),
                'preferred_supplier_id' => $supplierId ?? $locked->preferred_supplier_id,
                'cost_per_unit_cents' => $costPerUnitCents !== null ? max(0, $costPerUnitCents) : $locked->cost_per_unit_cents,
                'last_purchase_at' => $purchasedAt,
                'last_purchase_qty' => number_format($qty, 2, '.', ''),
            ]);

            $log = InventoryLog::create([
                'inventory_item_id' => $locked->id,
                'action' => 'restock',
                'quantity_change' => number_format($qty, 2, '.', ''),
                'unit' => $locked->unit,
                'unit_cost_cents' => $costPerUnitCents ?? $locked->cost_per_unit_cents,
                'patient_id' => null,
                'dentist_id' => null,
                'procedure_id' => null,
                'stock_before' => number_format($before, 2, '.', ''),
                'stock_after' => number_format($after, 2, '.', ''),
                'occurred_at' => $purchasedAt,
                'recorded_by_user_id' => $recordedByUserId,
                'meta' => $supplierId ? ['supplier_id' => $supplierId] : null,
            ]);

            ActivityLog::create([
                'actor_user_id' => $recordedByUserId,
                'patient_id' => null,
                'action' => 'inventory.restocked',
                'subject_type' => InventoryItem::class,
                'subject_id' => $locked->id,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'meta' => [
                    'quantity_added' => number_format($qty, 2, '.', ''),
                    'unit' => $locked->unit,
                    'supplier_id' => $supplierId,
                ],
                'created_at' => now(),
            ]);

            return $log;
        });
    }
}
