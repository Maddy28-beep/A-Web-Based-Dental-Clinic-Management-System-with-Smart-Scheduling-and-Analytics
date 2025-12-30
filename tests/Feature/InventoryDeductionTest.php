<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\InventoryLog;
use App\Models\Patient;
use App\Models\ProcedureMaterial;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryDeductionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_auto_deducts_inventory_when_creating_a_procedure(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin.inventory@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $this->actingAsStaff($admin);
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $patient = Patient::create([
            'full_name' => 'Inventory Patient',
            'phone' => '09170000011',
        ]);

        $gloves = InventoryItem::create([
            'name' => 'Gloves (pairs)',
            'sku' => 'GLV-001',
            'unit' => 'pair',
            'current_stock' => '10.00',
            'min_stock' => '2.00',
            'cost_per_unit_cents' => 50,
            'preferred_supplier_id' => null,
            'supplier_sku' => null,
            'last_purchase_at' => null,
            'last_purchase_qty' => null,
            'is_active' => true,
            'meta' => null,
        ]);

        $anesthetic = InventoryItem::create([
            'name' => 'Anesthetic',
            'sku' => 'ANS-001',
            'unit' => 'ml',
            'current_stock' => '10.00',
            'min_stock' => '2.00',
            'cost_per_unit_cents' => 100,
            'preferred_supplier_id' => null,
            'supplier_sku' => null,
            'last_purchase_at' => null,
            'last_purchase_qty' => null,
            'is_active' => true,
            'meta' => null,
        ]);

        ProcedureMaterial::create([
            'procedure_type' => 'filling',
            'inventory_item_id' => $gloves->id,
            'quantity' => '2.00',
            'is_per_tooth' => false,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        ProcedureMaterial::create([
            'procedure_type' => 'filling',
            'inventory_item_id' => $anesthetic->id,
            'quantity' => '1.00',
            'is_per_tooth' => true,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $res = $this->postJson("/api/patients/{$patient->id}/procedures", [
            'visit_id' => null,
            'dentist_id' => null,
            'procedure_type' => 'filling',
            'description' => 'Test filling',
            'cost_cents' => 15000,
            'performed_at' => CarbonImmutable::now()->toDateTimeString(),
            'tooth_codes' => ['11', '12'],
        ])->assertStatus(201);

        $procedureId = (int) $res->json('data.id');
        $this->assertGreaterThan(0, $procedureId);

        $this->assertSame('8.00', (string) $gloves->fresh()->current_stock);
        $this->assertSame('8.00', (string) $anesthetic->fresh()->current_stock);

        $this->assertSame(2, InventoryLog::query()->where('procedure_id', $procedureId)->count());

        $glovesLog = InventoryLog::query()
            ->where('procedure_id', $procedureId)
            ->where('inventory_item_id', $gloves->id)
            ->first();

        $this->assertNotNull($glovesLog);
        $this->assertSame($patient->id, $glovesLog->patient_id);
        $this->assertSame('usage', $glovesLog->action);
        $this->assertSame('-2.00', (string) $glovesLog->quantity_change);
        $this->assertSame('10.00', (string) $glovesLog->stock_before);
        $this->assertSame('8.00', (string) $glovesLog->stock_after);
    }
}
