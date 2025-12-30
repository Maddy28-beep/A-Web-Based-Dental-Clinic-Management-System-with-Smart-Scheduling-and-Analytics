<?php

namespace Tests\Feature;

use App\Models\Dentist;
use App\Models\DentistWorkingHour;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exposes_patient_lookup_and_allows_staff_check_in_and_status_flow(): void
    {
        $startAt = CarbonImmutable::now()
            ->addWeek()
            ->startOfWeek(CarbonImmutable::MONDAY)
            ->setTime(9, 0);

        $dentist = Dentist::create([
            'name' => 'Dr. Verify',
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
            'name' => 'Consultation',
            'duration_minutes' => 30,
            'buffer_minutes' => 0,
            'is_active' => true,
        ]);

        $bookingRes = $this->postJson('/api/appointments', [
            'dentist_id' => $dentist->id,
            'service_id' => $service->id,
            'start_at' => $startAt->toDateTimeString(),
            'patient_name' => 'Patient Verify',
            'patient_email' => 'verify@example.com',
            'patient_phone' => '09170000000',
        ])->assertStatus(201);

        $code = (string) $bookingRes->json('data.booking_reference_code');
        $this->assertNotSame('', $code);

        $this->getJson('/api/appointments/reference/'.$code)
            ->assertStatus(200)
            ->assertJsonPath('data.booking_reference_code', $code)
            ->assertJsonPath('data.status', 'booked');

        $staff = User::create([
            'name' => 'Receptionist Verify',
            'email' => 'receptionist.verify@example.com',
            'password' => 'password',
            'role' => 'receptionist',
        ]);

        $this->actingAsStaff($staff);
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $checkinRes = $this->postJson('/api/appointments/check-in', [
            'booking_reference_code' => $code,
        ])->assertStatus(200)
            ->assertJsonPath('data.status', 'checked_in');

        $appointmentId = (int) $checkinRes->json('data.id');
        $this->assertGreaterThan(0, $appointmentId);

        $this->assertDatabaseHas('appointment_checkins', [
            'appointment_id' => $appointmentId,
            'method' => 'reference_code',
        ]);

        $this->patchJson('/api/appointments/'.$appointmentId.'/status', [
            'status' => 'in_treatment',
        ])->assertStatus(200)->assertJsonPath('data.status', 'in_treatment');

        $this->patchJson('/api/appointments/'.$appointmentId.'/status', [
            'status' => 'completed',
        ])->assertStatus(200)->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseCount('appointment_checkins', 1);
    }
}
