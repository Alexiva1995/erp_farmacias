<?php

use App\Http\Controllers\Api\DonationController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\api\ExchangeRateController;
use App\Http\Controllers\Api\LotController;
use App\Http\Controllers\Api\InventoryAdjustmentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\TraceabilityController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Api\ProfitabilityController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Controllers\Api\ExpirationController;
use App\Http\Controllers\Api\LaboratoryController;
use App\Http\Controllers\Api\LotteryController;
use App\Http\Controllers\Api\QuotationController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\SupplierLaboratoryController;
use App\Http\Controllers\Api\FiscalController;
use App\Http\Controllers\Api\InventoryStockController;
use App\Http\Controllers\Api\OrderController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
// Rutas de autenticación
Route::post('/login', [LoginController::class, 'login']);
Route::post('/two-factor-challenge', [LoginController::class, 'verify2FA']);

// Rutas protegidas que requieren autenticación (Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [LoginController::class, 'logout']);
});

// Rutas de Productos
Route::get('/products', [ProductController::class, 'index']);
Route::put('/products/{product}', [ProductController::class, 'updateProducts']);
Route::post('/products', [ProductController::class, 'store']);
Route::delete('/products/{product}', [ProductController::class, 'destroy']);
Route::get('/products/export', [ProductController::class, 'export']);
Route::delete('/products/{product}/unassign-group', [ProductController::class, 'unassignProductFromGroup']);
Route::get('/cyclic', [ProductController::class, 'getProductAll']);


// Rutas de Grupos de Productos
Route::get('/groups', [GroupController::class, 'index']);
Route::post('/groups', [GroupController::class, 'store']);
Route::get('/groups/search', [GroupController::class, 'search']);
Route::put('/groups/{group}', [GroupController::class, 'update']);
Route::delete('/groups/{group}', [GroupController::class, 'destroy']);


// Rutas de Recursos Básicos (Laboratorios, Orígenes, Categorías, Proveedores, Códigos de Barras)
Route::get('/laboratories', [ResourceController::class, 'getLaboratories']);
Route::get('/origins', [ResourceController::class, 'getOrigins']);
Route::get('/categories', [ResourceController::class, 'getCategories']);
Route::get('/suppliers', [ResourceController::class, 'getSuppliers']);
Route::get('/products/all', [ResourceController::class, 'getAllProducts']);
Route::get('/barcode/{barcode}', [ResourceController::class, 'findProductByBarcode']);

// Rutas de Expiraciones
Route::get('/products/expirations', [ExpirationController::class, 'index']);
Route::put('/lots/{lot}/expire', [ExpirationController::class, 'expire']);

Route::post('/lots/expire-multiple', [ExpirationController::class, 'expireMultiple']);
Route::get('/expired-logs/summary', [ExpirationController::class, 'getSummary']);
Route::get('/expired-logs', [ExpirationController::class, 'getLotExpired']);
Route::post('/expirations/adjust-prices/preview', [App\Http\Controllers\Api\ExpirationController::class, 'previewPriceAdjustment']);
Route::post('/expirations/adjust-expired-prices', [ExpirationController::class, 'adjustExpiredProductsPrices']);
Route::get('/expirations/month/{month}/adjustment-status', [ExpirationController::class, 'checkMonthAdjustmentStatus']);

// Opcional: Ruta para obtener el historial de reajustes
Route::get('/price-adjustments', [ExpirationController::class, 'getPriceAdjustmentHistory']);
Route::get('/price-adjustments/month/{month}', [ExpirationController::class, 'getMonthPriceAdjustments']);

// Rutas de Donaciones
Route::post('/donations', [DonationController::class, 'create']);
Route::get('/donations/month/{month}/data', [DonationController::class, 'getMonthlyDonationData']);

// Rutas de Lotes de Productos
Route::resource('product-lots', LotController::class)->except(['create', 'edit']);
Route::get('/product-without-lots', [LotController::class, 'productsWithInconsistentStock']);
Route::get('/products-without-lots', [LotController::class, 'productsWithoutLot']);
Route::get('/available-suppliers', [LotController::class, 'availableSuppliers']);
Route::post('/product-lots/batch-update', [LotController::class, 'batchUpdate']);
Route::get('lots/available-stock/{productId}', [LotController::class, 'getAvailableStock']);

// Rutas de Ajustes de Inventario
Route::post('/adjustments/{product}/validate-barcode', [InventoryAdjustmentController::class, 'validateBarcode']);
Route::post('/adjustments/process-count', [InventoryAdjustmentController::class, 'processCount']);

// Rutas de TPV / Cotizaciones (provenientes de 4.0-TPV)
Route::get('/quotation', [QuotationController::class, 'index']);
Route::get('/quotation/{product}', [QuotationController::class, 'show']);
Route::post('/quotations', [QuotationController::class, 'store']);

// Rutas de Trazabilidad (provenientes de develop)
Route::prefix('sales/report')->controller(TraceabilityController::class)->group(function () {
    Route::get('/', 'index')->name('api.sales.report.index');
    Route::get('/filterByPsychotropics', 'filterByPsychotropics');
    Route::get('/export', 'export')->name('api.sales.report.export');
});

