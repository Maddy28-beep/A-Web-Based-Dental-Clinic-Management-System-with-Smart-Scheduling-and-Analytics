<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\InventoryItem;
use App\Models\Procedure;
use App\Services\InventoryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    use AuthorizesPermissions;

    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'inventory.view');

        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            'active_only' => ['nullable', 'boolean'],
            'low_stock_only' => ['nullable', 'boolean'],
        ]);

        $limit = (int) ($validated['limit'] ?? 50);
        $activeOnly = ! array_key_exists('active_only', $validated) || (bool) $validated['active_only'];
        $lowStockOnly = (bool) ($validated['low_stock_only'] ?? false);

        $query = InventoryItem::query()->orderBy('name');

        if ($activeOnly) {
            $query->where('is_active', true);
        }
        if ($lowStockOnly) {
            $query->whereColumn('current_stock', '<=', 'min_stock');
        }

        return response()->json([
            'data' => $query->with('supplier')->limit($limit)->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'inventory.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:inventory_items,name'],
            'sku' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:20'],
            'current_stock' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'min_stock' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'cost_per_unit_cents' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'preferred_supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'supplier_sku' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'meta' => ['nullable', 'array'],
        ]);

        $item = InventoryItem::create([
            'name' => $validated['name'],
            'sku' => $validated['sku'] ?? null,
            'unit' => $validated['unit'] ?? 'pcs',
            'current_stock' => isset($validated['current_stock']) ? number_format((float) $validated['current_stock'], 2, '.', '') : '0.00',
            'min_stock' => isset($validated['min_stock']) ? number_format((float) $validated['min_stock'], 2, '.', '') : '0.00',
            'cost_per_unit_cents' => (int) ($validated['cost_per_unit_cents'] ?? 0),
            'preferred_supplier_id' => $validated['preferred_supplier_id'] ?? null,
            'supplier_sku' => $validated['supplier_sku'] ?? null,
            'last_purchase_at' => null,
            'last_purchase_qty' => null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'meta' => $validated['meta'] ?? null,
        ]);

        ActivityLog::create([
            'actor_user_id' => $request->user()?->id,
            'patient_id' => null,
            'action' => 'inventory_item.created',
            'subject_type' => InventoryItem::class,
            'subject_id' => $item->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => [
                'actor_role' => $request->user()?->role,
                'name' => $item->name,
                'unit' => $item->unit,
            ],
            'created_at' => now(),
        ]);

        return response()->json([
            'data' => $item->load('supplier'),
        ], 201);
    }

    public function update(Request $request, InventoryItem $inventoryItem): JsonResponse
    {
        $this->requirePermission($request, 'inventory.manage');

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255', Rule::unique('inventory_items', 'name')->ignore($inventoryItem->id)],
            'sku' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:20'],
            'min_stock' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'cost_per_unit_cents' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'preferred_supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'supplier_sku' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'meta' => ['nullable', 'array'],
        ]);

        $before = $inventoryItem->toArray();

        $inventoryItem->update([
            'name' => $validated['name'] ?? $inventoryItem->name,
            'sku' => array_key_exists('sku', $validated) ? $validated['sku'] : $inventoryItem->sku,
            'unit' => $validated['unit'] ?? $inventoryItem->unit,
            'min_stock' => array_key_exists('min_stock', $validated) ? number_format((float) $validated['min_stock'], 2, '.', '') : $inventoryItem->min_stock,
            'cost_per_unit_cents' => array_key_exists('cost_per_unit_cents', $validated) ? (int) $validated['cost_per_unit_cents'] : $inventoryItem->cost_per_unit_cents,
            'preferred_supplier_id' => array_key_exists('preferred_supplier_id', $validated) ? $validated['preferred_supplier_id'] : $inventoryItem->preferred_supplier_id,
            'supplier_sku' => array_key_exists('supplier_sku', $validated) ? $validated['supplier_sku'] : $inventoryItem->supplier_sku,
            'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : $inventoryItem->is_active,
            'meta' => array_key_exists('meta', $validated) ? $validated['meta'] : $inventoryItem->meta,
        ]);

        ActivityLog::create([
            'actor_user_id' => $request->user()?->id,
            'patient_id' => null,
            'action' => 'inventory_item.updated',
            'subject_type' => InventoryItem::class,
            'subject_id' => $inventoryItem->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => [
                'actor_role' => $request->user()?->role,
                'before' => $before,
                'after' => $inventoryItem->fresh()->toArray(),
            ],
            'created_at' => now(),
        ]);

        return response()->json([
            'data' => $inventoryItem->fresh()->load('supplier'),
        ]);
    }

    public function restock(Request $request, InventoryItem $inventoryItem, InventoryService $inventory): JsonResponse
    {
        $this->requirePermission($request, 'inventory.manage');

        $validated = $request->validate([
            'quantity_added' => ['required', 'numeric', 'min:0.01', 'max:100000000'],
            'purchased_at' => ['nullable', 'date'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'cost_per_unit_cents' => ['nullable', 'integer', 'min:0', 'max:100000000'],
        ]);

        $purchasedAt = isset($validated['purchased_at'])
            ? CarbonImmutable::parse($validated['purchased_at'])
            : CarbonImmutable::now();

        $log = $inventory->restock(
            $inventoryItem,
            (float) $validated['quantity_added'],
            $purchasedAt,
            $request->user()?->id,
            $validated['supplier_id'] ?? null,
            $validated['cost_per_unit_cents'] ?? null,
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'data' => [
                'item' => $inventoryItem->fresh()->load('supplier'),
                'log' => $log,
            ],
        ], 201);
    }

    public function adjust(Request $request, InventoryItem $inventoryItem, InventoryService $inventory): JsonResponse
    {
        $this->requirePermission($request, 'inventory.manage');

        $validated = $request->validate([
            'quantity_change' => ['required', 'numeric', 'min:-100000000', 'max:100000000'],
            'reason' => ['required', 'string', 'max:255'],
            'adjusted_at' => ['nullable', 'date'],
        ]);

        $adjustedAt = isset($validated['adjusted_at'])
            ? CarbonImmutable::parse($validated['adjusted_at'])
            : CarbonImmutable::now();

        $adj = $inventory->adjustStock(
            $inventoryItem,
            (float) $validated['quantity_change'],
            $validated['reason'],
            $adjustedAt,
            $request->user()?->id,
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'data' => [
                'item' => $inventoryItem->fresh()->load('supplier'),
                'adjustment' => $adj,
            ],
        ], 201);
    }

    public function lowStock(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'inventory.view');

        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            'days_window' => ['nullable', 'integer', 'min:7', 'max:180'],
        ]);

        $limit = (int) ($validated['limit'] ?? 50);
        $daysWindow = (int) ($validated['days_window'] ?? 30);

        $items = InventoryItem::query()
            ->where('is_active', true)
            ->whereColumn('current_stock', '<=', 'min_stock')
            ->orderByRaw('current_stock / NULLIF(min_stock, 0) asc')
            ->orderBy('name')
            ->limit($limit)
            ->get();

        $since = CarbonImmutable::now()->subDays($daysWindow)->startOfDay();
        $usageMap = DB::table('inventory_logs')
            ->select([
                'inventory_item_id',
                DB::raw("SUM(CASE WHEN action = 'usage' THEN ABS(quantity_change) ELSE 0 END) as used_qty"),
            ])
            ->where('occurred_at', '>=', $since)
            ->groupBy('inventory_item_id')
            ->pluck('used_qty', 'inventory_item_id')
            ->map(fn ($v) => (float) $v)
            ->all();

        $data = $items->map(function (InventoryItem $item) use ($usageMap, $daysWindow) {
            $current = (float) $item->current_stock;
            $min = (float) $item->min_stock;

            $status = 'low';
            if ($min > 0 && $current <= ($min * 0.5)) {
                $status = 'critical';
            }

            $usedQty = (float) ($usageMap[$item->id] ?? 0);
            $avgDaily = $daysWindow > 0 ? ($usedQty / $daysWindow) : 0;
            $daysLeft = $avgDaily > 0 ? round($current / $avgDaily, 1) : null;
            $suggestedReorderQty = max(0, round(($min * 2) - $current, 2));

            return [
                'item' => $item->load('supplier'),
                'status' => $status,
                'estimated_days_left' => $daysLeft,
                'suggested_reorder_qty' => $suggestedReorderQty,
                'days_window' => $daysWindow,
            ];
        })->values();

        return response()->json([
            'data' => $data,
        ]);
    }

    public function monthlyReport(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'inventory.view');

        $validated = $request->validate([
            'month' => ['nullable', 'string', 'regex:/^\\d{4}-\\d{2}$/'],
        ]);

        $month = $validated['month'] ?? CarbonImmutable::now()->format('Y-m');
        $start = CarbonImmutable::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->addMonth();

        $perItem = DB::table('inventory_logs')
            ->join('inventory_items', 'inventory_logs.inventory_item_id', '=', 'inventory_items.id')
            ->where('inventory_logs.occurred_at', '>=', $start)
            ->where('inventory_logs.occurred_at', '<', $end)
            ->groupBy('inventory_items.id', 'inventory_items.name', 'inventory_items.unit')
            ->orderByDesc(DB::raw("ROUND(SUM(CASE WHEN inventory_logs.action = 'usage' THEN ABS(inventory_logs.quantity_change) * COALESCE(inventory_logs.unit_cost_cents, 0) ELSE 0 END))"))
            ->get([
                'inventory_items.id as inventory_item_id',
                'inventory_items.name',
                'inventory_items.unit',
                DB::raw("SUM(CASE WHEN inventory_logs.action = 'usage' THEN ABS(inventory_logs.quantity_change) ELSE 0 END) as used_qty"),
                DB::raw("ROUND(SUM(CASE WHEN inventory_logs.action = 'usage' THEN ABS(inventory_logs.quantity_change) * COALESCE(inventory_logs.unit_cost_cents, 0) ELSE 0 END)) as used_cost_cents"),
                DB::raw("SUM(CASE WHEN inventory_logs.action = 'restock' THEN inventory_logs.quantity_change ELSE 0 END) as restocked_qty"),
                DB::raw("ROUND(SUM(CASE WHEN inventory_logs.action = 'restock' THEN inventory_logs.quantity_change * COALESCE(inventory_logs.unit_cost_cents, 0) ELSE 0 END)) as restocked_cost_cents"),
                DB::raw("SUM(CASE WHEN inventory_logs.action = 'adjustment' AND inventory_logs.quantity_change < 0 THEN ABS(inventory_logs.quantity_change) ELSE 0 END) as wastage_qty"),
                DB::raw("SUM(CASE WHEN inventory_logs.action = 'adjustment' AND inventory_logs.quantity_change > 0 THEN inventory_logs.quantity_change ELSE 0 END) as correction_qty"),
            ]);

        $perProcedureType = DB::table('inventory_logs')
            ->join('procedures', 'inventory_logs.procedure_id', '=', 'procedures.id')
            ->where('inventory_logs.action', 'usage')
            ->where('inventory_logs.occurred_at', '>=', $start)
            ->where('inventory_logs.occurred_at', '<', $end)
            ->groupBy('procedures.procedure_type')
            ->orderByDesc(DB::raw('ROUND(SUM(ABS(inventory_logs.quantity_change) * COALESCE(inventory_logs.unit_cost_cents, 0)))'))
            ->get([
                'procedures.procedure_type',
                DB::raw('SUM(ABS(inventory_logs.quantity_change)) as used_qty'),
                DB::raw('ROUND(SUM(ABS(inventory_logs.quantity_change) * COALESCE(inventory_logs.unit_cost_cents, 0))) as used_cost_cents'),
            ]);

        $perDentist = DB::table('inventory_logs')
            ->leftJoin('dentists', 'inventory_logs.dentist_id', '=', 'dentists.id')
            ->where('inventory_logs.action', 'usage')
            ->where('inventory_logs.occurred_at', '>=', $start)
            ->where('inventory_logs.occurred_at', '<', $end)
            ->groupBy('inventory_logs.dentist_id', 'dentists.name')
            ->orderByDesc(DB::raw('ROUND(SUM(ABS(inventory_logs.quantity_change) * COALESCE(inventory_logs.unit_cost_cents, 0)))'))
            ->get([
                'inventory_logs.dentist_id',
                'dentists.name as dentist_name',
                DB::raw('SUM(ABS(inventory_logs.quantity_change)) as used_qty'),
                DB::raw('ROUND(SUM(ABS(inventory_logs.quantity_change) * COALESCE(inventory_logs.unit_cost_cents, 0))) as used_cost_cents'),
            ]);

        ActivityLog::create([
            'actor_user_id' => $request->user()?->id,
            'patient_id' => null,
            'action' => 'inventory.reported',
            'subject_type' => Procedure::class,
            'subject_id' => null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => [
                'month' => $month,
            ],
            'created_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'month' => $month,
                'start' => $start->toDateString(),
                'end_exclusive' => $end->toDateString(),
                'per_item' => $perItem,
                'per_procedure_type' => $perProcedureType,
                'per_dentist' => $perDentist,
            ],
        ]);
    }
}
