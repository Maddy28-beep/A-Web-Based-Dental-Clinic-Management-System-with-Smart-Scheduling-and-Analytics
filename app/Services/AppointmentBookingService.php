<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentStatusLog;
use App\Models\ClinicClosedHour;
use App\Models\ClinicClosure;
use App\Models\Dentist;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AppointmentBookingService
{
    public function book(
        Dentist $dentist,
        Service $service,
        CarbonImmutable $startAt,
        string $patientName,
        ?string $patientEmail,
        ?string $patientPhone,
        ?string $notes,
        bool $overrideConflicts = false,
        ?string $overrideReason = null,
        string $source = 'online',
    ): Appointment {
        if ($startAt->lessThan(CarbonImmutable::now())) {
            throw new \DomainException('Cannot book an appointment in the past.');
        }

        if (! $dentist->is_active) {
            throw new \DomainException('Dentist is not active.');
        }

        if (! $service->is_active) {
            throw new \DomainException('Service is not active.');
        }

        $workingHour = $dentist->workingHours()
            ->where('day_of_week', $startAt->dayOfWeek)
            ->where('is_active', true)
            ->first();

        if (! $workingHour) {
            throw new \DomainException('Dentist is not available on this day.');
        }

        $slotMinutes = (int) $workingHour->slot_minutes;
        $serviceDurationMinutes = (int) $service->duration_minutes;
        $bufferMinutes = (int) $service->buffer_minutes;
        $reservedMinutes = $serviceDurationMinutes + $bufferMinutes;

        if ($serviceDurationMinutes <= 0 || $serviceDurationMinutes % $slotMinutes !== 0 || $bufferMinutes < 0) {
            throw new \DomainException('Invalid appointment duration.');
        }

        $workStart = $startAt->startOfDay()->setTimeFromTimeString($workingHour->start_time);
        $workEnd = $startAt->startOfDay()->setTimeFromTimeString($workingHour->end_time);

        $endAt = $startAt->addMinutes($reservedMinutes);

        if ($startAt->lessThan($workStart) || $endAt->greaterThan($workEnd)) {
            throw new \DomainException('Requested time is outside dentist working hours.');
        }

        $offsetMinutes = $workStart->diffInMinutes($startAt, false);
        if ($offsetMinutes < 0 || $offsetMinutes % $slotMinutes !== 0) {
            throw new \DomainException('Requested time is not aligned with slot schedule.');
        }

        if ($workingHour->break_start_time && $workingHour->break_end_time) {
            $breakStart = $startAt->startOfDay()->setTimeFromTimeString($workingHour->break_start_time);
            $breakEnd = $startAt->startOfDay()->setTimeFromTimeString($workingHour->break_end_time);

            if ($breakStart->lessThan($endAt) && $breakEnd->greaterThan($startAt)) {
                throw new \DomainException('Requested time overlaps dentist break time.');
            }
        }

        $clinicClosedHours = ClinicClosedHour::query()
            ->where('day_of_week', $startAt->dayOfWeek)
            ->where('is_active', true)
            ->get(['start_time', 'end_time']);

        foreach ($clinicClosedHours as $ch) {
            $closedStart = $startAt->startOfDay()->setTimeFromTimeString($ch->start_time);
            $closedEnd = $startAt->startOfDay()->setTimeFromTimeString($ch->end_time);

            if ($closedStart->lessThan($endAt) && $closedEnd->greaterThan($startAt)) {
                throw new \DomainException('Requested time overlaps clinic closed hours.');
            }
        }

        return DB::transaction(function () use ($dentist, $service, $startAt, $endAt, $serviceDurationMinutes, $bufferMinutes, $patientName, $patientEmail, $patientPhone, $notes, $overrideConflicts, $overrideReason, $source) {
            $clinicIsClosed = ClinicClosure::query()
                ->where('start_at', '<', $endAt)
                ->where('end_at', '>', $startAt)
                ->lockForUpdate()
                ->exists();

            if ($clinicIsClosed) {
                throw new \DomainException('Clinic is closed at the requested time.');
            }

            $hasTimeOff = $dentist->timeOffs()
                ->where('start_at', '<', $endAt)
                ->where('end_at', '>', $startAt)
                ->lockForUpdate()
                ->exists();

            if ($hasTimeOff) {
                throw new \DomainException('Dentist is not available at the requested time.');
            }

            if (! $overrideConflicts) {
                $hasConflict = Appointment::query()
                    ->where('dentist_id', $dentist->id)
                    ->whereIn('status', ['booked', 'checked_in', 'in_treatment'])
                    ->where('start_at', '<', $endAt)
                    ->where('end_at', '>', $startAt)
                    ->lockForUpdate()
                    ->exists();

                if ($hasConflict) {
                    throw new \DomainException('Time slot is already booked.');
                }
            }

            $code = $this->generateBookingReferenceCode($startAt);
            while (Appointment::query()->where('booking_reference_code', $code)->lockForUpdate()->exists()) {
                $code = $this->generateBookingReferenceCode($startAt);
            }

            $appointment = Appointment::create([
                'dentist_id' => $dentist->id,
                'service_id' => $service->id,
                'service_duration_minutes' => $serviceDurationMinutes,
                'buffer_minutes' => $bufferMinutes,
                'is_override' => $overrideConflicts,
                'override_reason' => $overrideReason,
                'patient_name' => $patientName,
                'patient_email' => $patientEmail,
                'patient_phone' => $patientPhone,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'status' => 'booked',
                'booking_reference_code' => $code,
                'source' => $source,
                'notes' => $notes,
            ]);

            AppointmentStatusLog::create([
                'appointment_id' => $appointment->id,
                'from_status' => null,
                'to_status' => 'booked',
                'changed_by_user_id' => null,
                'changed_at' => now(),
                'meta' => [
                    'source' => $source,
                ],
            ]);

            return $appointment;
        }, 3);
    }

    private function generateBookingReferenceCode(CarbonImmutable $startAt): string
    {
        return sprintf(
            'DENT-%s-%s-%s',
            $startAt->format('Y'),
            $startAt->format('md'),
            strtoupper(Str::random(4))
        );
    }
}
