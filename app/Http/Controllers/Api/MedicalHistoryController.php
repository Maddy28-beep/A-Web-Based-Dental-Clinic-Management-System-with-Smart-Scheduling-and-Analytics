<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\MedicalHistory;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MedicalHistoryController extends Controller
{
    use AuthorizesPermissions;

    public function index(Request $request, Patient $patient): JsonResponse
    {
        $this->requirePermission($request, 'clinical.view');

        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $limit = (int) ($validated['limit'] ?? 10);

        $records = MedicalHistory::query()
            ->where('patient_id', $patient->id)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        ActivityLog::create([
            'actor_user_id' => $request->user()?->id,
            'patient_id' => $patient->id,
            'action' => 'medical_history.viewed',
            'subject_type' => Patient::class,
            'subject_id' => $patient->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => ['count' => $records->count()],
            'created_at' => now(),
        ]);

        return response()->json([
            'data' => $records,
        ]);
    }

    public function store(Request $request, Patient $patient): JsonResponse
    {
        $this->requirePermission($request, 'clinical.edit');

        $validated = $request->validate([
            'data' => ['nullable', 'array'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        $record = MedicalHistory::create([
            'patient_id' => $patient->id,
            'data' => $validated['data'] ?? null,
            'recorded_at' => $validated['recorded_at'] ?? now(),
            'created_by_user_id' => $request->user()?->id,
        ]);

        ActivityLog::create([
            'actor_user_id' => $request->user()?->id,
            'patient_id' => $patient->id,
            'action' => 'medical_history.created',
            'subject_type' => MedicalHistory::class,
            'subject_id' => $record->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => null,
            'created_at' => now(),
        ]);

        return response()->json([
            'data' => $record,
        ], 201);
    }
}
