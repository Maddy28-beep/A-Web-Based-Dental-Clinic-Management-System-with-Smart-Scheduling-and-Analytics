<?php

use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\AppointmentStatusLog;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Procedure;
use App\Models\ProcedurePrice;
use App\Models\Service;
use App\Models\User;
use App\Services\BillingService;
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

Artisan::command('pricing:seed-defaults {--reprice-existing=1}', function () {
    $resolveDefaults = function (string $procedureType): array {
        $t = strtolower(trim($procedureType));

        if (str_contains($t, 'consult')) {
            return ['base' => 5000, 'per_tooth' => 0];
        }
        if (str_contains($t, 'clean')) {
            return ['base' => 7000, 'per_tooth' => 0];
        }
        if (str_contains($t, 'fill')) {
            return ['base' => 10000, 'per_tooth' => 2000];
        }
        if (str_contains($t, 'extract')) {
            return ['base' => 8000, 'per_tooth' => 0];
        }
        if (str_contains($t, 'root') && str_contains($t, 'canal')) {
            return ['base' => 15000, 'per_tooth' => 0];
        }
        if (str_contains($t, 'brace')) {
            return ['base' => 20000, 'per_tooth' => 0];
        }
        if (str_contains($t, 'crown')) {
            return ['base' => 12000, 'per_tooth' => 0];
        }
        if (str_contains($t, 'whiten')) {
            return ['base' => 9000, 'per_tooth' => 0];
        }

        return ['base' => 5000, 'per_tooth' => 0];
    };

    $adminId = User::query()
        ->where('role', 'admin')
        ->orderBy('id')
        ->value('id');

    $billing = app(BillingService::class);

    $created = 0;

    $serviceTypes = Service::query()
        ->get(['name', 'duration_minutes'])
        ->map(fn (Service $s) => [
            'procedure_type' => strtolower(trim((string) $s->name)),
            'duration_minutes' => (int) $s->duration_minutes,
        ])
        ->filter(fn (array $row) => $row['procedure_type'] !== '')
        ->unique('procedure_type')
        ->values();

    $procedureTypes = Procedure::query()
        ->select('procedure_type')
        ->distinct()
        ->pluck('procedure_type')
        ->map(fn ($v) => strtolower(trim((string) $v)))
        ->filter()
        ->values();

    foreach ($serviceTypes as $row) {
        $type = (string) $row['procedure_type'];

        $exists = ProcedurePrice::query()
            ->where('procedure_type', $type)
            ->whereNull('dentist_id')
            ->where('is_active', true)
            ->exists();

        if ($exists) {
            continue;
        }

        $defaults = $resolveDefaults($type);
        ProcedurePrice::create([
            'procedure_type' => $type,
            'dentist_id' => null,
            'base_price_cents' => (int) $defaults['base'],
            'per_tooth_cents' => (int) $defaults['per_tooth'],
            'duration_minutes' => (int) ($row['duration_minutes'] ?: 0) ?: null,
            'is_active' => true,
            'created_by_user_id' => $adminId,
        ]);
        $created++;
    }

    foreach ($procedureTypes as $type) {
        $exists = ProcedurePrice::query()
            ->where('procedure_type', $type)
            ->whereNull('dentist_id')
            ->where('is_active', true)
            ->exists();

        if ($exists) {
            continue;
        }

        $defaults = $resolveDefaults($type);
        ProcedurePrice::create([
            'procedure_type' => $type,
            'dentist_id' => null,
            'base_price_cents' => (int) $defaults['base'],
            'per_tooth_cents' => (int) $defaults['per_tooth'],
            'duration_minutes' => null,
            'is_active' => true,
            'created_by_user_id' => $adminId,
        ]);
        $created++;
    }

    $repricedItems = 0;
    $recomputedBills = 0;

    if ((int) $this->option('reprice-existing') === 1) {
        $items = BillItem::query()
            ->whereNull('override_price_cents')
            ->where('total_cents', '<=', 0)
            ->orderBy('id')
            ->get();

        foreach ($items as $item) {
            $bill = Bill::query()->whereKey($item->bill_id)->first();
            if (! $bill) {
                continue;
            }

            $price = $billing->resolvePrice((string) $item->procedure_type, $bill->dentist_id);
            if (! $price) {
                continue;
            }

            $toothCount = max(1, (int) $item->tooth_count);
            $baseTotal = $billing->computeBaseTotalCents($price, $toothCount);
            $total = $billing->computeItemTotalCents($baseTotal, (int) $item->add_ons_cents, (int) $item->discount_cents, null);

            $item->update([
                'base_price_cents' => $baseTotal,
                'total_cents' => $total,
                'meta' => [
                    'price_id' => $price->id,
                    'dentist_id' => $price->dentist_id,
                    'per_tooth_cents' => $price->per_tooth_cents,
                ],
            ]);

            $billing->recomputeBillTotals($bill);
            $repricedItems++;
            $recomputedBills++;
        }
    }

    $this->info('Created '.$created.' default procedure price(s).');
    $this->info('Repriced '.$repricedItems.' bill item(s), recomputed '.$recomputedBills.' bill(s).');
})->purpose('Seed default procedure prices and optionally reprice zero-total bills');

Schedule::command('appointments:auto-advance')
    ->everyMinute()
    ->withoutOverlapping();
