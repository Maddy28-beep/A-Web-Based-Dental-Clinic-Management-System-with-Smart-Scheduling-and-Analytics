<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppointmentPatientController extends Controller
{
    use AuthorizesPermissions;

    public function convertToPatient(Request $request, Appointment $appointment): JsonResponse
    {
        $this->requirePermission($request, 'appointments.convert_to_patient');

        $validated = $request->validate([
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'date_of_birth' => ['nullable', 'date_format:Y-m-d'],
            'sync_patient_contact' => ['nullable', 'boolean'],
        ]);

        $syncPatientContact = (bool) ($validated['sync_patient_contact'] ?? true);

        return DB::transaction(function () use ($request, $appointment, $validated, $syncPatientContact) {
            $patient = null;

            if (isset($validated['patient_id'])) {
                $patient = Patient::findOrFail((int) $validated['patient_id']);
            } else {
                $fullName = $validated['full_name'] ?? $appointment->patient_name;
                $phone = $validated['phone'] ?? $appointment->patient_phone;
                $email = $validated['email'] ?? $appointment->patient_email;

                $patient = Patient::create([
                    'full_name' => $fullName,
                    'phone' => $phone,
                    'email' => $email,
                    'date_of_birth' => $validated['date_of_birth'] ?? null,
                ]);
            }

            $appointment->update([
                'patient_id' => $patient->id,
            ]);

            if ($syncPatientContact) {
                $appointment->update([
                    'patient_name' => $patient->full_name,
                    'patient_phone' => $patient->phone,
                    'patient_email' => $patient->email,
                ]);
            }

            ActivityLog::create([
                'actor_user_id' => $request->user()?->id,
                'patient_id' => $patient->id,
                'action' => 'appointment.converted_to_patient',
                'subject_type' => Appointment::class,
                'subject_id' => $appointment->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'meta' => ['patient_id' => $patient->id],
                'created_at' => now(),
            ]);

            return response()->json([
                'data' => [
                    'appointment' => $appointment->fresh(),
                    'patient' => $patient,
                ],
            ]);
        });
    }
}
