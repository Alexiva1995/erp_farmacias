<?php

declare(strict_types=1);

namespace Tests\Feature\Bi;

use App\Models\Laboratory;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventorySnapshotManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Laboratory $laboratory;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['id' => 1], [
            'name' => 'Admin',
            'guard_name' => 'web',
            'menu_permissions' => ['*'],
        ]);

        $this->user = User::factory()->create(['role_id' => $role->id]);
        $this->actingAs($this->user, 'sanctum');

        $this->laboratory = Laboratory::create(['name' => 'Laboratorios Vivax']);
    }

    public function test_can_create_list_show_audit_and_delete_inventory_snapshot(): void
    {
        $product = Product::create([
            'name' => 'Metformina 850mg',
            'laboratory_id' => $this->laboratory->id,
            'unit_cost' => 2.00,
            'sale_price' => 3.50,
            'stock' => 100,
        ]);

        $order = Order::create([
            'order_date' => Carbon::now()->subDays(2)->format('Y-m-d'),
            'status' => Order::COMPLETED,
            'total_amount' => 35.00,
            'total_amount_usd' => 35.00,
            'money_returns' => 0.00,
            'currency' => 'USD',
        ]);

        OrderDetail::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_type' => 'product',
            'quantity' => 10,
            'price' => 3.50,
            'unit_cost' => 2.00,
            'unit_price_usd' => 3.50,
        ]);

        // 1. Generar Foto Finish (Snapshot)
        $cutoffDate = Carbon::now()->format('Y-m-d');
        $storeResponse = $this->postJson('/api/bi/snapshots', [
            'cutoff_date' => $cutoffDate,
            'period_days' => 30,
            'name' => 'Cierre Estratégico Mensual',
        ]);

        $storeResponse->assertCreated()
            ->assertJsonPath('message', 'Foto Finish generada exitosamente.')
            ->assertJsonPath('data.name', 'Cierre Estratégico Mensual');

        $snapshotId = (int) $storeResponse->json('data.id');

        // 2. Listar snapshots
        $indexResponse = $this->getJson('/api/bi/snapshots');
        $indexResponse->assertOk();
        $this->assertNotEmpty($indexResponse->json('data'));

        // 3. Obtener detalle de snapshot e ítems
        $showResponse = $this->getJson("/api/bi/snapshots/{$snapshotId}");
        $showResponse->assertOk()
            ->assertJsonPath('snapshot.id', $snapshotId);
        $this->assertNotEmpty($showResponse->json('items'));

        // 4. Obtener auditoría de los 4 módulos estratégicos
        $auditResponse = $this->getJson("/api/bi/snapshots/{$snapshotId}/audit");
        $auditResponse->assertOk()
            ->assertJsonPath('snapshot.id', $snapshotId);

        $auditJson = $auditResponse->json();
        $this->assertArrayHasKey('module_1_summary', $auditJson);
        $this->assertArrayHasKey('module_2_cz_recovery', $auditJson);
        $this->assertArrayHasKey('module_3_ab_restock', $auditJson);
        $this->assertArrayHasKey('module_4_margin_alerts', $auditJson);

        // 5. Eliminar snapshot
        $deleteResponse = $this->deleteJson("/api/bi/snapshots/{$snapshotId}");
        $deleteResponse->assertOk();

        $this->assertDatabaseMissing('inventory_snapshots', ['id' => $snapshotId]);
    }
}
