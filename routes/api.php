<?php

use App\Http\Controllers\Api\DonationController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\LotController;
use App\Http\Controllers\Api\InventoryAdjustmentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\TraceabilityController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Controllers\Api\ExpirationController;
use App\Http\Controllers\Api\QuotationController;
use App\Http\Controllers\Api\SupplierController;

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
Route::get('/barcode/{barcode}', [ResourceController::class, 'findProductByBarcode']);

// Rutas de Expiraciones
Route::get('/products/expirations', [ExpirationController::class, 'index']);
Route::put('/lots/{lot}/expire', [ExpirationController::class, 'expire']);
Route::post('/lots/expire-multiple', [ExpirationController::class, 'expireMultiple']);
Route::get('/expired-logs/summary', [ExpirationController::class, 'getSummary']);
Route::get('/expired-logs', [ExpirationController::class, 'getLotExpired']);

// Rutas de Donaciones
Route::post('/donations', [DonationController::class, 'create']);
Route::get('/donations/month/{month}/data', [DonationController::class, 'getMonthlyDonationData']);

// Rutas de Lotes de Productos
Route::resource('product-lots', LotController::class)->except(['create', 'edit']);
Route::get('/product-without-lots', [LotController::class, 'productsWithInconsistentStock']);
Route::get('/products-without-lots', [LotController::class, 'productsWithoutLot']);
Route::get('/available-suppliers', [LotController::class, 'availableSuppliers']);
Route::post('/product-lots/batch-update', [LotController::class, 'batchUpdate']);

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
    Route::get('/export', 'export')->name('api.sales.report.export');
});

// Rutas de CRM (provenientes de develop)
Route::prefix("crm")->group(function () {
    // Rutas de Compañías
    Route::prefix("companies")->group(function () {
        Route::post("/", [CompanyController::class, "create"]);
        Route::get("/", [CompanyController::class, "consultAll"]);
        Route::get("/{id}", [CompanyController::class, "consultById"]);
        Route::delete("/{id}", [CompanyController::class, "deleteById"]);
        Route::post("/edit/{id}", [CompanyController::class, "edit"]);
        Route::post("/filrar", [CompanyController::class, "filrar"]);
    });

    // Rutas de Clientes
    Route::prefix("clients")->group(function () {
        Route::post("/", [ClientController::class, "create"]);
        Route::get("/", [ClientController::class, "consultAll"]);
        Route::get("/{id}", [ClientController::class, "consultById"]);
        Route::delete("/{id}", [ClientController::class, "deleteById"]);
        Route::post("/edit/{id}", [ClientController::class, "edit"]);
        Route::post("/filrar", [ClientController::class, "filrar"]);
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
