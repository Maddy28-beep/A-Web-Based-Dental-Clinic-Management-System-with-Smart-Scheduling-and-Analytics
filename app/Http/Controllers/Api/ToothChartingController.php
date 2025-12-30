<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\Tooth;
use App\Models\ToothHistory;
use App\Services\ToothChartingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ToothChartingController extends Controller
{
    use AuthorizesPermissions;

    public function index(Request $request, Patient $patient, ToothChartingService $service): JsonResponse
    {
        $this->requirePermission($request, 'clinical.view');

        $validated = $request->validate([
            'dentition' => ['nullable', 'in:adult,pediatric'],
        ]);

        $dentition = $validated['dentition'] ?? 'adult';
        $teeth = $service->ensureTeethExist($patient, $dentition);

        return response()->json([
            'data' => [
                'patient' => $patient,
                'dentition' => $dentition,
                'teeth' => $teeth,
            ],
        ]);
    }

    public function history(Request $request, Patient $patient, string $toothCode): JsonResponse
    {
        $this->requirePermission($request, 'clinical.view');

        $tooth = Tooth::query()
            ->where('patient_id', $patient->id)
            ->where('tooth_code', $toothCode)
            ->firstOrFail();

        $history = $tooth->histories()
            ->orderByDesc('recorded_at')
            ->get();

        ActivityLog::create([
            'actor_user_id' => $request->user()?->id,
            'patient_id' => $patient->id,
            'action' => 'tooth_history.viewed',
            'subject_type' => Tooth::class,
            'subject_id' => $tooth->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => ['tooth_code' => $tooth->tooth_code],
            'created_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'tooth' => $tooth,
                'history' => $history->map(function (ToothHistory $h) {
                    return array_merge($h->toArray(), [
                        'image_before_url' => $h->image_before_path ? Storage::disk('public')->url($h->image_before_path) : null,
                        'image_after_url' => $h->image_after_path ? Storage::disk('public')->url($h->image_after_path) : null,
                    ]);
                })->values(),
            ],
        ]);
    }

    public function storeRecord(Request $request, Patient $patient, string $toothCode, ToothChartingService $service): JsonResponse
    {
        $this->requirePermission($request, 'clinical.edit');

        $validated = $request->validate([
            'dentition' => ['nullable', 'in:adult,pediatric'],
            'condition' => ['required', 'string', 'max:50'],
            'procedure' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'recorded_at' => ['nullable', 'date'],
            'cdt_code' => ['nullable', 'string', 'max:20'],
            'surfaces' => ['nullable', 'array', 'max:5'],
            'surfaces.*' => ['string', 'in:M,D,B,L,O'],
            'image_before' => ['nullable', 'image', 'max:5120'],
            'image_after' => ['nullable', 'image', 'max:5120'],
        ]);

        $dentition = $validated['dentition'] ?? 'adult';
        $service->ensureTeethExist($patient, $dentition);

        $tooth = Tooth::query()
            ->where('patient_id', $patient->id)
            ->where('tooth_code', $toothCode)
            ->firstOrFail();

        $recordedAt = isset($validated['recorded_at'])
            ? CarbonImmutable::parse($validated['recorded_at'])
            : CarbonImmutable::now();

        return DB::transaction(function () use ($request, $patient, $validated, $tooth, $recordedAt, $service) {
            $beforePath = $request->file('image_before')?->store('tooth-images', 'public');
            $afterPath = $request->file('image_after')?->store('tooth-images', 'public');

            $beforeTooth = $tooth->only([
                'condition',
                'procedure',
                'notes',
                'severity',
                'last_recorded_at',
            ]);

            $meta = [];
            if (isset($validated['cdt_code']) && $validated['cdt_code'] !== '') {
                $meta['cdt_code'] = $validated['cdt_code'];
            }
            if (! empty($validated['surfaces'])) {
                $meta['surfaces'] = array_values(array_unique($validated['surfaces']));
            }

            $history = ToothHistory::create([
                'tooth_id' => $tooth->id,
                'condition' => $validated['condition'],
                'procedure' => $validated['procedure'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'recorded_at' => $recordedAt,
                'image_before_path' => $beforePath,
                'image_after_path' => $afterPath,
                'created_by_user_id' => $request->user()?->id,
                'meta' => empty($meta) ? null : $meta,
            ]);

            $tooth->update([
                'condition' => $history->condition,
                'procedure' => $history->procedure,
                'notes' => $history->notes,
                'severity' => $service->severityFromCondition($history->condition),
                'last_recorded_at' => $history->recorded_at,
            ]);

            $afterTooth = $tooth->fresh()->only([
                'condition',
                'procedure',
                'notes',
                'severity',
                'last_recorded_at',
            ]);

            ActivityLog::create([
                'actor_user_id' => $request->user()?->id,
                'patient_id' => $patient->id,
                'action' => 'tooth_history.created',
                'subject_type' => ToothHistory::class,
                'subject_id' => $history->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'meta' => [
                    'actor_role' => $request->user()?->role,
                    'tooth_code' => $tooth->tooth_code,
                    'before' => $beforeTooth,
                    'after' => $afterTooth,
                ],
                'created_at' => now(),
            ]);

            return response()->json([
                'data' => [
                    'tooth' => $tooth->fresh(),
                    'history' => array_merge($history->toArray(), [
                        'image_before_url' => $history->image_before_path ? Storage::disk('public')->url($history->image_before_path) : null,
                        'image_after_url' => $history->image_after_path ? Storage::disk('public')->url($history->image_after_path) : null,
                    ]),
                ],
            ], 201);
        });
    }

    public function procedureHighlights(Request $request, Patient $patient, Procedure $procedure): JsonResponse
    {
        $this->requirePermission($request, 'clinical.view');

        if ($procedure->patient_id !== $patient->id) {
            abort(404);
        }

        $teeth = $procedure->teeth()
            ->orderBy('tooth_code')
            ->get(['tooth_code', 'surfaces']);

        ActivityLog::create([
            'actor_user_id' => $request->user()?->id,
            'patient_id' => $patient->id,
            'action' => 'procedure.viewed',
            'subject_type' => Procedure::class,
            'subject_id' => $procedure->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => ['tooth_codes' => $teeth->pluck('tooth_code')->values()->all()],
            'created_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'procedure' => $procedure,
                'teeth' => $teeth,
                'tooth_codes' => $teeth->pluck('tooth_code')->values(),
            ],
        ]);
    }
}
