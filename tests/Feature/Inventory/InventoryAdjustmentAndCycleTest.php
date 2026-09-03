<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\InventoryCycle;
use App\Models\ProductCount;
use App\Models\ProductDistribution;
use App\Models\InventoryMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryAdjustmentAndCycleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $cajero;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Sembrar los roles necesarios en la BD para cumplir con la restricción de clave foránea
        $this->seed(\Database\Seeders\RolesSeeder::class);

        // Crear usuario Administrador (role_id = 1) para supervisión y creación de ciclos
        $this->admin = User::create([
            'username' => 'admin_inventario',
            'role_id' => 1,
            'email' => 'admin_inv@farmacia.com',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);

        // Crear usuario Cajero (role_id = 2) para conteos físicos
        $this->cajero = User::create([
            'username' => 'cajero_inventario',
            'role_id' => 2,
            'email' => 'cajero_inv@farmacia.com',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);

        // Crear un producto con código de barras e IVA de prueba
        $this->product = Product::create([
            'name' => 'Amoxicilina 500mg',
            'unit_cost' => 2.00,
            'sale_price' => 3.50,
            'iva' => 0,
            'barcode' => '7501234567890',
        ]);
    }

    /**
     * Valida que un administrador pueda crear exitosamente un ciclo de inventario.
     */
    public function test_admin_can_create_inventory_cycle_successfully(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/inventory/cycle/create');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Nuevo ciclo de inventario creado y activado.',
            ]);

        $this->assertDatabaseHas('inventory_cycles', [
            'status' => 'active',
        ]);
    }

    /**
     * Valida que no se pueda crear un ciclo de inventario nuevo si ya hay uno activo.
     */
    public function test_cannot_create_cycle_if_already_active(): void
    {
        // Sembrar un ciclo activo manualmente
        InventoryCycle::create([
            'start_date' => now(),
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/inventory/cycle/create');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Ya existe un ciclo de inventario activo.',
            ]);
    }

    /**
     * Valida la obtención de información del ciclo de inventario activo actual.
     */
    public function test_can_get_active_cycle_status(): void
    {
        $cycle = InventoryCycle::create([
            'start_date' => now(),
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/inventory/cycle/active');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'has_active_cycle' => true,
                'data' => [
                    'id' => $cycle->id,
                    'status' => 'active',
                ],
            ]);
    }

    /**
     * Valida que un conteo físico sin discrepancia se apruebe automáticamente.
     */
    public function test_cajero_can_record_physical_count_without_discrepancy_approves_automatically(): void
    {
        // Activar ciclo
        $cycle = InventoryCycle::create([
            'start_date' => now(),
            'status' => 'active',
        ]);

        // Crear stock inicial (lote) de 10 unidades
        ProductLot::create([
            'product_id' => $this->product->id,
            'lot_number' => 'LOT-100',
            'expiration_date' => now()->addYear(),
            'quantity' => 10,
            'unit_cost' => 2.00,
        ]);

        // Ejecutar conteo físico que coincide con el sistema (10 unidades)
        $response = $this->actingAs($this->cajero, 'sanctum')
            ->postJson("/api/inventory/count/{$this->product->id}", [
                'counted_quantity' => 10,
                'system_quantity' => 10,
                'discrepancy' => 0,
                'barcode' => '7501234567890',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Conteo registrado y aprobado automáticamente (sin discrepancia).',
            ]);

        // Verificar en BD que el conteo esté aprobado y con discrepancia 0
        $this->assertDatabaseHas('product_counts', [
            'product_id' => $this->product->id,
            'cycle_id' => $cycle->id,
            'counted_quantity' => 10,
            'system_quantity' => 10,
            'discrepancy' => 0,
            'status' => 'approved',
        ]);

        // Verificar que se haya creado un movimiento de inventario de verificación (ajuste 0)
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $this->product->id,
            'movement_type' => 'verification',
            'quantity' => 0,
            'stock_before' => 10,
            'stock_after' => 10,
        ]);
    }

    /**
     * Valida que un conteo con discrepancia física quede en estado pendiente.
     */
    public function test_cajero_can_record_physical_count_with_discrepancy_remains_pending(): void
    {
        // Activar ciclo
        $cycle = InventoryCycle::create([
            'start_date' => now(),
            'status' => 'active',
        ]);

        // Crear stock inicial de 10 unidades
        ProductLot::create([
            'product_id' => $this->product->id,
            'lot_number' => 'LOT-100',
            'expiration_date' => now()->addYear(),
            'quantity' => 10,
            'unit_cost' => 2.00,
        ]);

        // Ejecutar conteo físico con discrepancia (contado 8, sistema 10, discrepancia -2)
        $response = $this->actingAs($this->cajero, 'sanctum')
            ->postJson("/api/inventory/count/{$this->product->id}", [
                'counted_quantity' => 8,
                'system_quantity' => 10,
                'discrepancy' => -2,
                'barcode' => '7501234567890',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Conteo registrado exitosamente.',
            ]);

        // Verificar que el estado en BD sea pendiente
        $this->assertDatabaseHas('product_counts', [
            'product_id' => $this->product->id,
            'cycle_id' => $cycle->id,
            'counted_quantity' => 8,
            'system_quantity' => 10,
            'discrepancy' => -2,
            'status' => 'pending',
        ]);
    }

    /**
     * Valida que no se permita registrar un conteo si el código de barras escaneado es incorrecto.
     */
    public function test_cannot_record_physical_count_with_incorrect_barcode(): void
    {
        // Activar ciclo
        InventoryCycle::create([
            'start_date' => now(),
            'status' => 'active',
        ]);

        // Enviar un código de barras incorrecto
        $response = $this->actingAs($this->cajero, 'sanctum')
            ->postJson("/api/inventory/count/{$this->product->id}", [
                'counted_quantity' => 10,
                'system_quantity' => 10,
                'discrepancy' => 0,
                'barcode' => 'CODIGO_INCORRECTO_999',
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'El código de barras no coincide con el producto seleccionado.',
            ]);
    }

    /**
     * Valida que el supervisor pueda aprobar un conteo pendiente actualizando la cantidad de un lote existente.
     */
    public function test_supervisor_can_approve_discrepancy_with_updated_lots(): void
    {
        // Activar ciclo
        $cycle = InventoryCycle::create([
            'start_date' => now(),
            'status' => 'active',
        ]);

        // Crear stock inicial (lote) de 10 unidades
        $lot = ProductLot::create([
            'product_id' => $this->product->id,
            'lot_number' => 'LOT-100',
            'expiration_date' => now()->addYear(),
            'quantity' => 10,
            'unit_cost' => 2.00,
        ]);

        // Registrar conteo pendiente (se contaron 7 unidades)
        $productCount = ProductCount::create([
            'product_id' => $this->product->id,
            'user_id' => $this->cajero->id,
            'cycle_id' => $cycle->id,
            'counted_quantity' => 7,
            'system_quantity' => 10,
            'discrepancy' => -3,
            'status' => 'pending',
        ]);

        // Procesar acción de aprobación por parte del supervisor actualizando el lote
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/inventory/count/{$productCount->id}/process", [
                'action' => 'approve',
                'updated_lots' => [
                    [
                        'id' => $lot->id,
                        'quantity' => 7,
                    ]
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => "Ajuste de inventario aplicado exitosamente a '{$this->product->name}'.",
            ]);

        // Validar que el lote físico en product_lots se haya actualizado a 7
        $lot->refresh();
        $this->assertEquals(7, $lot->quantity);

        // Validar que el conteo se marque como approved
        $productCount->refresh();
        $this->assertEquals('approved', $productCount->status);
        $this->assertEquals($this->admin->id, $productCount->supervisor_id);

        // Validar que se haya registrado la trazabilidad de distribución
        $this->assertDatabaseHas('product_distributions', [
            'product_count_id' => $productCount->id,
            'product_lot_id' => $lot->id,
            'quantity' => 7,
        ]);
    }

    /**
     * Valida que el supervisor pueda aprobar un conteo pendiente creando un nuevo lote.
     */
    public function test_supervisor_can_approve_discrepancy_with_new_lots(): void
    {
        // Activar ciclo
        $cycle = InventoryCycle::create([
            'start_date' => now(),
            'status' => 'active',
        ]);

        // Crear stock inicial (lote) de 10 unidades
        ProductLot::create([
            'product_id' => $this->product->id,
            'lot_number' => 'LOT-100',
            'expiration_date' => now()->addYear(),
            'quantity' => 10,
            'unit_cost' => 2.00,
        ]);

        // Registrar conteo pendiente (se contaron 12 unidades)
        $productCount = ProductCount::create([
            'product_id' => $this->product->id,
            'user_id' => $this->cajero->id,
            'cycle_id' => $cycle->id,
            'counted_quantity' => 12,
            'system_quantity' => 10,
            'discrepancy' => 2,
            'status' => 'pending',
        ]);

        // Procesar acción de aprobación por el supervisor creando un nuevo lote (ej. lote de sobrantes)
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/inventory/count/{$productCount->id}/process", [
                'action' => 'approve',
                'new_lots' => [
                    [
                        'lot_number' => 'LOT-NEW-EXCESS',
                        'expiration_date' => now()->addYear()->format('Y-m-d'),
                        'quantity' => 2,
                    ]
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Validar que se haya creado el nuevo lote con cantidad 2
        $this->assertDatabaseHas('product_lots', [
            'product_id' => $this->product->id,
            'lot_number' => 'LOT-NEW-EXCESS',
            'quantity' => 2,
        ]);

        // Validar que la cantidad total física del producto ahora sea 12 (10 del lote viejo + 2 del nuevo)
        $totalQuantity = ProductLot::where('product_id', $this->product->id)->sum('quantity');
        $this->assertEquals(12, $totalQuantity);

        // Validar distribución
        $newLot = ProductLot::where('lot_number', 'LOT-NEW-EXCESS')->first();
        $this->assertDatabaseHas('product_distributions', [
            'product_count_id' => $productCount->id,
            'product_lot_id' => $newLot->id,
            'quantity' => 2,
        ]);
    }

    /**
     * Valida que el supervisor pueda rechazar un conteo de discrepancia sin que se realicen ajustes.
     */
    public function test_supervisor_can_reject_discrepancy_count(): void
    {
        // Activar ciclo
        $cycle = InventoryCycle::create([
            'start_date' => now(),
            'status' => 'active',
        ]);

        // Crear stock inicial (lote) de 10 unidades
        $lot = ProductLot::create([
            'product_id' => $this->product->id,
            'lot_number' => 'LOT-100',
            'expiration_date' => now()->addYear(),
            'quantity' => 10,
            'unit_cost' => 2.00,
        ]);

        // Registrar conteo pendiente (se contaron 5 unidades)
        $productCount = ProductCount::create([
            'product_id' => $this->product->id,
            'user_id' => $this->cajero->id,
            'cycle_id' => $cycle->id,
            'counted_quantity' => 5,
            'system_quantity' => 10,
            'discrepancy' => -5,
            'status' => 'pending',
        ]);

        // Procesar acción de rechazo
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/inventory/count/{$productCount->id}/process", [
                'action' => 'reject',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => "Conteo rechazado. No se realizaron cambios en el inventario de '{$this->product->name}'.",
            ]);

        // Validar que el lote original se mantenga intacto en 10
        $lot->refresh();
        $this->assertEquals(10, $lot->quantity);

        // Validar que el conteo pase a 'rejected'
        $productCount->refresh();
        $this->assertEquals('rejected', $productCount->status);
    }

    /**
     * Valida que no se pueda cerrar un ciclo de inventario si existen conteos pendientes.
     */
    public function test_cannot_close_cycle_with_pending_counts(): void
    {
        // Activar ciclo
        $cycle = InventoryCycle::create([
            'start_date' => now(),
            'status' => 'active',
        ]);

        // Crear un conteo pendiente
        ProductCount::create([
            'product_id' => $this->product->id,
            'user_id' => $this->cajero->id,
            'cycle_id' => $cycle->id,
            'counted_quantity' => 8,
            'system_quantity' => 10,
            'discrepancy' => -2,
            'status' => 'pending',
        ]);

        // Intentar cerrar el ciclo
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/inventory/cycle/close');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'No se puede cerrar el ciclo. Aún existen conteos pendientes de aprobación o rechazo.',
            ]);

        // Validar que el ciclo siga activo
        $cycle->refresh();
        $this->assertEquals('active', $cycle->status);
    }

    /**
     * Valida el cierre exitoso del ciclo de inventario cuando no hay conteos pendientes.
     */
    public function test_can_close_cycle_when_no_pending_counts(): void
    {
        // Activar ciclo
        $cycle = InventoryCycle::create([
            'start_date' => now(),
            'status' => 'active',
        ]);

        // Crear un conteo y marcarlo como aprobado
        ProductCount::create([
            'product_id' => $this->product->id,
            'user_id' => $this->cajero->id,
            'cycle_id' => $cycle->id,
            'counted_quantity' => 10,
            'system_quantity' => 10,
            'discrepancy' => 0,
            'status' => 'approved',
        ]);

        // Cerrar el ciclo
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/inventory/cycle/close');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'El ciclo de inventario ha sido cerrado exitosamente.',
            ]);

        // Validar que el ciclo esté cerrado
        $cycle->refresh();
        $this->assertEquals('closed', $cycle->status);
        $this->assertNotNull($cycle->end_date);
    }
}
