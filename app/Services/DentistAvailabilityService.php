<?php

namespace App\Services;

use App\Models\ClinicClosedHour;
use App\Models\ClinicClosure;
use App\Models\Dentist;
use Carbon\CarbonImmutable;

class DentistAvailabilityService
{
    public function getDailyAvailability(Dentist $dentist, CarbonImmutable $date, int $serviceDurationMinutes = 0, int $bufferMinutes = 0): array
    {
        $workingHour = $dentist->workingHours()
            ->where('day_of_week', $date->dayOfWeek)
            ->where('is_active', true)
            ->first();

        if (! $workingHour || ! $dentist->is_active) {
            return [];
        }

        $slotMinutes = (int) $workingHour->slot_minutes;

        if ($serviceDurationMinutes <= 0) {
            $serviceDurationMinutes = $slotMinutes;
        }

        if ($serviceDurationMinutes % $slotMinutes !== 0 || $bufferMinutes < 0) {
            return [];
        }

        $workStart = $date->setTimeFromTimeString($workingHour->start_time);
        $workEnd = $date->setTimeFromTimeString($workingHour->end_time);

        if ($workEnd->lessThanOrEqualTo($workStart)) {
            return [];
        }

        $appointments = $dentist->appointments()
            ->where('status', 'booked')
            ->where('start_at', '<', $workEnd)
            ->where('end_at', '>', $workStart)
            ->get(['start_at', 'end_at'])
            ->all();

        $timeOffs = $dentist->timeOffs()
            ->where('start_at', '<', $workEnd)
            ->where('end_at', '>', $workStart)
            ->get(['start_at', 'end_at'])
            ->all();

        $blockedIntervals = [];

        if ($workingHour->break_start_time && $workingHour->break_end_time) {
            $breakStart = $date->setTimeFromTimeString($workingHour->break_start_time);
            $breakEnd = $date->setTimeFromTimeString($workingHour->break_end_time);

            if ($breakEnd->greaterThan($breakStart)) {
                $blockedIntervals[] = [$breakStart, $breakEnd];
            }
        }

        $clinicClosedHours = ClinicClosedHour::query()
            ->where('day_of_week', $date->dayOfWeek)
            ->where('is_active', true)
            ->get(['start_time', 'end_time']);

        foreach ($clinicClosedHours as $ch) {
            $closedStart = $date->setTimeFromTimeString($ch->start_time);
            $closedEnd = $date->setTimeFromTimeString($ch->end_time);

            if ($closedEnd->greaterThan($closedStart)) {
                $blockedIntervals[] = [$closedStart, $closedEnd];
            }
        }

        $clinicClosures = ClinicClosure::query()
            ->where('start_at', '<', $workEnd)
            ->where('end_at', '>', $workStart)
            ->get(['start_at', 'end_at']);

        foreach ($clinicClosures as $closure) {
            $blockedIntervals[] = [CarbonImmutable::parse($closure->start_at), CarbonImmutable::parse($closure->end_at)];
        }

        $available = [];
        $reservedMinutes = $serviceDurationMinutes + $bufferMinutes;

        for ($cursor = $workStart; $cursor->addMinutes($reservedMinutes)->lessThanOrEqualTo($workEnd); $cursor = $cursor->addMinutes($slotMinutes)) {
            $slotStart = $cursor;
            $serviceEnd = $cursor->addMinutes($serviceDurationMinutes);
            $slotEnd = $cursor->addMinutes($reservedMinutes);

            if (! $this->intervalIsFree($slotStart, $slotEnd, $appointments) || ! $this->intervalIsFree($slotStart, $slotEnd, $timeOffs) || ! $this->intervalIsFreeRanges($slotStart, $slotEnd, $blockedIntervals)) {
                continue;
            }

            $available[] = [
                'start_at' => $slotStart->toIso8601String(),
                'service_end_at' => $serviceEnd->toIso8601String(),
                'end_at' => $slotEnd->toIso8601String(),
            ];
        }

        return $available;
    }

    private function intervalIsFree(CarbonImmutable $start, CarbonImmutable $end, array $intervals): bool
    {
        foreach ($intervals as $interval) {
            $intervalStart = CarbonImmutable::parse($interval->start_at);
            $intervalEnd = CarbonImmutable::parse($interval->end_at);

            if ($intervalStart->lessThan($end) && $intervalEnd->greaterThan($start)) {
                return false;
            }
        }

        return true;
    }

    private function intervalIsFreeRanges(CarbonImmutable $start, CarbonImmutable $end, array $intervals): bool
    {
        foreach ($intervals as [$intervalStart, $intervalEnd]) {
            if ($intervalStart->lessThan($end) && $intervalEnd->greaterThan($start)) {
                return false;
            }
        }

        return true;
    }
}
