<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Dentist;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Procedure;
use App\Models\ProcedurePrice;
use App\Models\ProcedureTooth;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_auto_creates_a_bill_when_a_procedure_is_created_via_api(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin.autobill@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $this->actingAsStaff($admin);
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $patient = Patient::create([
            'full_name' => 'Auto Bill Patient',
            'phone' => '09170000021',
        ]);

        ProcedurePrice::create([
            'procedure_type' => 'consultation',
            'dentist_id' => null,
            'base_price_cents' => 5000,
            'per_tooth_cents' => 0,
            'duration_minutes' => 15,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $procRes = $this->postJson("/api/patients/{$patient->id}/procedures", [
            'procedure_type' => 'consultation',
            'description' => 'Consult',
            'performed_at' => CarbonImmutable::now()->toDateTimeString(),
        ])->assertStatus(201);

        $procedureId = (int) $procRes->json('data.id');
        $this->assertGreaterThan(0, $procedureId);

        $billsRes = $this->getJson('/api/bills?limit=10')->assertStatus(200);
        $billId = (int) ($billsRes->json('data.0.id') ?? 0);
        $this->assertGreaterThan(0, $billId);

        $billShowRes = $this->getJson("/api/bills/{$billId}")->assertStatus(200);
        $billShowRes
            ->assertJsonPath('data.bill.patient_id', $patient->id)
            ->assertJsonPath('data.bill.total_cents', 5000)
            ->assertJsonPath('data.bill.items.0.procedure_id', $procedureId);
    }

    public function test_it_creates_bill_records_partial_and_full_payments_and_allows_refunds(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin.billing@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $this->actingAsStaff($admin);
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $patient = Patient::create([
            'full_name' => 'Billing Patient',
            'phone' => '09170000001',
        ]);

        $dentist = Dentist::create([
            'name' => 'Dr. Billing',
            'email' => 'dr.billing@example.com',
            'is_active' => true,
        ]);

        ProcedurePrice::create([
            'procedure_type' => 'filling',
            'dentist_id' => null,
            'base_price_cents' => 10000,
            'per_tooth_cents' => 2000,
            'duration_minutes' => 60,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $procedure = Procedure::create([
            'patient_id' => $patient->id,
            'visit_id' => null,
            'dentist_id' => $dentist->id,
            'procedure_type' => 'filling',
            'description' => 'Test filling',
            'cost_cents' => null,
            'performed_at' => CarbonImmutable::now()->subDay(),
            'created_by_user_id' => $admin->id,
            'meta' => null,
        ]);

        ProcedureTooth::insert([
            [
                'procedure_id' => $procedure->id,
                'tooth_code' => '11',
                'surfaces' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'procedure_id' => $procedure->id,
                'tooth_code' => '12',
                'surfaces' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $billRes = $this->postJson("/api/patients/{$patient->id}/bills", [
            'procedure_ids' => [$procedure->id],
            'add_ons_cents' => 500,
            'discount_cents' => 300,
            'lock' => false,
        ])->assertStatus(201);

        $billId = (int) $billRes->json('data.id');
        $this->assertGreaterThan(0, $billId);

        $billRes
            ->assertJsonPath('data.locked_at', null)
            ->assertJsonPath('data.status', 'unpaid')
            ->assertJsonPath('data.subtotal_cents', 14000)
            ->assertJsonPath('data.total_cents', 14200)
            ->assertJsonPath('data.paid_cents', 0)
            ->assertJsonPath('data.balance_cents', 14200);

        $payment1Res = $this->postJson("/api/bills/{$billId}/payments", [
            'method' => 'cash',
            'amount_cents' => 10000,
        ])->assertStatus(201);

        $payment1Id = (int) $payment1Res->json('data.payment.id');
        $this->assertGreaterThan(0, $payment1Id);

        $payment1Res
            ->assertJsonPath('data.bill.id', $billId)
            ->assertJsonPath('data.bill.status', 'partial')
            ->assertJsonPath('data.bill.paid_cents', 10000)
            ->assertJsonPath('data.bill.balance_cents', 4200)
            ->assertJsonPath('data.payment.amount_cents', 10000)
            ->assertJsonPath('data.payment.receipt.receipt_number', 1);

        $payment2Res = $this->postJson("/api/bills/{$billId}/payments", [
            'method' => 'cash',
            'amount_cents' => 5000,
        ])->assertStatus(201);

        $payment2Res
            ->assertJsonPath('data.bill.status', 'paid')
            ->assertJsonPath('data.bill.paid_cents', 14200)
            ->assertJsonPath('data.bill.balance_cents', 0)
            ->assertJsonPath('data.payment.amount_cents', 4200)
            ->assertJsonPath('data.payment.receipt.receipt_number', 2);

        $refundRes = $this->postJson("/api/payments/{$payment1Id}/refunds", [
            'amount_cents' => 5000,
            'reason' => 'Test refund',
        ])->assertStatus(201);

        $refundRes
            ->assertJsonPath('data.bill.id', $billId)
            ->assertJsonPath('data.bill.status', 'partial')
            ->assertJsonPath('data.bill.paid_cents', 9200)
            ->assertJsonPath('data.bill.balance_cents', 5000)
            ->assertJsonPath('data.refund.amount_cents', 5000);
    }

    public function test_it_prevents_double_billing_same_procedure(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin.billing2@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $this->actingAsStaff($admin);
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $patient = Patient::create([
            'full_name' => 'Billing Patient 2',
            'phone' => '09170000002',
        ]);

        $procedure = Procedure::create([
            'patient_id' => $patient->id,
            'visit_id' => null,
            'dentist_id' => null,
            'procedure_type' => 'consultation',
            'description' => 'Consult',
            'cost_cents' => 1000,
            'performed_at' => CarbonImmutable::now()->subDay(),
            'created_by_user_id' => $admin->id,
            'meta' => null,
        ]);

        $this->postJson("/api/patients/{$patient->id}/bills", [
            'procedure_ids' => [$procedure->id],
            'lock' => true,
        ])->assertStatus(201);

        $this->postJson("/api/patients/{$patient->id}/bills", [
            'procedure_ids' => [$procedure->id],
            'lock' => true,
        ])->assertStatus(409)->assertJsonPath('data.procedure_ids.0', $procedure->id);
    }

    public function test_payments_cannot_be_deleted(): void
    {
        $patient = Patient::create([
            'full_name' => 'Delete Payment Patient',
            'phone' => '09170000003',
        ]);

        $bill = Bill::create([
            'patient_id' => $patient->id,
            'visit_id' => null,
            'dentist_id' => null,
            'status' => 'unpaid',
            'currency' => 'PHP',
            'subtotal_cents' => 0,
            'add_ons_cents' => 0,
            'discount_cents' => 0,
            'total_cents' => 100,
            'paid_cents' => 0,
            'balance_cents' => 100,
            'locked_at' => null,
            'locked_by_user_id' => null,
            'due_at' => null,
            'meta' => null,
        ]);

        $payment = Payment::create([
            'bill_id' => $bill->id,
            'method' => 'cash',
            'amount_cents' => 100,
            'paid_at' => now(),
            'recorded_by_user_id' => null,
            'reference' => null,
            'notes' => null,
            'meta' => null,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Payments cannot be deleted. Use refunds instead.');
        $payment->delete();
    }
}
