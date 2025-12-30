<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Allergy;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AllergyController extends Controller
{
    use AuthorizesPermissions;

    public function index(Request $request, Patient $patient): JsonResponse
    {
        $this->requirePermission($request, 'clinical.view');

        $allergies = Allergy::query()
            ->where('patient_id', $patient->id)
            ->orderByDesc('is_active')
            ->orderBy('severity')
            ->orderBy('tag')
            ->get();

        ActivityLog::create([
            'actor_user_id' => $request->user()?->id,
            'patient_id' => $patient->id,
            'action' => 'allergy.viewed',
            'subject_type' => Patient::class,
            'subject_id' => $patient->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => ['count' => $allergies->count()],
            'created_at' => now(),
        ]);

        return response()->json([
            'data' => $allergies,
        ]);
    }

    public function store(Request $request, Patient $patient): JsonResponse
    {
        $this->requirePermission($request, 'clinical.edit');

        $validated = $request->validate([
            'tag' => ['required', 'string', 'max:100'],
            'severity' => ['required', 'in:mild,moderate,severe'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        $allergy = Allergy::create([
            'patient_id' => $patient->id,
            'tag' => trim($validated['tag']),
            'severity' => $validated['severity'],
            'notes' => $validated['notes'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'recorded_at' => $validated['recorded_at'] ?? null,
            'created_by_user_id' => $request->user()?->id,
            'updated_by_user_id' => $request->user()?->id,
        ]);

        ActivityLog::create([
            'actor_user_id' => $request->user()?->id,
            'patient_id' => $patient->id,
            'action' => 'allergy.created',
            'subject_type' => Allergy::class,
            'subject_id' => $allergy->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => ['severity' => $allergy->severity, 'tag' => $allergy->tag],
            'created_at' => now(),
        ]);

        return response()->json([
            'data' => $allergy,
        ], 201);
    }

    public function update(Request $request, Patient $patient, Allergy $allergy): JsonResponse
    {
        $this->requirePermission($request, 'clinical.edit');

        if ($allergy->patient_id !== $patient->id) {
            abort(404);
        }

        $validated = $request->validate([
            'tag' => ['nullable', 'string', 'max:100'],
            'severity' => ['nullable', 'in:mild,moderate,severe'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $allergy->update([
            'tag' => isset($validated['tag']) ? trim($validated['tag']) : $allergy->tag,
            'severity' => $validated['severity'] ?? $allergy->severity,
            'notes' => array_key_exists('notes', $validated) ? $validated['notes'] : $allergy->notes,
            'is_active' => isset($validated['is_active']) ? (bool) $validated['is_active'] : $allergy->is_active,
            'updated_by_user_id' => $request->user()?->id,
        ]);

        ActivityLog::create([
            'actor_user_id' => $request->user()?->id,
            'patient_id' => $patient->id,
            'action' => 'allergy.updated',
            'subject_type' => Allergy::class,
            'subject_id' => $allergy->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => ['severity' => $allergy->severity, 'tag' => $allergy->tag, 'is_active' => $allergy->is_active],
            'created_at' => now(),
        ]);

        return response()->json([
            'data' => $allergy->fresh(),
        ]);
    }
}
