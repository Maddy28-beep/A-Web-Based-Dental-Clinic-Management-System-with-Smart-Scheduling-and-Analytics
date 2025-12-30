<?php

namespace Tests\Feature;

use App\Models\Dentist;
use App\Models\DentistWorkingHour;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentConversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_converts_an_online_booking_into_a_patient_record(): void
    {
        $startAt = CarbonImmutable::now()
            ->addWeek()
            ->startOfWeek(CarbonImmutable::MONDAY)
            ->setTime(9, 0);

        $dentist = Dentist::create([
            'name' => 'Dr. A',
            'is_active' => true,
        ]);

        DentistWorkingHour::create([
            'dentist_id' => $dentist->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_minutes' => 30,
            'is_active' => true,
        ]);

        $service = Service::create([
            'name' => 'Cleaning',
            'duration_minutes' => 30,
            'buffer_minutes' => 0,
            'is_active' => true,
        ]);

        $payload = [
            'dentist_id' => $dentist->id,
            'service_id' => $service->id,
            'start_at' => $startAt->toDateTimeString(),
            'patient_name' => 'Juan Dela Cruz',
            'patient_email' => 'juan@example.com',
            'patient_phone' => '09171234567',
        ];

        $appointmentId = (int) $this->postJson('/api/appointments', $payload)
            ->assertStatus(201)
            ->json('data.id');

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAsStaff($admin);

        $this->postJson("/api/appointments/{$appointmentId}/convert-to-patient", [])
            ->assertOk()
            ->assertJsonPath('data.patient.full_name', 'Juan Dela Cruz')
            ->assertJsonPath('data.appointment.patient_id', fn ($v) => is_int($v));
    }

    public function test_it_links_an_appointment_to_an_existing_patient(): void
    {
        $startAt = CarbonImmutable::now()
            ->addWeek()
            ->startOfWeek(CarbonImmutable::MONDAY)
            ->setTime(9, 0);

        $patient = Patient::create([
            'full_name' => 'Existing Patient',
            'phone' => '09990000000',
        ]);

        $dentist = Dentist::create([
            'name' => 'Dr. A',
            'is_active' => true,
        ]);

        DentistWorkingHour::create([
            'dentist_id' => $dentist->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_minutes' => 30,
            'is_active' => true,
        ]);

        $service = Service::create([
            'name' => 'Cleaning',
            'duration_minutes' => 30,
            'buffer_minutes' => 0,
            'is_active' => true,
        ]);

        $payload = [
            'dentist_id' => $dentist->id,
            'service_id' => $service->id,
            'start_at' => $startAt->toDateTimeString(),
            'patient_name' => 'Temp Name',
            'patient_phone' => '09170000000',
        ];

        $appointmentId = (int) $this->postJson('/api/appointments', $payload)
            ->assertStatus(201)
            ->json('data.id');

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAsStaff($admin);

        $this->postJson("/api/appointments/{$appointmentId}/convert-to-patient", [
            'patient_id' => $patient->id,
            'sync_patient_contact' => false,
        ])->assertOk()
            ->assertJsonPath('data.patient.id', $patient->id)
            ->assertJsonPath('data.appointment.patient_id', $patient->id)
            ->assertJsonPath('data.appointment.patient_name', 'Temp Name');
    }
}
