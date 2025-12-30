<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\Visit;
use App\Models\XrayFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class XrayFileController extends Controller
{
    use AuthorizesPermissions;

    public function index(Request $request, Patient $patient): JsonResponse
    {
        $this->requirePermission($request, 'clinical.view');

        $validated = $request->validate([
            'visit_id' => ['nullable', 'integer'],
            'procedure_id' => ['nullable', 'integer'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $limit = (int) ($validated['limit'] ?? 50);

        $query = XrayFile::query()
            ->where('patient_id', $patient->id)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id');

        if (isset($validated['visit_id'])) {
            $query->where('visit_id', (int) $validated['visit_id']);
        }
        if (isset($validated['procedure_id'])) {
            $query->where('procedure_id', (int) $validated['procedure_id']);
        }

        $files = $query->limit($limit)->get()->map(function (XrayFile $f) {
            return array_merge($f->toArray(), [
                'preview_url' => route('api.xrays.show', ['patient' => $f->patient_id, 'xray' => $f->id]),
            ]);
        })->values();

        ActivityLog::create([
            'actor_user_id' => $request->user()?->id,
            'patient_id' => $patient->id,
            'action' => 'xray.listed',
            'subject_type' => Patient::class,
            'subject_id' => $patient->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => ['count' => $files->count()],
            'created_at' => now(),
        ]);

        return response()->json([
            'data' => $files,
        ]);
    }

    public function store(Request $request, Patient $patient): JsonResponse
    {
        $this->requirePermission($request, 'clinical.edit');

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'visit_id' => ['nullable', 'integer', 'exists:visits,id'],
            'procedure_id' => ['nullable', 'integer', 'exists:procedures,id'],
            'tooth_code' => ['nullable', 'string', 'max:10'],
            'recorded_at' => ['nullable', 'date'],
            'meta' => ['nullable', 'array'],
        ]);

        if (isset($validated['visit_id']) && ! Visit::query()->where('id', (int) $validated['visit_id'])->where('patient_id', $patient->id)->exists()) {
            return response()->json([
                'message' => 'Visit does not belong to patient.',
            ], 422);
        }

        if (isset($validated['procedure_id']) && ! Procedure::query()->where('id', (int) $validated['procedure_id'])->where('patient_id', $patient->id)->exists()) {
            return response()->json([
                'message' => 'Procedure does not belong to patient.',
            ], 422);
        }

        $file = $request->file('file');
        $bytes = file_get_contents($file->getRealPath());
        if ($bytes === false) {
            abort(422, 'Failed to read uploaded file.');
        }
        $encrypted = Crypt::encrypt($bytes);

        $visitFolder = isset($validated['visit_id']) ? (string) $validated['visit_id'] : 'unassigned';
        $name = Str::uuid()->toString().'.enc';
        $path = "patients/{$patient->id}/xray/{$visitFolder}/{$name}";
        Storage::disk('local')->put($path, $encrypted);

        $record = XrayFile::create([
            'patient_id' => $patient->id,
            'visit_id' => $validated['visit_id'] ?? null,
            'procedure_id' => $validated['procedure_id'] ?? null,
            'tooth_code' => $validated['tooth_code'] ?? null,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType() ?: 'application/octet-stream',
            'size_bytes' => $file->getSize() ?: 0,
            'encrypted_path' => $path,
            'recorded_at' => $validated['recorded_at'] ?? now(),
            'uploaded_by_user_id' => $request->user()?->id,
            'meta' => $validated['meta'] ?? null,
        ]);

        ActivityLog::create([
            'actor_user_id' => $request->user()?->id,
            'patient_id' => $patient->id,
            'action' => 'xray.uploaded',
            'subject_type' => XrayFile::class,
            'subject_id' => $record->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => ['tooth_code' => $record->tooth_code, 'mime_type' => $record->mime_type],
            'created_at' => now(),
        ]);

        return response()->json([
            'data' => array_merge($record->toArray(), [
                'preview_url' => route('api.xrays.show', ['patient' => $patient->id, 'xray' => $record->id]),
            ]),
        ], 201);
    }

    public function show(Request $request, Patient $patient, XrayFile $xray)
    {
        $this->requirePermission($request, 'clinical.view');

        if ($xray->patient_id !== $patient->id) {
            abort(404);
        }

        $encrypted = Storage::disk('local')->get($xray->encrypted_path);
        $bytes = Crypt::decrypt($encrypted);

        ActivityLog::create([
            'actor_user_id' => $request->user()?->id,
            'patient_id' => $patient->id,
            'action' => 'xray.viewed',
            'subject_type' => XrayFile::class,
            'subject_id' => $xray->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => ['tooth_code' => $xray->tooth_code],
            'created_at' => now(),
        ]);

        return response($bytes, 200, [
            'Content-Type' => $xray->mime_type,
            'Content-Disposition' => 'inline; filename="'.$xray->original_name.'"',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }
}
