<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitController extends Controller
{
    use AuthorizesPermissions;

    public function index(Request $request, Patient $patient): JsonResponse
    {
        $this->requirePermission($request, 'clinical.view');

        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $limit = (int) ($validated['limit'] ?? 50);

        $visits = Visit::query()
            ->where('patient_id', $patient->id)
            ->orderByDesc('start_at')
            ->with(['dentist'])
            ->limit($limit)
            ->get();

        ActivityLog::create([
            'actor_user_id' => $request->user()?->id,
            'patient_id' => $patient->id,
            'action' => 'visit.viewed',
            'subject_type' => Patient::class,
            'subject_id' => $patient->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => ['count' => $visits->count()],
            'created_at' => now(),
        ]);

        return response()->json([
            'data' => $visits,
        ]);
    }

    public function store(Request $request, Patient $patient): JsonResponse
    {
        $this->requirePermission($request, 'clinical.edit');

        $validated = $request->validate([
            'dentist_id' => ['nullable', 'integer', 'exists:dentists,id'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $visit = Visit::create([
            'patient_id' => $patient->id,
            'dentist_id' => $validated['dentist_id'] ?? null,
            'start_at' => $validated['start_at'] ?? null,
            'end_at' => $validated['end_at'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'created_by_user_id' => $request->user()?->id,
        ]);

        ActivityLog::create([
            'actor_user_id' => $request->user()?->id,
            'patient_id' => $patient->id,
            'action' => 'visit.created',
            'subject_type' => Visit::class,
            'subject_id' => $visit->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => ['dentist_id' => $visit->dentist_id, 'start_at' => $visit->start_at?->toIso8601String()],
            'created_at' => now(),
        ]);

        return response()->json([
            'data' => $visit->load(['dentist']),
        ], 201);
    }
}
