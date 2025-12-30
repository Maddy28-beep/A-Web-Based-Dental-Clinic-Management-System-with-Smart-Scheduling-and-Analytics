<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Dentist;
use App\Models\DentistWorkingHour;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_books_an_appointment_and_rejects_conflicts(): void
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
            'buffer_minutes' => 10,
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

        $pastPayload = $payload;
        $pastPayload['start_at'] = CarbonImmutable::now()->subMinute()->toDateTimeString();

        $this->postJson('/api/appointments', $pastPayload)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cannot book an appointment in the past.');

        $this->postJson('/api/appointments', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.dentist_id', $dentist->id);

        $this->postJson('/api/appointments', $payload)
            ->assertStatus(409)
            ->assertJsonPath('message', 'Time slot is already booked.');

        $payloadAt930 = $payload;
        $payloadAt930['start_at'] = $startAt->addMinutes(30)->toDateTimeString();

        $this->postJson('/api/appointments', $payloadAt930)
            ->assertStatus(409);
    }

    public function test_it_shows_daily_availability_excluding_booked_slots(): void
    {
        $day = CarbonImmutable::now()
            ->addWeek()
            ->startOfWeek(CarbonImmutable::MONDAY);

        $dentist = Dentist::create([
            'name' => 'Dr. B',
            'is_active' => true,
        ]);

        DentistWorkingHour::create([
            'dentist_id' => $dentist->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '11:00',
            'slot_minutes' => 30,
            'is_active' => true,
        ]);

        Appointment::create([
            'dentist_id' => $dentist->id,
            'patient_name' => 'Booked',
            'start_at' => $day->setTime(9, 0)->toDateTimeString(),
            'end_at' => $day->setTime(9, 30)->toDateTimeString(),
            'status' => 'booked',
            'source' => 'online',
        ]);

        $response = $this->getJson('/api/dentists/'.$dentist->id.'/availability?date='.$day->toDateString())
            ->assertStatus(200);

        $slots = $response->json('data.slots');

        $this->assertNotEmpty($slots);

        $slotStarts = array_map(fn (array $s) => $s['start_at'], $slots);

        $slotStartTimes = array_map(fn (string $iso) => CarbonImmutable::parse($iso)->format('H:i'), $slotStarts);

        $this->assertFalse(in_array('09:00', $slotStartTimes, true));
    }
}
