<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Controller;
use App\Models\ClinicClosedHour;
use App\Models\ClinicClosure;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClinicController extends Controller
{
    use AuthorizesPermissions;

    public function listClosures(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
        ]);

        $from = CarbonImmutable::parse($validated['from'])->startOfDay();
        $to = CarbonImmutable::parse($validated['to'])->endOfDay();

        $closures = ClinicClosure::query()
            ->where('start_at', '<=', $to)
            ->where('end_at', '>=', $from)
            ->orderBy('start_at')
            ->get();

        return response()->json([
            'data' => $closures,
        ]);
    }

    public function storeClosure(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'clinic.manage_schedule');

        $validated = $request->validate([
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $closure = ClinicClosure::create([
            'start_at' => $validated['start_at'],
            'end_at' => $validated['end_at'],
            'reason' => $validated['reason'] ?? null,
            'created_by_user_id' => $request->user()?->id,
        ]);

        return response()->json([
            'data' => $closure,
        ], 201);
    }

    public function storeClosedHour(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'clinic.manage_schedule');

        $validated = $request->validate([
            'day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $closedHour = ClinicClosedHour::create([
            'day_of_week' => (int) $validated['day_of_week'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'data' => $closedHour,
        ], 201);
    }
}