// Rutas de CRM (provenientes de develop)
Route::prefix("crm")->group(function () {


    // Rutas de Doctores
    Route::prefix("doctors")->group(function () {
        Route::post("/", [DoctorController::class, "create"]);
        Route::post("/edit/{id}", [DoctorController::class, "edit"]);
        Route::get("/", [DoctorController::class, "consultAll"]);
        Route::get("/{id}", [DoctorController::class, "consultById"]);
        Route::delete("/{id}", [DoctorController::class, "deleteById"]);
        Route::post("/filtrar", [DoctorController::class, "filtrar"]);
        Route::post("/filtrar-sin-paginar", [DoctorController::class, "filtrarSinPaginar"]);
        Route::get("/exportar/excel", [DoctorController::class, "exportarExcel"]);
        Route::get("/help/check", [DoctorController::class, "helpCheck"]);
    });

    // Rutas de Compañías
    Route::prefix("companies")->group(function () {
        Route::post("/", [CompanyController::class, "create"]);
        Route::get("/", [CompanyController::class, "consultAll"]);
        Route::get("/{id}", [CompanyController::class, "consultById"]);
        Route::delete("/{id}", [CompanyController::class, "deleteById"]);
        Route::post("/edit/{id}", [CompanyController::class, "edit"]);
        Route::post("/filtrar", [CompanyController::class, "filtrar"]);
        Route::post("/filtrar-sin-paginar", [CompanyController::class, "filtrarSinPaginar"]);
        Route::get("/exportar/excel", [CompanyController::class, "exportarExcel"]);
    });

    // Rutas de Clientes
    Route::prefix("clients")->group(function () {
        Route::post("/", [ClientController::class, "create"]);
        Route::get("/", [ClientController::class, "consultAll"]);
        Route::get("/{id}", [ClientController::class, "consultById"]);
        Route::delete("/{id}", [ClientController::class, "deleteById"]);
        Route::post("/edit/{id}", [ClientController::class, "edit"]);
        Route::post("/filtrar", [ClientController::class, "filtrar"]);
        Route::post("/filtrar-sin-paginar", [ClientController::class, "filtrarSinPaginar"]);
        Route::get("/exportar/excel", [ClientController::class, "exportarExcel"]);
    });

    // Rutas Sorteo
    Route::prefix("lottery")->group(function () {
        Route::post("/filtrar-ordenes-sin-paginar",  [LotteryController::class, "filtrarOrdenesWithoutPaginate"]);
        Route::post("/filtrar-ordenes",              [LotteryController::class, "filtrarOrdenesPaginate"]);
    });
});

Route::prefix("orders")->group(function () {
    Route::get("/psychotropics/pagination", [OrderController::class, "filtrarOrderPorpsychotropicsConPaginacion"]);
});
Route::prefix("inventory")->group(function () {

    Route::prefix("stock")->group(function () {
        Route::post("/filter", [InventoryStockController::class, "filter"]);
        Route::post("/filter-without-paginate", [InventoryStockController::class, "filterWithoutPaginate"]);
        Route::get("/exportar/excel", [InventoryStockController::class, "exportarExcel"]);
    });
});

// Route Laboratorio
// Route::prefix("laboratories")->group(function () {
//     Route::get("/", [LaboratoryController::class, "consultAll"]);

// });

// Ruta de fiscal
// Histori
Route::get('/history', [FiscalController::class, 'index']);
Route::get('/history/export', [FiscalController::class, 'export']);

// Finances
//Route::get('/profitability', [ProfitabilityController::class, 'getProfitabilityAll']);

Route::prefix("finances")->group(function () {

    // Profitability
    Route::prefix("profitability")->group(function () {

        Route::get("/", [ProfitabilityController::class, "consultOne"]);
        Route::post("/store", [ProfitabilityController::class, "store"]);
        Route::post("/{id}", [ProfitabilityController::class, "edit"]);

        Route::prefix("product")->group(function () {
            Route::get("/{id}", [ProfitabilityController::class, "getProduct"]);
            Route::post("/update", [ProfitabilityController::class, "editProfitabilityProduct"]);
            Route::post("/store", [ProfitabilityController::class, "storeProfitabilityProduct"]);
        });
    });

    // exchange rates
    Route::prefix("exchange-rates")->group(function () {

        Route::get("/", [ExchangeRateController::class, "consultAll"]);
        Route::get("/apiDollar", [ExchangeRateController::class, "apiDollar"]);
        Route::post("/store", [ExchangeRateController::class, "store"]);
        Route::get("/consultOneCOP", [ExchangeRateController::class, "consultOneCOP"]);
        Route::get("/consultOneBCV", [ExchangeRateController::class, "consultOneBCV"]);
    });
});

// Rutas de Proveedores
Route::resource('suppliers', SupplierController::class)->except(['create', 'edit', 'show']);
Route::prefix("suppliers")->group(function () {
    Route::get('/check-health', [SupplierController::class, 'checkApiHealth']);
    Route::put('/{supplier}/payment-rule', [SupplierController::class, 'updatePaymentRule']);
    Route::post('/{supplier}/laboratories', [SupplierController::class, 'storeLaboratory']);
    Route::get('/{supplier}/laboratories', [SupplierController::class, 'getLaboratoryLinks']);
    Route::get('/{supplier}/pending-invoices', [SupplierController::class, 'getPendingInvoices']);
});

Route::prefix("supplier-laboratories")->group(function () {
    Route::get('/{supplier}/discount-rules', [SupplierLaboratoryController::class, 'getDiscountRules']);
    Route::post('/{lab}/discount-rules', [SupplierLaboratoryController::class, 'storeDiscountRule']);
});
