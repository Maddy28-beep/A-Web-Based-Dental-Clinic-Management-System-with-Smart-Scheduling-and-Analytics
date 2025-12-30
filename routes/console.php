<?php

use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\AppointmentStatusLog;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('appointments:auto-advance', function () {
    $now = CarbonImmutable::now();

    $updated = DB::transaction(function () use ($now) {
        $appointments = Appointment::query()
            ->where('status', 'checked_in')
            ->whereNotNull('checked_in_at')
            ->whereNull('in_treatment_at')
            ->where('start_at', '<=', $now)
            ->lockForUpdate()
            ->limit(200)
            ->get();

        foreach ($appointments as $appt) {
            $from = (string) $appt->status;
            $to = 'in_treatment';

            $appt->update([
                'status' => $to,
                'in_treatment_at' => $appt->in_treatment_at ?? $now,
            ]);

            AppointmentStatusLog::create([
                'appointment_id' => $appt->id,
                'from_status' => $from,
                'to_status' => $to,
                'changed_by_user_id' => null,
                'changed_at' => $now,
                'meta' => [
                    'method' => 'auto',
                    'rule' => 'time_reached_and_checked_in',
                ],
            ]);

            ActivityLog::create([
                'actor_user_id' => null,
                'patient_id' => $appt->patient_id,
                'action' => 'appointment.auto_in_treatment',
                'subject_type' => Appointment::class,
                'subject_id' => $appt->id,
                'ip_address' => null,
                'user_agent' => null,
                'meta' => [
                    'from' => $from,
                    'to' => $to,
                    'start_at' => $appt->start_at?->toIso8601String(),
                    'checked_in_at' => $appt->checked_in_at?->toIso8601String(),
                ],
                'created_at' => now(),
            ]);
        }

        return $appointments->count();
    });

    $this->info('Auto-advanced '.$updated.' appointment(s) to in_treatment.');
})->purpose('Auto-advance appointment statuses based on time and check-in');

Schedule::command('appointments:auto-advance')
    ->everyMinute()
    ->withoutOverlapping();
