<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\AppointmentCheckin;
use App\Models\AppointmentStatusLog;
use App\Models\Dentist;
use App\Models\Patient;
use App\Models\Service;
use App\Services\AppointmentBookingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppointmentController extends Controller
{
    use AuthorizesPermissions;

    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'appointments.view');

        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'string', 'max:50'],
            'unlinked_only' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $limit = (int) ($validated['limit'] ?? 50);
        $from = isset($validated['from']) ? CarbonImmutable::parse($validated['from'])->startOfDay() : null;
        $to = isset($validated['to']) ? CarbonImmutable::parse($validated['to'])->endOfDay() : null;

        $query = Appointment::query()->orderByDesc('start_at');

        if ($from) {
            $query->where('start_at', '>=', $from);
        }
        if ($to) {
            $query->where('start_at', '<=', $to);
        }
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['unlinked_only'])) {
            $query->whereNull('patient_id');
        }

        $appointments = $query->with(['dentist', 'service', 'patient'])->limit($limit)->get();

        return response()->json([
            'data' => $appointments,
        ]);
    }

    public function store(Request $request, AppointmentBookingService $bookingService): JsonResponse
    {
        $validated = $request->validate([
            'dentist_id' => ['required', 'integer', 'exists:dentists,id'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'start_at' => ['required', 'date'],
            'patient_name' => ['required', 'string', 'max:255'],
            'patient_email' => ['nullable', 'email', 'max:255'],
            'patient_phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'override_conflicts' => ['nullable', 'boolean'],
            'override_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $dentist = Dentist::findOrFail($validated['dentist_id']);
        $service = Service::findOrFail($validated['service_id']);
        $startAt = CarbonImmutable::parse($validated['start_at']);
        $overrideConflicts = (bool) ($validated['override_conflicts'] ?? false);
        $overrideReason = $validated['override_reason'] ?? null;

        if ($overrideConflicts && ! $overrideReason) {
            return response()->json([
                'message' => 'Override reason is required when overriding conflicts.',
            ], 422);
        }

        try {
            $appointment = $bookingService->book(
                dentist: $dentist,
                service: $service,
                startAt: $startAt,
                patientName: $validated['patient_name'],
                patientEmail: $validated['patient_email'] ?? null,
                patientPhone: $validated['patient_phone'] ?? null,
                notes: $validated['notes'] ?? null,
                overrideConflicts: $overrideConflicts,
                overrideReason: $overrideReason,
            );
        } catch (\DomainException $e) {
            $status = $e->getMessage() === 'Time slot is already booked.' ? 409 : 422;

            return response()->json([
                'message' => $e->getMessage(),
            ], $status);
        }

        return response()->json([
            'data' => array_merge($appointment->load(['dentist', 'service'])->toArray(), [
                'confirmation_url' => url('/booking/'.$appointment->booking_reference_code),
            ]),
        ], 201);
    }

    public function lookupByReference(Request $request, string $bookingReferenceCode): JsonResponse
    {
        $bookingReferenceCode = strtoupper(trim($bookingReferenceCode));

        $appointment = Appointment::query()
            ->where('booking_reference_code', $bookingReferenceCode)
            ->with(['dentist', 'service'])
            ->first();

        if (! $appointment) {
            return response()->json([
                'message' => 'Booking reference not found.',
            ], 404);
        }

        return response()->json([
            'data' => array_merge($appointment->toArray(), [
                'confirmation_url' => url('/booking/'.$appointment->booking_reference_code),
            ]),
        ]);
    }

    public function checkInByReference(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'appointments.checkin');

        $validated = $request->validate([
            'booking_reference_code' => ['required', 'string', 'max:30'],
        ]);

        $code = strtoupper(trim((string) $validated['booking_reference_code']));
        $now = CarbonImmutable::now();

        $appointment = DB::transaction(function () use ($request, $code, $now) {
            $appt = Appointment::query()
                ->where('booking_reference_code', $code)
                ->lockForUpdate()
                ->first();

            if (! $appt) {
                abort(404, 'Booking reference not found.');
            }

            if (in_array($appt->status, ['cancelled', 'no_show', 'completed'], true)) {
                abort(409, 'Appointment can no longer be checked in.');
            }

            $from = (string) $appt->status;

            if ($appt->status === 'checked_in') {
                return $appt;
            }

            $before = $appt->only(['status', 'checked_in_at']);

            $appt->update([
                'status' => 'checked_in',
                'checked_in_at' => $appt->checked_in_at ?? $now,
            ]);

            $after = $appt->fresh()->only(['status', 'checked_in_at']);

            $patient = null;
            $canConvertToPatient = (bool) $request->user()?->hasPermission('appointments.convert_to_patient');
            if (! $appt->patient_id && $canConvertToPatient) {
                $email = $appt->patient_email ? strtolower(trim((string) $appt->patient_email)) : null;
                $phone = $appt->patient_phone ? trim((string) $appt->patient_phone) : null;

                if ($email) {
                    $patient = Patient::query()->where('email', $email)->first();
                }
                if (! $patient && $phone) {
                    $patient = Patient::query()->where('phone', $phone)->first();
                }
                if (! $patient) {
                    $patient = Patient::create([
                        'full_name' => $appt->patient_name,
                        'email' => $email ?: null,
                        'phone' => $phone ?: null,
                        'date_of_birth' => null,
                    ]);
                }

                $appt->update([
                    'patient_id' => $patient->id,
                    'patient_name' => $patient->full_name,
                    'patient_phone' => $patient->phone,
                    'patient_email' => $patient->email,
                ]);

                ActivityLog::create([
                    'actor_user_id' => $request->user()?->id,
                    'patient_id' => $patient->id,
                    'action' => 'appointment.converted_to_patient',
                    'subject_type' => Appointment::class,
                    'subject_id' => $appt->id,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'meta' => [
                        'patient_id' => $patient->id,
                        'auto' => true,
                    ],
                    'created_at' => now(),
                ]);
            }

            AppointmentCheckin::updateOrCreate(
                ['appointment_id' => $appt->id],
                [
                    'checked_in_at' => $now,
                    'checked_in_by_user_id' => $request->user()?->id,
                    'method' => 'reference_code',
                    'meta' => null,
                ],
            );

            AppointmentStatusLog::create([
                'appointment_id' => $appt->id,
                'from_status' => $from,
                'to_status' => 'checked_in',
                'changed_by_user_id' => $request->user()?->id,
                'changed_at' => $now,
                'meta' => [
                    'method' => 'reference_code',
                ],
            ]);

            ActivityLog::create([
                'actor_user_id' => $request->user()?->id,
                'patient_id' => $appt->patient_id,
                'action' => 'appointment.checked_in',
                'subject_type' => Appointment::class,
                'subject_id' => $appt->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'meta' => [
                    'actor_role' => $request->user()?->role,
                    'booking_reference_code' => $code,
                    'before' => $before,
                    'after' => $after,
                ],
                'created_at' => now(),
            ]);

            return $appt;
        });

        return response()->json([
            'data' => $appointment->fresh()->load(['dentist', 'service', 'patient', 'checkin']),
        ]);
    }

    public function updateStatus(Request $request, Appointment $appointment): JsonResponse
    {
        $this->requirePermission($request, 'appointments.update_status');

        $validated = $request->validate([
            'status' => ['required', 'string', 'max:50'],
        ]);

        $to = strtolower(trim((string) $validated['status']));
        $now = CarbonImmutable::now();

        $appointment = DB::transaction(function () use ($request, $appointment, $to, $now) {
            $locked = Appointment::query()->whereKey($appointment->id)->lockForUpdate()->firstOrFail();
            $from = (string) $locked->status;

            $before = $locked->only([
                'status',
                'checked_in_at',
                'in_treatment_at',
                'completed_at',
                'cancelled_at',
                'no_show_at',
            ]);

            $allowed = [
                'booked' => ['checked_in', 'in_treatment', 'cancelled', 'no_show'],
                'checked_in' => ['in_treatment', 'completed', 'cancelled'],
                'in_treatment' => ['completed'],
                'completed' => [],
                'cancelled' => [],
                'no_show' => [],
            ];

            if (! array_key_exists($from, $allowed)) {
                abort(422, 'Invalid current appointment status.');
            }

            if ($to === $from) {
                return $locked;
            }

            if (! in_array($to, $allowed[$from], true)) {
                abort(409, 'Invalid status transition.');
            }

            $updates = ['status' => $to];
            if ($to === 'checked_in') {
                $updates['checked_in_at'] = $locked->checked_in_at ?? $now;
            }
            if ($to === 'in_treatment') {
                $updates['in_treatment_at'] = $locked->in_treatment_at ?? $now;
            }
            if ($to === 'completed') {
                $updates['completed_at'] = $locked->completed_at ?? $now;
            }
            if ($to === 'cancelled') {
                $updates['cancelled_at'] = $locked->cancelled_at ?? $now;
            }
            if ($to === 'no_show') {
                $updates['no_show_at'] = $locked->no_show_at ?? $now;
            }

            $locked->update($updates);

            $after = $locked->fresh()->only([
                'status',
                'checked_in_at',
                'in_treatment_at',
                'completed_at',
                'cancelled_at',
                'no_show_at',
            ]);

            AppointmentStatusLog::create([
                'appointment_id' => $locked->id,
                'from_status' => $from,
                'to_status' => $to,
                'changed_by_user_id' => $request->user()?->id,
                'changed_at' => $now,
                'meta' => null,
            ]);

            ActivityLog::create([
                'actor_user_id' => $request->user()?->id,
                'patient_id' => $locked->patient_id,
                'action' => 'appointment.status_updated',
                'subject_type' => Appointment::class,
                'subject_id' => $locked->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'meta' => [
                    'actor_role' => $request->user()?->role,
                    'from' => $from,
                    'to' => $to,
                    'before' => $before,
                    'after' => $after,
                ],
                'created_at' => now(),
            ]);

            return $locked;
        });

        return response()->json([
            'data' => $appointment->fresh()->load(['dentist', 'service', 'patient', 'checkin']),
        ]);
    }
}
