<?php

namespace Tests\Unit\Services;

use App\Models\Category;
use App\Models\Employee;
use App\Models\GeneralSetting;
use App\Models\InventoryCycle;
use App\Models\InvoiceCount;
use App\Models\Laboratory;
use App\Models\Origin;
use App\Models\Product;
use App\Models\ProductCount;
use App\Models\ProductLot;
use App\Models\Role;
use App\Models\SaleCount;
use App\Models\User;
use App\Services\InventoryCycle\InventoryCycleActionService;
use App\Services\InventoryCycle\InventoryCycleQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InventoryCycleServicesTest extends TestCase
{
    use RefreshDatabase;

    private InventoryCycleActionService $actionService;
    private InventoryCycleQueryService $queryService;

    protected function setUp(): void
    {
        parent::setUp();

        // Instanciamos ambos servicios reales para probar ciclo completo y consultas
        $this->actionService = app(InventoryCycleActionService::class);
        $this->queryService = app(InventoryCycleQueryService::class);
    }

    /**
     * Prepara usuarios (cajero contador y supervisor), empleado activo y producto con lote.
     */
    private function seedCycleEnvironment(): array
    {
        GeneralSetting::create([
            'company_name' => 'Farmacia Test C.A.',
            'company_rif' => 'J-12345678-9',
            'company_address' => 'Av. Principal #10',
            'company_phone' => '04141234567',
            'iva_percentage' => 16.00,
        ]);

        $adminRole = Role::create([
            'name' => 'admin',
            'display_name' => 'Administrador',
        ]);

        $counterRole = Role::create([
            'name' => 'employee',
            'display_name' => 'Empleado',
        ]);

        $counterUser = User::create([
            'username' => 'contador_uno',
            'email' => 'contador_uno@example.com',
            'password_hash' => Hash::make('password'),
            'role_id' => $counterRole->id,
            'is_active' => true,
        ]);

        $supervisorUser = User::create([
            'username' => 'supervisor_uno',
            'email' => 'supervisor_uno@example.com',
            'password_hash' => Hash::make('password'),
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        Employee::create([
            'user_id' => $counterUser->id,
            'name' => 'Carlos',
            'last_name' => 'Mendoza',
            'identification' => 'V-19111222',
            'is_active' => true,
            'total_package_usd' => 240.00,
        ]);

        $category = Category::create(['name' => 'Antibióticos']);
        $laboratory = Laboratory::create(['name' => 'Lab Vida']);
        $origin = Origin::create(['name' => 'Importado']);

        $product = Product::create([
            'name' => 'Amoxicilina 500mg x 12',
            'barcode' => '7595556667778',
            'unit_cost' => 3.50,
            'sale_price' => 6.00,
            'units_per_inner_pack' => 1,
            'stock' => 20,
            'category_id' => $category->id,
            'laboratory_id' => $laboratory->id,
            'origin_id' => $origin->id,
            'is_deleted' => false,
        ]);

        $lot = ProductLot::create([
            'product_id' => $product->id,
            'lot_number' => 'LOT-AMX-01',
            'quantity' => 20,
            'unit_cost' => 3.50,
            'expiration_date' => now()->addMonths(10)->toDateString(),
            'entry_date' => now()->toDateString(),
        ]);

        return compact('counterUser', 'supervisorUser', 'product', 'lot', 'category');
    }

    public function test_inventory_cycle_full_lifecycle_counts_approvals_exemptions_and_closure(): void
    {
        $env = $this->seedCycleEnvironment();
        $product = $env['product'];
        $lot = $env['lot'];

        $this->actingAs($env['supervisorUser']);
        $this->assertFalse($this->actionService->hasActiveCycle());

        // Crear ciclo activo y validar sus consultores
        $cycleCreated = $this->actionService->createNewCycle();
        $this->assertTrue($cycleCreated['success']);
        $this->assertTrue($this->actionService->hasActiveCycle());

        $activeCycle = $this->actionService->getActiveCycle();
        $this->assertInstanceOf(InventoryCycle::class, $activeCycle);
        $this->assertNotNull($this->actionService->getActiveCycleInfo());

        // Verificar cálculo de stock por lotes
        $this->assertSame(20, $this->actionService->calculateCurrentStock($product));

        // 1. Conteo exacto ejecutado por contador_uno -> auto-aprobación
        $this->actingAs($env['counterUser']);
        $exactResult = $this->actionService->createProductCount($product->id, [
            'system_quantity' => 20,
            'counted_quantity' => 20,
            'discrepancy' => 0,
            'barcode' => $product->barcode,
            'lots_breakdown' => [
                ['id' => $lot->id, 'quantity' => 20],
            ],
        ]);
        $this->assertTrue($exactResult['success']);

        // 2. Conteo con discrepancia (-2) -> queda pendiente para revisión del supervisor
        $pendingResult = $this->actionService->createProductCount($product->id, [
            'system_quantity' => 20,
            'counted_quantity' => 18,
            'discrepancy' => -2,
            'barcode' => $product->barcode,
            'lots_breakdown' => [
                ['id' => $lot->id, 'quantity' => 18],
            ],
        ]);
        $this->assertTrue($pendingResult['success']);
        $pendingCount = ProductCount::where('status', 'pending')->latest('id')->first();
        $this->assertNotNull($pendingCount);

        // No debe permitir cerrar ciclo mientras existan conteos pendientes sin forzar rechazo
        $this->actingAs($env['supervisorUser']);
        $blockedClosure = $this->actionService->closeActiveCycle(false);
        $this->assertFalse($blockedClosure['success']);

        // Actualizar discrepancia y aplicar exoneración parcial
        $discrepancyUpdate = $this->actionService->updateDiscrepancy($pendingCount, -3);
        $this->assertTrue($discrepancyUpdate['success']);

        $exemptionUpdate = $this->actionService->updateExemption($pendingCount->fresh(), 1, 'Rotura autorizada');
        $this->assertTrue($exemptionUpdate['success']);

        // Aprobar conteo ajustando lote
        $approveResult = $this->actionService->processAction($pendingCount->fresh(), 'approve', [
            'updated_lots' => [
                ['id' => $lot->id, 'quantity' => 18],
            ],
        ]);
        $this->assertTrue($approveResult['success']);

        // 3. Crear otro conteo pendiente y probar corrección en rechazo
        $this->actingAs($env['counterUser']);
        $this->actionService->createProductCount($product->id, [
            'system_quantity' => 18,
            'counted_quantity' => 15,
            'discrepancy' => -3,
            'barcode' => $product->barcode,
            'lots_breakdown' => [
                ['id' => $lot->id, 'quantity' => 15],
            ],
        ]);
        $countToCorrect = ProductCount::where('status', 'pending')->latest('id')->first();

        $this->actingAs($env['supervisorUser']);
        $rejectWithCorrection = $this->actionService->processAction($countToCorrect, 'reject', [
            'corrected_quantity' => 18,
        ]);
        $this->assertTrue($rejectWithCorrection['success']);

        // 4. Conteos de factura y de venta
        $this->actingAs($env['counterUser']);
        $invCountRes = $this->actionService->createInvoiceCount($product->id, [
            'system_quantity' => 18,
            'counted_quantity' => 16,
            'discrepancy' => -2,
            'barcode' => $product->barcode,
            'type' => 'invoice',
        ]);
        $this->assertTrue($invCountRes['success'], $invCountRes['message'] ?? '');

        $invoiceCount = InvoiceCount::latest('id')->first();
        $this->actingAs($env['supervisorUser']);
        $invActionRes = $this->actionService->processInvoiceCountAction($invoiceCount, 'reject');
        $this->assertTrue($invActionRes['success']);

        $this->actingAs($env['counterUser']);
        $saleCountRes = $this->actionService->createSaleCount($product->id, [
            'system_quantity' => 18,
            'counted_quantity' => 17,
            'discrepancy' => -1,
            'barcode' => $product->barcode,
            'type' => 'sale',
        ]);
        $this->assertTrue($saleCountRes['success'], $saleCountRes['message'] ?? '');

        $saleCount = SaleCount::latest('id')->first();
        $this->actingAs($env['supervisorUser']);
        $saleActionRes = $this->actionService->processSaleCountAction($saleCount, 'reject');
        $this->assertTrue($saleActionRes['success']);

        // Crear un pendiente final para verificar cierre forzado rechazando pendientes
        $this->actingAs($env['counterUser']);
        $this->actionService->createProductCount($product->id, [
            'system_quantity' => 18,
            'counted_quantity' => 14,
            'discrepancy' => -4,
            'barcode' => $product->barcode,
            'lots_breakdown' => [
                ['id' => $lot->id, 'quantity' => 14],
            ],
        ]);

        // Consultas del servicio de lectura con supervisor autenticado
        $this->actingAs($env['supervisorUser']);

        // Creamos un producto adicional sin conteos previos en el ciclo activo para validar el filtro pendiente
        $uncountedProduct = Product::create([
            'name' => 'Paracetamol Sin Conteo',
            'barcode' => '7598889990001',
            'unit_cost' => 1.20,
            'sale_price' => 2.50,
            'units_per_inner_pack' => 1,
            'stock' => 10,
            'category_id' => $env['category']->id,
            'is_deleted' => false,
        ]);

        $filteredCounts = $this->queryService->getFilteredQuery(new Request([
            'history' => true,
        ]))->get();
        $this->assertGreaterThanOrEqual(3, $filteredCounts->count());

        $filteredProducts = $this->queryService->getProductsFilteredQuery(new Request([
            'q' => 'Paracetamol',
        ]))->get();
        $this->assertGreaterThanOrEqual(1, $filteredProducts->count());
        $this->assertTrue($filteredProducts->contains('id', $uncountedProduct->id));

        $userStats = $this->queryService->getCountStatisticsByUser($env['counterUser']->id);
        $this->assertArrayHasKey('pending', $userStats);
        $this->assertArrayHasKey('approved', $userStats);
        $this->assertArrayHasKey('rejected', $userStats);

        $recentCounts = $this->queryService->getRecentCountsForProduct($product->id, 5);
        $this->assertNotEmpty($recentCounts);

        $frequentDiscrepancies = $this->queryService->getProductsWithFrequentDiscrepancies(10);
        $this->assertNotNull($frequentDiscrepancies);

        $summaryRows = $this->queryService->getCycleSummaryQuery(new Request([
            'cycle_id' => $activeCycle->id,
        ]))->get();
        $this->assertNotEmpty($summaryRows);

        $financialTotals = $this->queryService->getCycleFinancialTotals($activeCycle->id);
        $this->assertIsObject($financialTotals);
        $this->assertObjectHasProperty('total_surplus', $financialTotals);
        $this->assertObjectHasProperty('total_shortage', $financialTotals);
        $this->assertObjectHasProperty('net_total', $financialTotals);

        $matrixProducts = $this->queryService->getDailyQuotasMatrixData(
            (int) now()->month,
            (int) now()->year,
            'products'
        );
        $this->assertIsArray($matrixProducts);

        $matrixTotals = $this->queryService->getDailyQuotasMatrixData(
            (int) now()->month,
            (int) now()->year,
            'totals'
        );
        $this->assertIsArray($matrixTotals);

        // Cerrar el ciclo rechazando pendientes remanentes
        $forcedClose = $this->actionService->closeActiveCycle(true);
        $this->assertTrue($forcedClose['success']);
        $this->assertFalse($this->actionService->hasActiveCycle());
    }
}
