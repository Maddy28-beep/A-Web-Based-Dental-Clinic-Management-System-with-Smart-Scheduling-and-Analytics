<?php

namespace Database\Seeders;

use App\Models\Allergy;
use App\Models\Appointment;
use App\Models\ClinicClosedHour;
use App\Models\ClinicClosure;
use App\Models\ClinicSetting;
use App\Models\Dentist;
use App\Models\DentistTimeOff;
use App\Models\DentistWorkingHour;
use App\Models\MedicalHistory;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Procedure;
use App\Models\ProcedureTooth;
use App\Models\Role;
use App\Models\Service;
use App\Models\ToothHistory;
use App\Models\User;
use App\Models\Visit;
use App\Services\ToothChartingService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $faker = fake();
        $now = CarbonImmutable::now();

        $roles = collect([
            ['slug' => 'admin', 'name' => 'Admin', 'is_active' => true],
            ['slug' => 'dentist', 'name' => 'Dentist', 'is_active' => true],
            ['slug' => 'receptionist', 'name' => 'Receptionist', 'is_active' => true],
            ['slug' => 'patient', 'name' => 'Patient', 'is_active' => true],
        ])->map(fn (array $r) => Role::updateOrCreate(['slug' => $r['slug']], $r))->keyBy('slug');

        $permissions = collect([
            ['key' => 'appointments.view', 'name' => 'View appointments'],
            ['key' => 'appointments.checkin', 'name' => 'Check in appointments'],
            ['key' => 'appointments.update_status', 'name' => 'Update appointment statuses'],
            ['key' => 'appointments.convert_to_patient', 'name' => 'Convert appointment to patient'],
            ['key' => 'patients.view', 'name' => 'View patients'],
            ['key' => 'patients.create', 'name' => 'Create patients'],
            ['key' => 'clinical.view', 'name' => 'View clinical records'],
            ['key' => 'clinical.edit', 'name' => 'Edit clinical records'],
            ['key' => 'inventory.view', 'name' => 'View inventory'],
            ['key' => 'inventory.manage', 'name' => 'Manage inventory'],
            ['key' => 'billing.view', 'name' => 'View billing'],
            ['key' => 'billing.create', 'name' => 'Create bills'],
            ['key' => 'billing.lock', 'name' => 'Lock bills'],
            ['key' => 'payments.record', 'name' => 'Record payments'],
            ['key' => 'refunds.create', 'name' => 'Create refunds'],
            ['key' => 'pricing.manage', 'name' => 'Manage pricing'],
            ['key' => 'catalog.manage', 'name' => 'Manage services and dentists'],
            ['key' => 'dentist.manage_leave_any', 'name' => 'Manage dentist leave (any)'],
            ['key' => 'dentist.manage_leave_self', 'name' => 'Manage own leave'],
            ['key' => 'analytics.view_widgets', 'name' => 'View analytics widgets'],
            ['key' => 'analytics.view_drilldowns', 'name' => 'View analytics drilldowns'],
            ['key' => 'analytics.choose_dentist', 'name' => 'Choose dentist filters'],
            ['key' => 'analytics.scope_self', 'name' => 'Scope analytics to own dentist'],
        ])->map(fn (array $p) => Permission::updateOrCreate(['key' => $p['key']], $p))->keyBy('key');

        $syncRolePerms = function (string $roleSlug, array $permissionKeys) use ($roles, $permissions): void {
            $role = $roles[$roleSlug] ?? null;
            if (! $role) {
                return;
            }

            $permIds = collect($permissionKeys)
                ->map(fn (string $k) => $permissions[$k]->id ?? null)
                ->filter()
                ->values()
                ->all();

            $role->permissions()->sync($permIds);
        };

        $allPermissionKeys = $permissions->keys()->values()->all();
        $adminKeys = array_values(array_diff($allPermissionKeys, ['analytics.scope_self']));
        $syncRolePerms('admin', $adminKeys);
        $syncRolePerms('dentist', [
            'appointments.view',
            'appointments.update_status',
            'patients.view',
            'clinical.view',
            'clinical.edit',
            'inventory.view',
            'billing.view',
            'billing.lock',
            'analytics.view_widgets',
            'analytics.view_drilldowns',
            'analytics.scope_self',
            'dentist.manage_leave_self',
        ]);
        $syncRolePerms('receptionist', [
            'appointments.view',
            'appointments.checkin',
            'appointments.update_status',
            'appointments.convert_to_patient',
            'patients.view',
            'patients.create',
            'inventory.view',
            'billing.view',
            'billing.create',
            'payments.record',
            'analytics.view_widgets',
            'analytics.choose_dentist',
            'dentist.manage_leave_any',
        ]);
        $syncRolePerms('patient', []);

        $admin = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
                'role' => 'admin',
            ],
        );
        $admin->update(['role' => 'admin']);

        $staff = User::firstOrCreate(
            ['email' => 'staff@example.com'],
            [
                'name' => 'Front Desk',
                'password' => 'password',
                'role' => 'receptionist',
            ],
        );
        $staff->update(['role' => 'receptionist']);

        $dentistUser = User::firstOrCreate(
            ['email' => 'dentist@example.com'],
            [
                'name' => 'Dentist User',
                'password' => 'password',
                'role' => 'dentist',
            ],
        );
        $dentistUser->update(['role' => 'dentist']);

        $services = collect([
            ['name' => 'Consultation', 'duration_minutes' => 30, 'buffer_minutes' => 0, 'is_active' => true, 'color' => '#2563EB'],
            ['name' => 'Cleaning', 'duration_minutes' => 60, 'buffer_minutes' => 0, 'is_active' => true, 'color' => '#10B981'],
            ['name' => 'Filling', 'duration_minutes' => 60, 'buffer_minutes' => 30, 'is_active' => true, 'color' => '#F59E0B'],
            ['name' => 'Extraction', 'duration_minutes' => 60, 'buffer_minutes' => 30, 'is_active' => true, 'color' => '#EF4444'],
            ['name' => 'Root Canal', 'duration_minutes' => 90, 'buffer_minutes' => 30, 'is_active' => true, 'color' => '#7C3AED'],
        ])->map(function (array $data) {
            return Service::updateOrCreate(['name' => $data['name']], $data);
        })->values();

        $dentists = collect([
            ['name' => 'Dr. Maria Santos', 'email' => 'maria.santos@example.com', 'phone' => '0917-111-2233', 'specialty' => 'General Dentistry', 'is_active' => true],
            ['name' => 'Dr. John Reyes', 'email' => 'john.reyes@example.com', 'phone' => '0917-222-3344', 'specialty' => 'Orthodontics', 'is_active' => true],
            ['name' => 'Dr. Anne Cruz', 'email' => 'anne.cruz@example.com', 'phone' => '0917-333-4455', 'specialty' => 'Endodontics', 'is_active' => true],
        ])->map(function (array $data) {
            return Dentist::updateOrCreate(['email' => $data['email']], $data);
        })->values();

        foreach ($dentists as $dentist) {
            foreach ([1, 2, 3, 4, 5] as $dayOfWeek) {
                DentistWorkingHour::updateOrCreate(
                    [
                        'dentist_id' => $dentist->id,
                        'day_of_week' => $dayOfWeek,
                    ],
                    [
                        'start_time' => '09:00',
                        'end_time' => '17:00',
                        'slot_minutes' => 30,
                        'is_active' => true,
                    ],
                );
            }
        }

        foreach (range(0, 6) as $dayOfWeek) {
            ClinicClosedHour::updateOrCreate(
                [
                    'day_of_week' => $dayOfWeek,
                    'start_time' => '00:00',
                    'end_time' => '08:59',
                ],
                [
                    'is_active' => true,
                ],
            );

            ClinicClosedHour::updateOrCreate(
                [
                    'day_of_week' => $dayOfWeek,
                    'start_time' => '17:00',
                    'end_time' => '23:59',
                ],
                [
                    'is_active' => true,
                ],
            );
        }

        ClinicSetting::updateOrCreate(
            ['key' => 'clinic_name'],
            ['value' => ['en' => 'Skye Dental']],
        );
        ClinicSetting::updateOrCreate(
            ['key' => 'timezone'],
            ['value' => ['value' => config('app.timezone')]],
        );

        $patients = collect(range(1, 12))->map(function () use ($faker) {
            return Patient::create([
                'full_name' => $faker->name(),
                'email' => $faker->unique()->safeEmail(),
                'phone' => $faker->numerify('09##-###-####'),
                'date_of_birth' => $faker->optional()->dateTimeBetween('-70 years', '-6 years')?->format('Y-m-d'),
            ]);
        })->values();

        foreach ($patients as $patient) {
            $conditions = $faker->randomElements(
                ['hypertension', 'diabetes', 'asthma', 'heart disease', 'bleeding disorder', 'epilepsy'],
                random_int(0, 3),
            );
            $meds = $faker->randomElements(
                ['metformin', 'amlodipine', 'salbutamol', 'warfarin', 'insulin', 'ibuprofen'],
                random_int(0, 2),
            );

            $data = [
                'conditions' => array_values($conditions),
                'medications' => array_values($meds),
                'smoker' => $faker->boolean(12),
                'pregnant' => $faker->boolean(3),
                'notes' => $faker->optional()->sentence(10),
            ];

            MedicalHistory::create([
                'patient_id' => $patient->id,
                'data' => $data,
                'recorded_at' => CarbonImmutable::instance($faker->dateTimeBetween('-2 years', '-5 days')),
                'created_by_user_id' => $admin->id,
            ]);
        }

        $charting = new ToothChartingService;

        foreach ($patients as $patient) {
            $teeth = $charting->ensureTeethExist($patient, 'adult');

            $toSeed = collect($teeth)->shuffle()->take(5)->values();
            foreach ($toSeed as $tooth) {
                $condition = $faker->randomElement(['healthy', 'needs_attention', 'cavity', 'sensitive', 'urgent', 'infection']);
                $procedure = $faker->randomElement([null, 'filling', 'crown', 'extraction', 'cleaning', 'root canal']);
                $recordedAt = CarbonImmutable::instance($faker->dateTimeBetween('-180 days', '-1 day'));

                $history = ToothHistory::create([
                    'tooth_id' => $tooth->id,
                    'condition' => $condition,
                    'procedure' => $procedure,
                    'notes' => $faker->optional()->sentence(),
                    'recorded_at' => $recordedAt,
                    'image_before_path' => null,
                    'image_after_path' => null,
                    'created_by_user_id' => $admin->id,
                    'meta' => null,
                ]);

                $tooth->update([
                    'condition' => $history->condition,
                    'procedure' => $history->procedure,
                    'notes' => $history->notes,
                    'severity' => $charting->severityFromCondition($history->condition),
                    'last_recorded_at' => $history->recorded_at,
                ]);
            }
        }

        foreach ($dentists as $dentist) {
            for ($date = $now->subDays(56)->startOfDay(); $date->lessThanOrEqualTo($now->subDay()->startOfDay()); $date = $date->addDay()) {
                if (in_array($date->dayOfWeek, [0, 6], true)) {
                    continue;
                }

                $cursor = $date->setTime(9, 0);
                $workEnd = $date->setTime(17, 0);
                $count = random_int(0, 4);

                for ($i = 0; $i < $count; $i++) {
                    $service = $services->random();
                    $reservedMinutes = (int) $service->duration_minutes + (int) $service->buffer_minutes;

                    if ($cursor->setTime(12, 0)->lessThanOrEqualTo($cursor) && $cursor->lessThan($date->setTime(13, 0))) {
                        $cursor = $date->setTime(13, 0);
                    }

                    $endAt = $cursor->addMinutes($reservedMinutes);
                    if ($endAt->greaterThan($workEnd)) {
                        break;
                    }

                    $patient = $patients->random();
                    $linkPatient = random_int(1, 100) <= 70;
                    $status = random_int(1, 100) <= 92 ? 'booked' : 'cancelled';

                    Appointment::create([
                        'patient_id' => $linkPatient ? $patient->id : null,
                        'dentist_id' => $dentist->id,
                        'service_id' => $service->id,
                        'service_duration_minutes' => (int) $service->duration_minutes,
                        'buffer_minutes' => (int) $service->buffer_minutes,
                        'is_override' => false,
                        'override_reason' => null,
                        'patient_name' => $linkPatient ? $patient->full_name : $faker->name(),
                        'patient_email' => $linkPatient ? $patient->email : $faker->optional()->safeEmail(),
                        'patient_phone' => $linkPatient ? $patient->phone : $faker->optional()->numerify('09##-###-####'),
                        'start_at' => $cursor,
                        'end_at' => $endAt,
                        'status' => $status,
                        'source' => random_int(1, 100) <= 70 ? 'online' : 'front_desk',
                        'notes' => $faker->optional()->sentence(),
                    ]);

                    $gap = random_int(0, 1) * 30;
                    $cursor = $cursor->addMinutes($reservedMinutes + $gap);
                }
            }

            for ($date = $now->startOfDay(); $date->lessThanOrEqualTo($now->addDays(10)->startOfDay()); $date = $date->addDay()) {
                if (in_array($date->dayOfWeek, [0, 6], true)) {
                    continue;
                }

                $service = $services->random();
                $reservedMinutes = (int) $service->duration_minutes + (int) $service->buffer_minutes;

                $startAt = $date->setTime(10, 0);
                $endAt = $startAt->addMinutes($reservedMinutes);
                if ($endAt->greaterThan($date->setTime(17, 0))) {
                    continue;
                }

                $patient = $patients->random();

                Appointment::create([
                    'patient_id' => $patient->id,
                    'dentist_id' => $dentist->id,
                    'service_id' => $service->id,
                    'service_duration_minutes' => (int) $service->duration_minutes,
                    'buffer_minutes' => (int) $service->buffer_minutes,
                    'is_override' => false,
                    'override_reason' => null,
                    'patient_name' => $patient->full_name,
                    'patient_email' => $patient->email,
                    'patient_phone' => $patient->phone,
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                    'status' => 'booked',
                    'source' => 'online',
                    'notes' => 'Seeded upcoming appointment.',
                ]);
            }
        }

        $allergyTags = ['penicillin', 'latex', 'anesthesia'];
        foreach ($patients as $patient) {
            $count = random_int(0, 2);
            $tags = collect($allergyTags)->shuffle()->take($count)->values();
            foreach ($tags as $tag) {
                Allergy::create([
                    'patient_id' => $patient->id,
                    'tag' => $tag,
                    'severity' => $faker->randomElement(['mild', 'moderate', 'severe']),
                    'is_active' => true,
                    'notes' => $faker->optional()->sentence(),
                    'recorded_at' => CarbonImmutable::instance($faker->dateTimeBetween('-5 years', '-1 month')),
                    'created_by_user_id' => $admin->id,
                    'updated_by_user_id' => $admin->id,
                ]);
            }

            $visitCount = random_int(1, 4);
            $patientVisits = collect(range(1, $visitCount))->map(function () use ($patient, $dentists, $faker, $now, $staff) {
                $start = CarbonImmutable::instance($faker->dateTimeBetween($now->subYear(), $now->subDay()))->startOfHour();

                return Visit::create([
                    'patient_id' => $patient->id,
                    'dentist_id' => $dentists->random()->id,
                    'start_at' => $start,
                    'end_at' => $start->addMinutes(60),
                    'notes' => $faker->optional()->sentence(),
                    'created_by_user_id' => $staff->id,
                ]);
            })->values();

            $teeth = $charting->ensureTeethExist($patient, 'adult');
            $toothCodes = collect($teeth)->pluck('tooth_code')->shuffle()->values();

            $procCount = random_int(1, 5);
            for ($i = 0; $i < $procCount; $i++) {
                $type = $faker->randomElement(['consultation', 'cleaning', 'filling', 'extraction', 'root canal']);
                $performedAt = CarbonImmutable::instance($faker->dateTimeBetween('-10 months', '-2 days'));
                $codes = $toothCodes->shuffle()->take(random_int(1, 2))->values();

                $requires = null;
                $conflicts = null;
                $patientAllergies = Allergy::query()->where('patient_id', $patient->id)->where('is_active', true)->pluck('tag')->map(fn ($v) => strtolower($v))->values();
                if (in_array($type, ['extraction', 'root canal'], true)) {
                    $requires = ['anesthesia', 'latex'];
                    $conflicts = collect($requires)->intersect($patientAllergies)->values()->all();
                } elseif (in_array($type, ['cleaning', 'filling'], true)) {
                    $requires = ['latex'];
                    $conflicts = collect($requires)->intersect($patientAllergies)->values()->all();
                }

                $meta = $type === 'cleaning'
                    ? ['follow_up_suggested_at' => $performedAt->addMonthsNoOverflow(6)->toDateString()]
                    : null;

                $procedure = Procedure::create([
                    'patient_id' => $patient->id,
                    'visit_id' => $patientVisits->random()->id,
                    'dentist_id' => $dentists->random()->id,
                    'procedure_type' => $type,
                    'description' => $faker->optional()->sentence(4),
                    'cost_cents' => random_int(500, 25000) * 100,
                    'performed_at' => $performedAt,
                    'requires_allergy_tags' => $requires,
                    'allergy_conflicts' => empty($conflicts) ? null : $conflicts,
                    'confirmed_by_user_id' => empty($conflicts) ? null : $dentistUser->id,
                    'confirmed_at' => empty($conflicts) ? null : $performedAt,
                    'created_by_user_id' => $staff->id,
                    'meta' => $meta,
                ]);

                $rows = $codes->map(function ($c) use ($procedure) {
                    return [
                        'procedure_id' => $procedure->id,
                        'tooth_code' => $c,
                        'surfaces' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                })->all();
                ProcedureTooth::insert($rows);
            }
        }

        $timeOffDentist = $dentists->first();
        if ($timeOffDentist) {
            $leaveDate = $now->next(CarbonImmutable::SATURDAY)->startOfDay();
            DentistTimeOff::create([
                'dentist_id' => $timeOffDentist->id,
                'start_at' => $leaveDate->setTime(9, 0),
                'end_at' => $leaveDate->setTime(11, 0),
                'reason' => 'Training',
            ]);
        }

        ClinicClosure::create([
            'start_at' => $now->subDays(2)->setTime(18, 0),
            'end_at' => $now->subDays(2)->setTime(19, 0),
            'reason' => 'Maintenance',
            'created_by_user_id' => $admin->id,
        ]);
    }
}
