<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Patient;
use App\Models\ProcedureMaterial;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_manages_inventory_items_and_exposes_low_stock_and_monthly_report(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin.inventory.api@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $this->actingAsStaff($admin);
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $createRes = $this->postJson('/api/inventory/items', [
            'name' => 'Composite Resin',
            'unit' => 'unit',
            'current_stock' => 5,
            'min_stock' => 10,
            'cost_per_unit_cents' => 2500,
            'is_active' => true,
        ])->assertStatus(201);

        $itemId = (int) $createRes->json('data.id');
        $this->assertGreaterThan(0, $itemId);

        $this->getJson('/api/inventory/items?low_stock_only=1')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $itemId);

        $this->patchJson("/api/inventory/items/{$itemId}", [
            'min_stock' => 20,
        ])->assertStatus(200)->assertJsonPath('data.min_stock', '20.00');

        $this->postJson("/api/inventory/items/{$itemId}/restock", [
            'quantity_added' => 50,
            'purchased_at' => CarbonImmutable::now()->toDateTimeString(),
            'cost_per_unit_cents' => 2400,
        ])->assertStatus(201)->assertJsonPath('data.item.current_stock', '55.00');

        $this->postJson("/api/inventory/items/{$itemId}/adjust", [
            'quantity_change' => -5,
            'reason' => 'Expired stock',
            'adjusted_at' => CarbonImmutable::now()->toDateTimeString(),
        ])->assertStatus(201)->assertJsonPath('data.item.current_stock', '50.00');

        $patient = Patient::create([
            'full_name' => 'Inventory API Patient',
            'phone' => '09170000021',
        ]);

        ProcedureMaterial::create([
            'procedure_type' => 'filling',
            'inventory_item_id' => $itemId,
            'quantity' => '1.00',
            'is_per_tooth' => true,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $this->postJson("/api/patients/{$patient->id}/procedures", [
            'procedure_type' => 'filling',
            'description' => 'Test filling',
            'performed_at' => CarbonImmutable::now()->toDateTimeString(),
            'tooth_codes' => ['11', '12'],
        ])->assertStatus(201);

        $this->assertSame('48.00', (string) InventoryItem::findOrFail($itemId)->current_stock);

        $month = CarbonImmutable::now()->format('Y-m');
        $reportRes = $this->getJson("/api/inventory/reports/monthly?month={$month}")
            ->assertStatus(200);

        $this->assertSame($month, (string) $reportRes->json('data.month'));
        $this->assertNotEmpty($reportRes->json('data.per_item'));
    }
}
