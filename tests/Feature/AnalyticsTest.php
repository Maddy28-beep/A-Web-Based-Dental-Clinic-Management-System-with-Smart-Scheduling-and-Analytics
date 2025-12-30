<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Bill;
use App\Models\Dentist;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Procedure;
use App\Models\Receipt;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_assistant_can_view_widgets_but_cannot_access_drilldowns(): void
    {
        $staff = User::create([
            'name' => 'Receptionist',
            'email' => 'receptionist@example.com',
            'password' => 'password',
            'role' => 'receptionist',
        ]);

        $this->actingAsStaff($staff);

        $this->getJson('/api/analytics/summary')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_patients',
                    'appointments_in_range',
                    'revenue_cents',
                    'returning_patient_rate',
                    'range' => ['from', 'to'],
                ],
            ]);

        $this->getJson('/api/analytics/procedures/top')->assertStatus(200);
        $this->getJson('/api/analytics/procedures/types')->assertStatus(200);
        $this->getJson('/api/analytics/appointments/peak-days')->assertStatus(200);
        $this->getJson('/api/analytics/revenue/monthly')->assertStatus(200);
        $this->getJson('/api/analytics/patients/retention')->assertStatus(200);

        $this->getJson('/api/analytics/procedures/cleaning/patients')->assertStatus(403);
        $this->getJson('/api/analytics/peak-days/1/appointments')->assertStatus(403);
        $this->getJson('/api/analytics/revenue/2025-01/receipts')->assertStatus(403);
    }

    public function test_dentist_role_is_scoped_to_matching_dentist_record(): void
    {
        $dentistA = Dentist::create([
            'name' => 'Dr. A',
            'email' => 'dr.a@example.com',
            'is_active' => true,
        ]);

        $dentistB = Dentist::create([
            'name' => 'Dr. B',
            'email' => 'dr.b@example.com',
            'is_active' => true,
        ]);

        $dentistUser = User::create([
            'name' => 'Dr. A',
            'email' => 'dr.a@example.com',
            'password' => 'password',
            'role' => 'dentist',
        ]);

        $patientA = Patient::create([
            'full_name' => 'Patient A',
            'email' => 'patient.a@example.com',
        ]);

        $patientB = Patient::create([
            'full_name' => 'Patient B',
            'email' => 'patient.b@example.com',
        ]);

        Procedure::create([
            'patient_id' => $patientA->id,
            'dentist_id' => $dentistA->id,
            'procedure_type' => 'cleaning',
            'performed_at' => '2025-01-10 09:00:00',
        ]);
        Procedure::create([
            'patient_id' => $patientA->id,
            'dentist_id' => $dentistA->id,
            'procedure_type' => 'cleaning',
            'performed_at' => '2025-01-11 09:00:00',
        ]);
        Procedure::create([
            'patient_id' => $patientA->id,
            'dentist_id' => $dentistA->id,
            'procedure_type' => 'filling',
            'performed_at' => '2025-01-12 09:00:00',
        ]);

        Procedure::create([
            'patient_id' => $patientB->id,
            'dentist_id' => $dentistB->id,
            'procedure_type' => 'cleaning',
            'performed_at' => '2025-01-13 09:00:00',
        ]);

        $this->actingAsStaff($dentistUser);

        $this->getJson('/api/analytics/procedures/top?from=2025-01-01&to=2025-01-31')
            ->assertStatus(200)
            ->assertJsonPath('data.total', 3);

        $patientsRes = $this->getJson('/api/analytics/procedures/cleaning/patients?from=2025-01-01&to=2025-01-31&dentist_id='.$dentistB->id)
            ->assertStatus(200);

        $patients = $patientsRes->json('data.patients');
        $this->assertIsArray($patients);
        $this->assertCount(1, $patients);
        $this->assertSame($patientA->full_name, $patients[0]['patient_name']);
    }

    public function test_admin_can_access_drilldowns(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $dentist = Dentist::create([
            'name' => 'Dr. Drill',
            'email' => 'dr.drill@example.com',
            'is_active' => true,
        ]);

        $service = Service::create([
            'name' => 'Consultation',
            'duration_minutes' => 30,
            'buffer_minutes' => 0,
            'is_active' => true,
        ]);

        $patient = Patient::create([
            'full_name' => 'Patient Drill',
            'email' => 'patient.drill@example.com',
        ]);

        Appointment::create([
            'dentist_id' => $dentist->id,
            'service_id' => $service->id,
            'service_duration_minutes' => 30,
            'buffer_minutes' => 0,
            'patient_name' => $patient->full_name,
            'patient_email' => $patient->email,
            'start_at' => '2025-01-06 09:00:00',
            'end_at' => '2025-01-06 09:30:00',
            'status' => 'booked',
            'source' => 'online',
        ]);

        Procedure::create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'procedure_type' => 'cleaning',
            'performed_at' => '2025-01-06 10:00:00',
        ]);

        $bill = Bill::create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'status' => 'paid',
            'currency' => 'PHP',
            'total_cents' => 10000,
            'paid_cents' => 10000,
            'balance_cents' => 0,
        ]);

        $payment = Payment::create([
            'bill_id' => $bill->id,
            'method' => 'cash',
            'amount_cents' => 10000,
            'paid_at' => '2025-01-15 11:00:00',
            'recorded_by_user_id' => $admin->id,
        ]);

        Receipt::create([
            'payment_id' => $payment->id,
            'receipt_number' => 123,
            'issued_at' => '2025-01-15 11:00:00',
            'issued_by_user_id' => $admin->id,
        ]);

        $this->actingAsStaff($admin);

        $this->getJson('/api/analytics/procedures/cleaning/patients?from=2025-01-01&to=2025-01-31')
            ->assertStatus(200)
            ->assertJsonPath('data.patients.0.patient_name', $patient->full_name);

        $this->getJson('/api/analytics/peak-days/1/appointments?from=2025-01-01&to=2025-01-31')
            ->assertStatus(200)
            ->assertJsonPath('data.appointments.0.patient_name', $patient->full_name);

        $this->getJson('/api/analytics/revenue/2025-01/receipts')
            ->assertStatus(200)
            ->assertJsonPath('data.receipts.0.receipt_number', 123);
    }
}
