<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Controller;
use App\Models\ProcedurePrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProcedurePriceController extends Controller
{
    use AuthorizesPermissions;

    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'pricing.view');

        $validated = $request->validate([
            'procedure_type' => ['nullable', 'string', 'max:100'],
            'dentist_id' => ['nullable', 'integer', 'exists:dentists,id'],
            'active_only' => ['nullable', 'boolean'],
        ]);

        $query = ProcedurePrice::query()->orderBy('procedure_type')->orderBy('dentist_id');

        if (! empty($validated['procedure_type'])) {
            $query->where('procedure_type', strtolower(trim((string) $validated['procedure_type'])));
        }
        if (array_key_exists('dentist_id', $validated)) {
            $query->where('dentist_id', $validated['dentist_id']);
        }
        if (! array_key_exists('active_only', $validated) || $validated['active_only']) {
            $query->where('is_active', true);
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'pricing.manage');

        $validated = $request->validate([
            'procedure_type' => ['required', 'string', 'max:100'],
            'dentist_id' => ['nullable', 'integer', 'exists:dentists,id'],
            'base_price_cents' => ['required', 'integer', 'min:0', 'max:100000000'],
            'per_tooth_cents' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $price = ProcedurePrice::create([
            'procedure_type' => strtolower(trim($validated['procedure_type'])),
            'dentist_id' => $validated['dentist_id'] ?? null,
            'base_price_cents' => (int) $validated['base_price_cents'],
            'per_tooth_cents' => (int) ($validated['per_tooth_cents'] ?? 0),
            'duration_minutes' => $validated['duration_minutes'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'created_by_user_id' => $request->user()?->id,
        ]);

        return response()->json([
            'data' => $price,
        ], 201);
    }

    public function update(Request $request, ProcedurePrice $procedurePrice): JsonResponse
    {
        $this->requirePermission($request, 'pricing.manage');

        $validated = $request->validate([
            'procedure_type' => ['nullable', 'string', 'max:100'],
            'dentist_id' => ['nullable', 'integer', 'exists:dentists,id'],
            'base_price_cents' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'per_tooth_cents' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $procedurePrice->update([
            'procedure_type' => isset($validated['procedure_type']) ? strtolower(trim($validated['procedure_type'])) : $procedurePrice->procedure_type,
            'dentist_id' => array_key_exists('dentist_id', $validated) ? $validated['dentist_id'] : $procedurePrice->dentist_id,
            'base_price_cents' => array_key_exists('base_price_cents', $validated) ? (int) $validated['base_price_cents'] : $procedurePrice->base_price_cents,
            'per_tooth_cents' => array_key_exists('per_tooth_cents', $validated) ? (int) $validated['per_tooth_cents'] : $procedurePrice->per_tooth_cents,
            'duration_minutes' => array_key_exists('duration_minutes', $validated) ? $validated['duration_minutes'] : $procedurePrice->duration_minutes,
            'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : $procedurePrice->is_active,
        ]);

        return response()->json([
            'data' => $procedurePrice->fresh(),
        ]);
    }
}
