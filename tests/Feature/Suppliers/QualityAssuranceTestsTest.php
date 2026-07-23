<?php

namespace Tests\Feature\Suppliers;

use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\User;
use App\Models\AutoOrder;
use App\Models\AutoOrderDetail;
use App\Services\Suppliers\SupplierQueryService;
use App\Repositories\AutoOrderDetailsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QualityAssuranceTestsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Supplier $supplier;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        // En SQLite, eliminamos el índice único uniq_product_supplier para poder simular duplicados reales
        if (DB::getDriverName() === 'sqlite') {
            DB::statement("DROP INDEX IF EXISTS uniq_product_supplier");
        }

        $this->user = User::create([
            'username' => 'testadmin',
            'email' => 'admin@test.com',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'Proveedor QA',
            'rif' => 'J-12345678-9',
            'address' => 'QA Addr',
            'phone' => '1234567',
            'dispatch_days' => [],
            'order_days' => [],
        ]);

        $this->product = Product::create([
            'name' => 'Producto QA 1',
            'unit_cost' => 10.00,
            'sale_price' => 15.00,
        ]);
    }

    /**
     * Prueba que la importación de ofertas NO elimine registros antiguos de product_suppliers
     * que estén vinculados a cualquier auto-orden (incluidas las completadas/históricas)
     * para evitar eliminaciones en cascada.
     */
    public function test_connection_import_does_not_delete_historical_offers_linked_to_any_auto_order(): void
    {
        // 1. Crear una oferta antigua
        $oldOffer = ProductSupplier::create([
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'QA Producto Old',
            'unit_cost_usd' => 8.00,
            'unit_cost' => 8.00,
            'connection_date' => now()->subMonth(),
        ]);

        // 2. Crear una autoorden completada y vincular el detalle a la oferta antigua
        $autoOrder = AutoOrder::create([
            'supplier_id' => $this->supplier->id,
            'order_date' => now()->subMonth(),
            'total_items' => 1,
            'total_quantity' => 5,
            'total_amount' => 40.00,
            'status' => 2, // COMPLETED
        ]);

        AutoOrderDetail::create([
            'order_id' => $autoOrder->id,
            'product_id' => $this->product->id,
            'product_suppliers_id' => $oldOffer->id,
            'quantity' => 5,
            'unit_cost' => 8.00,
            'subtotal' => 40.00,
        ]);

        // 3. Ejecutar la importación (usando el servicio directamente)
        $service = app(SupplierQueryService::class);
        $service->storeSupplierConnectionData($this->supplier, [
            'products' => [
                [
                    'supplier_id' => $this->supplier->id,
                    'product_id' => $this->product->id,
                    'barcode_match' => '123456789',
                    'name' => 'QA Producto New',
                    'unit_cost_usd' => 9.00,
                    'unit_cost' => 9.00,
                    'connection_date' => now()->toDateString(),
                ]
            ],
            'invoices' => []
        ]);

        // 4. Verificar que la oferta antigua NO fue eliminada de la base de datos
        $this->assertDatabaseHas('product_suppliers', [
            'id' => $oldOffer->id,
        ]);
    }

    /**
     * Prueba que las oportunidades de mercado solo consideren la oferta más reciente
     * importada por proveedor y producto, ignorando duplicados desactualizados.
     */
    public function test_market_opportunities_only_considers_latest_offer_per_supplier_and_product(): void
    {
        // 1. Crear oferta antigua (más barata pero desactualizada)
        ProductSupplier::create([
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'QA Producto Old',
            'unit_cost_usd' => 3.00, // Precio antiguo muy bajo
            'unit_cost' => 3.00,
            'connection_date' => now()->subMonth(),
        ]);

        // 2. Crear oferta nueva (más cara pero activa de hoy)
        ProductSupplier::create([
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'QA Producto New',
            'unit_cost_usd' => 8.00, // Precio actual real
            'unit_cost' => 8.00,
            'connection_date' => now(),
        ]);

        // 3. Consultar el endpoint de oportunidades de mercado
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/market-opportunities');

        $response->assertStatus(200);

        // 4. Validar que la oportunidad devuelta tome el precio nuevo (8.00) y no el antiguo (3.00)
        $data = $response->json('data');
        $opportunity = collect($data)->firstWhere('product_id', $this->product->id);

        $this->assertNotNull($opportunity);
        $this->assertEquals(8.00, $opportunity['unit_cost_usd']);
    }

    /**
     * Prueba que el parámetro perPage de paginación de detalles de auto-orden
     * sea acotado y limitado correctamente para evitar cargas de base de datos masivas.
     */
    public function test_auto_order_details_pagination_clamps_per_page(): void
    {
        // Crear una autoorden
        $autoOrder = AutoOrder::create([
            'supplier_id' => $this->supplier->id,
            'order_date' => now(),
            'total_items' => 0,
            'total_quantity' => 0,
            'total_amount' => 0.00,
            'status' => 0,
        ]);

        // Consultar el repositorio directamente con un perPage exageradamente alto
        $repo = app(AutoOrderDetailsRepository::class);
        $paginated = $repo->getPurchaseOrderDetails([
            'id' => $autoOrder->id,
            'perPage' => 999
        ]);

        // Validar que el límite perPage del paginador se acotó a 100
        $this->assertEquals(100, $paginated->perPage());
    }
}
