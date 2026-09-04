<?php

use App\Http\Controllers\Api\CleaningActivityController;
use App\Http\Controllers\Api\SupplierAiMatchController;
use App\Http\Controllers\Api\AutoReplenishmentConfigController;

use App\Http\Controllers\Api\DonationController;
use App\Http\Controllers\Api\EmployeeCleaningActivityController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\EmployeeLaboratoryController;
use App\Http\Controllers\Api\EmployeePerformanceController;
use App\Http\Controllers\Api\IslrController;
use App\Http\Controllers\Api\EmployeeProductController;
use App\Http\Controllers\Api\ResignationController;
use App\Http\Controllers\Api\FurnitureController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\ExchangeRateController;
use App\Http\Controllers\Api\InventoryCycleController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\InvoiceReturnController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\LotController;
use App\Http\Controllers\Api\InventoryAdjustmentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\TraceabilityController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Api\ProfitabilityController;
use App\Http\Controllers\Api\PayslipController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseOrderDetailController;
use App\Http\Controllers\Api\SocialBenefitController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Controllers\Api\ExpirationController;
use App\Http\Controllers\Api\LotteryController;
use App\Http\Controllers\Api\QuotationController;
use App\Http\Controllers\Api\MarketOpportunityController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\SupplierLaboratoryController;
use App\Http\Controllers\Api\FiscalController;
use App\Http\Controllers\Api\InventoryStockController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProcessAuditController;
use App\Http\Controllers\Api\PendingPaymentsController;
use App\Http\Controllers\Api\CreditsController;
use App\Http\Controllers\Api\ExpenseCategoryController;
use App\Http\Controllers\Api\ExpensesController;
use App\Http\Controllers\Api\SupplierIaAssistantReportController;
use App\Http\Controllers\Api\SuppliersIaOrderAssistantController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserPreferenceController;
use App\Http\Controllers\Api\ReturnsController;
use App\Http\Controllers\Api\IndividualOfferController;
use App\Http\Controllers\Api\CategoryOfferController;
use App\Http\Controllers\Api\CompanyOfferController;
use App\Http\Controllers\Api\DoctorOfferController;
use App\Http\Controllers\Api\ExpirationOfferController;
use App\Http\Controllers\Api\ProductPackController;
use App\Http\Controllers\Api\PrescriptionOfferController;
use App\Http\Controllers\Api\CashClosureController;
use App\Http\Controllers\Api\FinancialStatementController;
use App\Http\Controllers\Api\GeneralSettingController;
use App\Http\Controllers\Api\ProductFailureController;
use App\Http\Controllers\Api\SpecialtyController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Public\SupplierPublicUploadController;
use App\Http\Controllers\Public\SupplierOrderResponseController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\FiscalPrinterController;
use App\Http\Controllers\Api\IaAssistantActionController;
use App\Http\Controllers\Api\DishController;
use App\Http\Controllers\Api\EcommerceController;

// Fiscal Printer Bridge (OUTSIDE AUTH TO AVOID LOGIN ISSUES IN PYTHON)
Route::prefix('fiscal')->group(function () {
    Route::get('/pending', [FiscalPrinterController::class, 'getPending']);
    Route::patch('/confirm/{id}', [FiscalPrinterController::class, 'confirm']);
    
    // Rutas para comandos generales (Python Bridge)
    Route::get('/commands/status', [FiscalPrinterController::class, 'checkStatus']);
    Route::get('/commands/history', [FiscalPrinterController::class, 'history']);
    Route::get('/commands/pending', [FiscalPrinterController::class, 'getPendingCommand']);
    Route::patch('/commands/{id}/confirm', [FiscalPrinterController::class, 'confirmCommand']);
    
    // Nueva ruta de réplica
    Route::patch('/confirm-replica/{id}', [FiscalPrinterController::class, 'confirmReplica']);
});

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Rutas de autenticación
Route::post("/login", [LoginController::class, "login"])->middleware('throttle:login')->name('login');
Route::post("/two-factor-challenge", [LoginController::class, "verify2FA"])->middleware('throttle:login');

// Rutas públicas (no requieren autenticación ni middleware de estado)
Route::get("/public/bootstrap-config", [\App\Http\Controllers\Api\BootstrapConfigController::class, "index"]);
Route::get("/public/exchange-rates", [ResourceController::class, "getExchangeRates"]);
Route::get("/public/suppliers/upload/{token}", [SupplierPublicUploadController::class, "show"]);
Route::post("/public/suppliers/upload/{token}", [SupplierPublicUploadController::class, "upload"]);
Route::get("/public/orders/{hash}", [SupplierOrderResponseController::class, "show"]);

// Master Catalog API (Para comunicación entre Farmacia Principal y Esclavas)
Route::prefix('v1/master-catalog')->middleware('master_catalog')->group(function () {
    Route::get('/lookup', [\App\Http\Controllers\Api\MasterCatalogController::class, 'lookup']);
    Route::get('/bulk-export', [\App\Http\Controllers\Api\MasterCatalogController::class, 'bulkExport']);
    Route::post('/products', [\App\Http\Controllers\Api\MasterCatalogController::class, 'store']);
    Route::post('/laboratories', [\App\Http\Controllers\Api\MasterCatalogController::class, 'storeLaboratory']);
    Route::post('/groups', [\App\Http\Controllers\Api\MasterCatalogController::class, 'storeGroup']);
    Route::post('/suppliers', [\App\Http\Controllers\Api\MasterCatalogController::class, 'storeSupplier']);
    Route::post('/origins', [\App\Http\Controllers\Api\MasterCatalogController::class, 'storeOrigin']);
    Route::post('/categories', [\App\Http\Controllers\Api\MasterCatalogController::class, 'storeCategory']);
});
Route::post("/public/orders/{hash}/respond", [SupplierOrderResponseController::class, "respond"]);

Route::post("/public/reservations/webhook", [\App\Http\Controllers\Api\ReservationController::class, "webhook"]);
Route::get("/public/reservations/confirm-direct/{id}", [\App\Http\Controllers\Api\ReservationController::class, "confirmDirect"]);
Route::get("/public/reservations", [\App\Http\Controllers\Api\ReservationController::class, "index"]);
Route::post("/public/reservations", [\App\Http\Controllers\Api\ReservationController::class, "store"]);
Route::get("/public/reservations/search-to-cancel", [\App\Http\Controllers\Api\ReservationController::class, "searchToCancel"]);
Route::post("/public/reservations/{id}/request-cancellation", [\App\Http\Controllers\Api\ReservationController::class, "requestCancellation"]);
Route::get("/public/reservations/{id}/confirm-cancellation", [\App\Http\Controllers\Api\ReservationController::class, "confirmCancellationByAdmin"]);
Route::patch("/public/reservations/{id}/confirm", [\App\Http\Controllers\Api\ReservationController::class, "publicConfirm"]);

Route::post("/public/telegram/webhook", [\App\Http\Controllers\Api\TelegramWebhookController::class, "handle"]);
Route::get("/public/clients/identification/{identification}", [\App\Http\Controllers\Api\ClientController::class, "consultByIdentification"]);
Route::post("/public/visits", [\App\Http\Controllers\Api\BookingVisitController::class, "store"]);

// Rutas de E-commerce TOVA
Route::get("/public/ecommerce/products", [EcommerceController::class, "getProducts"]);
Route::get("/public/ecommerce/categories", [EcommerceController::class, "getCategories"]);
Route::post("/public/ecommerce/checkout", [EcommerceController::class, "checkout"]);
Route::post("/public/ecommerce/products/{id}/toggle-favorite", [EcommerceController::class, "toggleFavorite"]);
// Consulta CNE pública para autocompletar datos del cliente en la tienda virtual
Route::post("/public/clients/cne-verify", [\App\Http\Controllers\Api\ClientController::class, "verifyCne"]);
Route::get("/public/general-settings", [GeneralSettingController::class, "index"]);

// Rutas de administración de órdenes de e-commerce (dentro del bloque auth:sanctum)
Route::middleware(["auth:sanctum", "throttle:api"])->group(function () {
    Route::get("/ecommerce/admin/orders", [EcommerceController::class, "getAdminOrders"]);
    Route::post("/ecommerce/admin/orders/{id}/approve", [EcommerceController::class, "approveOrder"]);
    Route::post("/ecommerce/admin/orders/{id}/cancel", [EcommerceController::class, "cancelOrder"]);
    Route::post("/ecommerce/admin/orders/{id}/ship", [EcommerceController::class, "shipOrder"]);
    Route::post("/ecommerce/admin/orders/{id}/complete", [EcommerceController::class, "completeOrder"]);
});

// Rutas protegidas que requieren autenticación (Sanctum)
Route::middleware(["auth:sanctum", "throttle:api"])->group(function () {
    Route::get('/reservations', [\App\Http\Controllers\Api\ReservationController::class, 'index']);
    Route::post('/reservations', [\App\Http\Controllers\Api\ReservationController::class, 'store']);
    Route::patch('/reservations/{id}/status', [\App\Http\Controllers\Api\ReservationController::class, 'updateStatus']);
    Route::delete('/reservations/{id}', [\App\Http\Controllers\Api\ReservationController::class, 'destroy']);
    Route::post('/fixed-schedules', [\App\Http\Controllers\Api\FixedScheduleController::class, 'store']);
    Route::put('/fixed-schedules/{id}', [\App\Http\Controllers\Api\FixedScheduleController::class, 'update']);
    Route::delete('/fixed-schedules/{id}', [\App\Http\Controllers\Api\FixedScheduleController::class, 'destroy']);

    Route::prefix('telegram')->group(function () {
        Route::get('/config', [\App\Http\Controllers\Api\TelegramConfigController::class, 'getConfig']);
        Route::put('/config', [\App\Http\Controllers\Api\TelegramConfigController::class, 'updateConfig']);
        Route::post('/webhook/register', [\App\Http\Controllers\Api\TelegramConfigController::class, 'registerWebhook']);
        Route::get('/webhook/status', [\App\Http\Controllers\Api\TelegramConfigController::class, 'getWebhookStatus']);

        // Rutas de Múltiples Canales
        Route::get('/channels', [\App\Http\Controllers\Api\TelegramConfigController::class, 'getChannels']);
        Route::post('/channels', [\App\Http\Controllers\Api\TelegramConfigController::class, 'storeChannel']);
        Route::put('/channels/{id}', [\App\Http\Controllers\Api\TelegramConfigController::class, 'updateChannel']);
        Route::patch('/channels/{id}/toggle', [\App\Http\Controllers\Api\TelegramConfigController::class, 'toggleChannel']);
        Route::delete('/channels/{id}', [\App\Http\Controllers\Api\TelegramConfigController::class, 'deleteChannel']);
        Route::post('/channels/{id}/test', [\App\Http\Controllers\Api\TelegramConfigController::class, 'testChannelMessage']);

        // Rutas de Comandos por Módulo
        Route::get('/commands/{module}', [\App\Http\Controllers\Api\TelegramConfigController::class, 'getModuleCommands']);
        Route::patch('/commands/{id}/toggle', [\App\Http\Controllers\Api\TelegramConfigController::class, 'toggleCommand']);
        Route::put('/commands/{id}', [\App\Http\Controllers\Api\TelegramConfigController::class, 'updateCommand']);
    });

    Route::get('/general-settings', [GeneralSettingController::class, 'index']);
    Route::post('/import-csv', [\App\Http\Controllers\Api\DataImportController::class, 'importCsv']);
    Route::post('/import-external-catalog', [\App\Http\Controllers\Api\DataImportController::class, 'importExternalCatalog']);
    // Rutas de Finanzas (Estado de Resultados) - Protegidas por autenticación
    Route::prefix("finances")->group(function () {
        Route::get("/income-statement", [FinancialStatementController::class, "index"]);
        Route::get("/income-statement/summary", [FinancialStatementController::class, "getSummary"]);
        Route::get("/income-statement/details", [FinancialStatementController::class, "getDetails"]);
        Route::post("/income-statement/reset", [FinancialStatementController::class, "reset"]);
    });

    Route::get("/my-assigned-labs", function (Request $request) {
        return response()->json(
            \App\Models\Laboratory::whereHas('employees', function($q) use ($request) {
                $q->where('user_id', $request->user()->id);
            })->select('id', 'name')->get()
        );
    });
    Route::get("/my-assigned-products", function (Request $request) {
        $userId = $request->user()->id;
        $employee = \App\Models\Employee::where('user_id', $userId)->first();
        if (!$employee) return response()->json([]);
        return response()->json($employee->products()->pluck('products.id'));
    });
    Route::get("/user", function (Request $request) {
        // Query fresca con eager loading garantizado
        return \App\Models\User::with('employee.laboratories')->find($request->user()->id);
    });
    Route::get('/user/config', function (Request $request) {
        return $request->user()->load('config');
    });
    Route::post('/user/update-sort-config', [UserController::class, 'updateSortConfig']);
    Route::get('/user/ui-preferences', [UserPreferenceController::class, 'index']);
    Route::post('/user/ui-preferences', [UserPreferenceController::class, 'update']);
    Route::post("/logout", [LoginController::class, "logout"]);

    // Rutas de Productos
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/incomplete', [ProductController::class, 'incomplete']);
    Route::get('/products/without-group', [ProductController::class, 'withoutGroup']);
    Route::get('/productsAll', [ProductController::class, 'getProducts']);
    Route::get('/products/autocomplete', [ProductController::class, 'forAutocomplete']);
    Route::put('/products/{product}', [ProductController::class, 'updateProducts']);
    Route::get('/products/{product}/stock', [ProductController::class, 'getStock']);
    Route::patch('/products/incomplete/{product}', [ProductController::class, 'updateIncomplete']);
    Route::patch('/products/without-group/{product}', [ProductController::class, 'updateProductGroup']);
    Route::get('/products/{product}/stats', [ProductController::class, 'getStats']);
    Route::get('/products/{product}/next-lot-number', [ProductController::class, 'getNextLotNumber']);
    Route::post('/products/bulk-actions', [ProductController::class, 'bulkActions']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);
    Route::post('/products/{id}/restore', [ProductController::class, 'restore']);
    Route::get('/products/export', [ProductController::class, 'export']);
    Route::post('/products/{product}/toggle-scarce', [ProductController::class, 'toggleScarce']);
    Route::post('/products/{product}/toggle-active', [ProductController::class, 'toggleActive']);
    Route::delete('/products/{product}/unassign-group', [ProductController::class, 'unassignProductFromGroup']);
    Route::get('/products/search-by-barcode', [ProductController::class, 'searchByBarcode']);
    Route::get('/catalog/master-lookup', [\App\Http\Controllers\Api\CatalogLookupController::class, 'lookup']);
    Route::get('/products/inventory/value', [ProductController::class, 'getInventoryValue']);
    Route::post('/products/merge', [ProductController::class, 'merge']);

    // Rutas de Grupos de Productos
    Route::get("/groups", [GroupController::class, "index"]);
    Route::post("/groups", [GroupController::class, "store"]);
    Route::get("/groups/search", [GroupController::class, "search"]);
    Route::put("/groups/{group}", [GroupController::class, "update"]);
    Route::delete("/groups/{group}", [GroupController::class, "destroy"]);
    Route::get("/groups/consult-all", [GroupController::class, "consultAll"]);
    Route::post("/groups/{group}/associate-products", [GroupController::class, "associateProducts"]);

    // Rutas de Recursos Básicos (Laboratorios, Orígenes, Categorías, Proveedores, Códigos de Barras)
    Route::get("/laboratories", [ResourceController::class, "getLaboratories"]);
    Route::post("/laboratories", [ResourceController::class, "storeLaboratory"]);

    // Alias directo para Categorías de Gastos (/api/expenses/category)
    Route::prefix("expenses/category")->group(function () {
        Route::get("/", [ExpenseCategoryController::class, "getAll"]);
        Route::post("/", [ExpenseCategoryController::class, "store"]);
        Route::put("/{id}", [ExpenseCategoryController::class, "update"]);
        Route::delete("/{id}", [ExpenseCategoryController::class, "destroy"]);
    });

    // Gestión Administrativa de Laboratorios
    Route::prefix('inventory/laboratories-manage')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\Inventory\LaboratoryManagementController::class, 'index']);
        Route::get('/groups', [\App\Http\Controllers\Api\Inventory\LaboratoryManagementController::class, 'groups']);
        Route::post('/', [\App\Http\Controllers\Api\Inventory\LaboratoryManagementController::class, 'store']);
        Route::post('/groups', [\App\Http\Controllers\Api\Inventory\LaboratoryManagementController::class, 'storeGroup']);
        Route::delete('/groups/{group}', [\App\Http\Controllers\Api\Inventory\LaboratoryManagementController::class, 'destroyGroup']);
        Route::delete('/{laboratory}', [\App\Http\Controllers\Api\Inventory\LaboratoryManagementController::class, 'destroy']);
    });

    // Gestión Administrativa de Categorías de Inventario
    Route::prefix('inventory/categories-manage')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\Inventory\CategoryManagementController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\Inventory\CategoryManagementController::class, 'store']);
        Route::delete('/{category}', [\App\Http\Controllers\Api\Inventory\CategoryManagementController::class, 'destroy']);
    });
    Route::get("/origins", [ResourceController::class, "getOrigins"]);
    Route::post("/origins", [ResourceController::class, "storeOrigin"]);
    Route::get("/categories", [ResourceController::class, "getCategories"]);
    Route::apiResource("locations", LocationController::class);
    Route::apiResource("dishes", DishController::class);
    Route::get("/suppliers", [ResourceController::class, "getSuppliers"]);
    Route::get("/products/all", [ResourceController::class, "getAllProducts"]);
    Route::get("/barcode/{barcode}", [ResourceController::class, "findProductByBarcode"]);
    Route::get("/product/{product}", [ResourceController::class, "findProductById"]);

    // Rutas de Expiraciones
    Route::get("/products/expirations", [ExpirationController::class, "index"]);
    Route::get("/products/expirations-all", [ExpirationController::class, "getExpiringAll"]);
    Route::get("/products/expirations/export", [ExpirationController::class, "export"]);
    Route::put("/lots/{lot}/expire", [ExpirationController::class, "expire"]);
    Route::post("/lots/expire-multiple", [ExpirationController::class, "expireMultiple"]);
    Route::get("/expired-logs/summary", [ExpirationController::class, "getSummary"]);
    Route::get("/expired-logs", [ExpirationController::class, "getLotExpired"]);
    Route::post("/expirations/adjust-prices/preview", [App\Http\Controllers\Api\ExpirationController::class, "previewPriceAdjustment"]);
    Route::post("/expirations/adjust-expired-prices", [ExpirationController::class, "adjustExpiredProductsPrices"]);
    Route::get("/expirations/month/{month}/adjustment-status", [ExpirationController::class, "checkMonthAdjustmentStatus"]);
    Route::get("/price-adjustments", [ExpirationController::class, "getPriceAdjustmentHistory"]);
    Route::get("/price-adjustments/month/{month}", [ExpirationController::class, "getMonthPriceAdjustments"]);
    Route::get("/products/expirations/month/{month}/report", [ExpirationController::class, "downloadMonthlyReport"]);

    // Rutas de Donaciones
    Route::post("/donations", [DonationController::class, "create"]);
    Route::get("/donations/month/{month}/data", [DonationController::class, "getMonthlyDonationData"]);

    // Rutas de Lotes de Productos
    // Rutas específicas deben ir ANTES del resource para evitar conflictos
    Route::delete('/product-lots/clean-zero-quantity', [LotController::class, 'deleteLotsWithZeroQuantity']);
    Route::post('/product-lots/batch-update', [LotController::class, 'batchUpdate']);
    Route::get('/product-lots/without-location', [LotController::class, 'lotsWithoutLocation']);
    Route::get('/product-without-lots', [LotController::class, 'productsWithInconsistentStock']);
    Route::get('/inventory/products-pending-lotification', [LotController::class, 'getProductsPendingLotification']);
    Route::get('/product-lots/available-suppliers', [LotController::class, 'availableSuppliers']);
    Route::resource('product-lots', LotController::class)->except(['create', 'edit']);
    Route::get('lots/available-stock/{productId}', [LotController::class, 'getAvailableStock']);
    Route::get('lots/product/{productId}', [LotController::class, 'getProductLots']);

    // Rutas de Inventario
    // Ejemplo: GET /api/inventory/cycle/1
    Route::get("/products/count", [InventoryCycleController::class, "getProductCount"]);
    Route::post("/products/count/{countId}/process", [InventoryCycleController::class, "processCountAction"]);
    Route::prefix("inventory")->group(function () {
        Route::get("cycle/active", [InventoryCycleController::class, "getActiveCycleStatus"])->name("inventory.cycle.active");
        Route::get("cycle/users-with-counts", [InventoryCycleController::class, "getUsersWithCounts"]);
        Route::get("products", [InventoryCycleController::class, "getProductsForInventory"])->name("inventory.products.index");
        Route::get("user-quota-status", [InventoryCycleController::class, "getUserQuotaStatus"]);
        Route::post("request-more-quota", [InventoryCycleController::class, "requestMoreQuotaProducts"]);
        Route::get("daily-quotas-matrix", [InventoryCycleController::class, "getDailyQuotasMatrix"]);
        Route::get("/cash-close-items", [InventoryCycleController::class, "getCashCloseItems"]);
        Route::post("/cycle/close", [InventoryCycleController::class, "closeActiveCycle"]);
        Route::post("/cycle/create", [InventoryCycleController::class, "createCycle"]);
        Route::get('/cycle/summary', [InventoryCycleController::class, 'getCycleSummary']);
        Route::get('/cycle/{cycleId}', [InventoryCycleController::class, 'getCycleInfo']);
        Route::prefix('count')->group(function () {
            Route::get('/invoices/count', [InventoryCycleController::class, 'getInvoiceCount']);
            Route::get('/sale/count', [InventoryCycleController::class, 'getSaleCount']);
            Route::post('/invoices/{countId}/process', [InventoryCycleController::class, 'processInvoiceCountAction']);
            Route::post('/sale/{countId}/process', [InventoryCycleController::class, 'processSaleCountAction']);
            Route::post('/invoice-count/{productId}', [InventoryCycleController::class, 'storeInvoiceCount']);
            Route::post('/sales-count/{productId}', [InventoryCycleController::class, 'storeSaleCount']);
            Route::get('/invoice-details-to-count', [InventoryCycleController::class, 'getInvoiceDetailsToCount']);
            Route::get('/sales-details-to-count', [InventoryCycleController::class, 'getSaleDetailsToCount']);
            Route::get('/', [InventoryCycleController::class, 'getProductCount']);
            Route::post('{product}', [InventoryCycleController::class, 'storeProductCount']);
            Route::post('{countId}/process', [InventoryCycleController::class, 'processCountAction']);
            Route::post('{count}/action', [InventoryCycleController::class, 'processCountAction']);
            Route::delete('{sourceType}/{id}', [InventoryCycleController::class, 'deleteCount']);
            Route::patch('{sourceType}/{id}/discrepancy', [InventoryCycleController::class, 'updateDiscrepancy']);
        });
        Route::prefix("statistics")->group(function () {
            Route::get("/", [InventoryCycleController::class, "getCountStatistics"])->name("inventory.statistics");
        });
        Route::prefix("stock")->group(function () {
            Route::post("/filter", [InventoryStockController::class, "filter"]);
            Route::post("/filter-without-paginate", [InventoryStockController::class, "filterWithoutPaginate"]);
            Route::get("/exportar/excel", [InventoryStockController::class, "exportarExcel"]);
            Route::post("/exportar/excel", [InventoryStockController::class, "exportarExcel"]);
        });
    });

    // Rutas de Ajustes de Inventario
    Route::post("/adjustments/{product}/validate-barcode", [InventoryAdjustmentController::class, "validateBarcode"]);
    Route::post("/adjustments/process-count", [InventoryAdjustmentController::class, "processCount"]);

    // Rutas de TPV
    Route::prefix("tpv")->group(function () {
        Route::get("/courts/all", function () {
            return response()->json(\App\Models\Court::all());
        });
        Route::get("/quotation", [QuotationController::class, "index"]);
        Route::get("/quotation/{product}", [QuotationController::class, "show"]);
        Route::get("/quotations/list", [QuotationController::class, "list"]);
        Route::post("/quotations", [QuotationController::class, "store"]);
        Route::get("/quotations/{quotationId}/products", [QuotationController::class, "showProducts"]);
        Route::get("/quotations/last-number", [QuotationController::class, "getLastNumber"]);
        Route::get("/order", [OrderController::class, "index"]);
        Route::get("/order/client/{Identification}", [OrderController::class, "consultByIdentification"]);
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/order/seller/my-open-order', [OrderController::class, 'getMyOpenOrder']);
        Route::get('/orders/reserved-list', [OrderController::class, 'getReservedOrders']);
        Route::post('/orders/{order}/activate', [OrderController::class, 'activateOrder']);
        Route::post('/orders/{order}/items', [OrderController::class, 'storeOrderItem']);
        Route::patch('/orders/{order}', [OrderController::class, 'updateOrderTotals']);
        Route::delete('/orders/{order}/items/{item}', [OrderController::class, 'deleteOrderDetail']);
        Route::patch('/orders/{order}/abandon', [OrderController::class, 'abandonOrder']);
        Route::post('/orders/{orderId}/complete', [OrderController::class, 'completeOrder']);
        Route::patch('/order/{order}/reserve', [OrderController::class, 'reserveOrder']);
        Route::patch('/order/{order}/reserveAdd', [OrderController::class, 'reserveAddOrder']);
        Route::get("/order/searchReserved", [OrderController::class, "getSearchReserved"]);
        Route::get('/orders/cancelled', [OrderController::class, 'getCancelledOrder']);
        Route::get('/orders/completed', [OrderController::class, 'getcompletedOrder']);
        Route::get('/orders/all', [OrderController::class, 'getAllOrder']);
        Route::get('/orders/abandoned', [OrderController::class, 'getAbandonedOrder']);
        Route::get('/orders/{orderId}/print', [OrderController::class, 'getCPrintOrder']);
        Route::patch('/orders/{order}/cancelled', [OrderController::class, 'cancelledOrder']);
        Route::get('/credits', [CreditsController::class, 'index']);
        Route::delete('/credits', [CreditsController::class, 'destroy']);
        Route::put('/credits/status', [CreditsController::class, 'updateCreditStatus']);
        Route::post('/credits/complete', [CreditsController::class, 'completeCredits']);
        Route::post('/credits/details', [CreditsController::class, 'showDetails']);
        Route::get('/credits/payments', [CreditsController::class, 'payments']);
        Route::post('/credits/payments', [CreditsController::class, 'getPaymentHistory']);
        Route::delete('/credits/payments/{payment}', [CreditsController::class, 'destroyPayment']);
        Route::get('/returns', [ReturnsController::class, 'index']);
        Route::post('/returns/search-orders', [ReturnsController::class, 'searchOrders']);
        Route::get('/returns/product/{productId}/lots', [ReturnsController::class, 'getProductLots']);
        Route::post('/returns/product', [ReturnsController::class, 'returnsProduct']);
        Route::patch('/returns/{returnEntryId}/{status}', [ReturnsController::class, 'updateReturnStatus']);
        Route::post('/returns/{returnEntryId}/distribute-lots', [ReturnsController::class, 'distributeLots']);
        Route::post('/returns/{returnEntryId}/approve-with-distribution', [ReturnsController::class, 'approveWithDistribution']);
        // Rutas de Promociones
        Route::prefix("promotions")->group(function () {
            Route::prefix("individual")->group(function () {
                Route::get('/', [IndividualOfferController::class, "index"]);
                Route::post('/', [IndividualOfferController::class, "store"]);
                Route::put('/{individual}', [IndividualOfferController::class, "update"]);
                Route::delete('/{individual}', [IndividualOfferController::class, 'destroy']);
            });
            Route::prefix("category")->group(function () {
                Route::get('/', [CategoryOfferController::class, "index"]);
                Route::post('/', [CategoryOfferController::class, "store"]);
                Route::put('/{category}', [CategoryOfferController::class, "update"]);
                Route::delete('/{category}', [CategoryOfferController::class, 'destroy']);
            });
            Route::prefix("company-offer")->group(function () {
                Route::get('/', [CompanyOfferController::class, "index"]);
                Route::post('/', [CompanyOfferController::class, "store"]);
                Route::put('/{companyOffer}', [CompanyOfferController::class, "update"]);
                Route::delete('/{companyOffer}', [CompanyOfferController::class, 'destroy']);
                Route::post('/{companyOffer}/recalculate', [CompanyOfferController::class, "recalculate"]);
            });
            Route::prefix("doctor-offer")->group(function () {
                Route::get('/', [DoctorOfferController::class, "index"]);
                Route::post('/', [DoctorOfferController::class, "store"]);
                Route::put('/{doctorOffer}', [DoctorOfferController::class, "update"]);
                Route::delete('/{doctorOffer}', [DoctorOfferController::class, 'destroy']);
            });
            Route::prefix("expiration-offer")->group(function () {
                Route::get('/', [ExpirationOfferController::class, "index"]);
                Route::post('/', [ExpirationOfferController::class, "store"]);
                Route::put('/{expirationOffer}', [ExpirationOfferController::class, "update"]);
                Route::delete('/{expirationOffer}', [ExpirationOfferController::class, 'destroy']);
                Route::get('/available-product-lots', [ExpirationOfferController::class, 'getAvailableProductLots']);
            });
            Route::prefix("product-packs")->group(function () {
                Route::get('/', [ProductPackController::class, "index"]);
                Route::post('/', [ProductPackController::class, "store"]);
                Route::get('/{productPack}', [ProductPackController::class, "show"]);
                Route::put('/{productPack}', [ProductPackController::class, "update"]);
                Route::delete('/{productPack}', [ProductPackController::class, 'destroy']);
            });
            Route::prefix("prescription-offer")->group(function () {
                Route::get('/', [PrescriptionOfferController::class, "index"]);
                Route::post('/', [PrescriptionOfferController::class, "store"]);
                Route::get('/{prescriptionOffer}', [PrescriptionOfferController::class, "show"]);
                Route::put('/{prescriptionOffer}', [PrescriptionOfferController::class, "update"]);
                Route::delete('/{prescriptionOffer}', [PrescriptionOfferController::class, 'destroy']);
            });
            Route::apiResource("general-promotions", \App\Http\Controllers\Api\GeneralPromotionController::class);
        });
        //ruta para productos con fallas
        Route::post('/product-failure', [ProductFailureController::class, 'store'])->name('product-failure.store');
        Route::get('/heartbeat', function () {
            return response()->json(['status' => 'alive']);
        });//
    });
    Route::get('debito-fiscal', [OrderController::class, 'getDebitoFiscal']);
    Route::get('fiscal-history', [OrderController::class, 'getFiscalHistoryData']);


    //ruta de configuracion
    Route::post('/general-settings', [GeneralSettingController::class, 'store']);

    // Rutas del Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('/total-income', [DashboardController::class, 'getTotalIncome']);
        Route::get('/deductible-expenses', [DashboardController::class, 'getDeductibleExpenses']);
        Route::get('/non-deductible-expenses', [DashboardController::class, 'getNonDeductibleExpenses']);
        Route::get('/revenue-report', [DashboardController::class, 'getRevenueReport']);
        Route::get('/stats', [DashboardController::class, 'getStats']);
        Route::get('/units-sold', [DashboardController::class, 'getUnitsSold']);
        Route::get('/profit', [DashboardController::class, 'getProfit']);
        Route::get('/client-stats', [DashboardController::class, 'getClientStats']);
        Route::get('/popular-products', [DashboardController::class, 'getPopularProducts']);
        Route::get('/analytics-data', [DashboardController::class, 'getAnalyticsData']);
        Route::get('/employee-sales-amount', [DashboardController::class, 'getEmployeeSalesByAmount']);
        Route::get('/employee-sales-units', [DashboardController::class, 'getEmployeeSalesByUnits']);
        Route::get('/expiring-sold-products', [DashboardController::class, 'getSoldExpiringProducts']);
        Route::get('/minimarket-stats', [DashboardController::class, 'getMinimarketStats']);
    });

    // Rutas de Trazabilidad
    Route::prefix("sales/report")->controller(TraceabilityController::class)->group(function () {
        Route::get("/", "index")->name("api.sales.report.index");
        Route::get("/filterByPsychotropics", "filterByPsychotropics");
        Route::get("/export", "export")->name("api.sales.report.export");
        Route::get("/movement/{movement}", "getMovementDetails")->name("api.sales.report.movement.details");
        Route::post("/register-baseline-adjustments", "registerBaselineAdjustments");
    });

    // Rutas de CRM
    Route::prefix("crm")->group(function () {
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

            Route::prefix("specialties")->group(function () {
                Route::get("/", [SpecialtyController::class, "index"]);
            });

        Route::prefix("companies")->group(function () {
            Route::post("/", [CompanyController::class, "create"]);
            Route::get("/", [CompanyController::class, "consultAll"]);
            Route::get("/{id}", [CompanyController::class, "consultById"]);
            Route::delete("/{id}", [CompanyController::class, "deleteById"]);
            Route::post("/edit/{id}", [CompanyController::class, "edit"]);
            Route::post("/filtrar", [CompanyController::class, "filtrar"]);
            // Route::post("/filtrar-cliientes", [CompanyController::class, "filtrarClientes"]);
            Route::post("/filtrar-sin-paginar", [CompanyController::class, "filtrarSinPaginar"]);
            Route::get("/exportar/excel", [CompanyController::class, "exportarExcel"]);
        });

        Route::prefix("clients")->group(function () {
            Route::post("/", [ClientController::class, "create"]);
            Route::get("/", [ClientController::class, "consultAll"]);
            Route::get("/identification/{identification}", [ClientController::class, "consultByIdentification"]);
            Route::get("/{id}/stats", [ClientController::class, "stats"]);
            Route::get("/{id}", [ClientController::class, "consultById"]);
            Route::delete("/{id}", [ClientController::class, "deleteById"]);
            Route::post("/edit/{id}", [ClientController::class, "edit"]);
            Route::post("/{id}/update-company/{company_id}", [ClientController::class, "updateCompany"]);
            Route::post("/filtrar", [ClientController::class, "filtrar"]);
            Route::post("/filtrar-sin-paginar", [ClientController::class, "filtrarSinPaginar"]);
            Route::post("/count", [ClientController::class, "countByDateRange"]);
            Route::get("/exportar/excel", [ClientController::class, "exportarExcel"]);
            Route::post("/bulk-cleanup", [ClientController::class, "bulkCleanup"]);
            Route::post("/cne-verify", [ClientController::class, "verifyCne"]);
            Route::post("/bulk-cne-verify", [ClientController::class, "bulkVerifyCne"]);
        });

        Route::prefix("lottery")->group(function () {
            Route::post("/filtrar-ordenes-sin-paginar", [LotteryController::class, "filtrarOrdenesWithoutPaginate"]);
            Route::post("/filtrar-ordenes", [LotteryController::class, "filtrarOrdenesPaginate"]);
        });
    });

    Route::prefix('rrhh')->group(function () {
        Route::prefix('employee-performance')->group(function () {
            Route::get('/', [EmployeePerformanceController::class, 'index']);
            Route::post('/lock', [EmployeePerformanceController::class, 'lockMonth']);
        });
        Route::prefix('employees')->group(function () {
            Route::get('/', [EmployeeController::class, 'list']);
            Route::post('/', [EmployeeController::class, 'store']);
            Route::get('/{employee}', [EmployeeController::class, 'profile']);
            Route::get('/{employee}/vouchers', [EmployeeController::class, 'getVouchers']);
            Route::post('/{employee}/voucher', [EmployeeController::class, 'storeVoucher']);
            Route::delete('/{employee}', [EmployeeController::class, 'deleteEmployee']);
            Route::post('/{employee}/documents', [EmployeeController::class, 'storeDocuments']);
            Route::get('/{employee}/download/{file}', [EmployeeController::class, 'downloadDocument']);
            Route::delete('/vouchers/{voucher}', [EmployeeController::class, 'deleteVoucher']);
            Route::put('/{employee}', [EmployeeController::class, 'update']);
            Route::put('/{employee}/fire', [EmployeeController::class, 'fire']);
            Route::put('/{employee}/reset-2fa', [EmployeeController::class, 'reset2FA']);
            Route::get('/{employee}/performance', [EmployeePerformanceController::class, 'getPerformance']);
            Route::get('/{employee}/payments', [EmployeeController::class, 'getPayments']);
            Route::post('/{employee}/payments', [EmployeeController::class, 'storePaymentCalculation']);
            Route::put('/{employee}/payroll-settings', [EmployeeController::class, 'updatePayrollSettings']);
            Route::post('/{employee}/health-consumption', [EmployeeController::class, 'setHealthConsumption']);
        });

        Route::prefix('social-benefits')->group(function () {
            Route::get('/employees', [SocialBenefitController::class, 'index']);
            Route::post('/employees/{employee}/payment', [SocialBenefitController::class, 'payment']);
            Route::get('/employees/{employee}/settlement-data', [SocialBenefitController::class, 'getSettlementData']);
            Route::get('/employees/{employee}/download-settlement', [SocialBenefitController::class, 'downloadSettlement']);
            Route::post('/employees/{employee}/upload-signed-settlement', [SocialBenefitController::class, 'uploadSignedSettlement']);
            Route::get('/employees/{employee}/download-signed-settlement', [SocialBenefitController::class, 'downloadSignedSettlement']);
            Route::post('/employees/{employee}/fire', [SocialBenefitController::class, 'fire']);
        });

        Route::prefix('resignations')->group(function () {
            Route::post('/generate', [ResignationController::class, 'generateResignation']);
            Route::get('/', [ResignationController::class, 'listResignations']);
            Route::get('/stats', [ResignationController::class, 'getStats']);
            Route::put('/toggle-employee-status', [ResignationController::class, 'toggleEmployeeStatus']);
            Route::get('/{id}/download-pdf', [ResignationController::class, 'downloadResignationPdf']);
            Route::get('/{id}/edit', [ResignationController::class, 'getResignationForEdit']);
            Route::get('/employee/{employeeId}/edit', [ResignationController::class, 'getResignationForEditByEmployee']);
            Route::delete('/{id}', [ResignationController::class, 'deleteResignation']);
        });
    });

    Route::get('/roles', [RoleController::class, 'list']);
    Route::apiResource('employee-laboratories', EmployeeLaboratoryController::class);

    Route::prefix("orders")->group(function () {
        Route::get("/psychotropics/pagination", [OrderController::class, "filtrarOrderPorpsychotropicsConPaginacion"]);
    });

    // Route::prefix("expenses")->group(function () {
//     Route::post("/", [ExpensesController::class, "filterWithoutPaginate"]);
//     Route::post("/create", [ExpensesController::class, "createExpense"]);
//     Route::post("/edit/{id}", [ExpensesController::class, "editExpense"]);
//     Route::post("/filter-paginate", [ExpensesController::class, "filterWithPaginate"]);
//     Route::post("/exportar/excel", [ExpensesController::class, "exportExcel"]);
//     Route::post("/change-status", [ExpensesController::class, "changeStatus"]);
//     Route::post("/upload-file-invoice", [ExpensesController::class, "uploadFileInvoice"]);
//     Route::prefix("category")->group(function () {
//         Route::get("/", [ExpenseCategoryController::class, "getAll"]);
//     });
// });

    // Ruta de fiscal
    Route::get("/history", [FiscalController::class, "index"]);
    Route::get("/history/export", [FiscalController::class, "export"]);

    // Invoice
    Route::prefix('invoices')->name('invoices.')->controller(InvoiceController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::post('/match-barcode', 'matchBarcode')->name('match-barcode');
        Route::get('/{invoice}/details', 'getDetails')->name('details');
        Route::get('/{invoice}/suggested-details', 'getSuggestedDetails')->name('suggested-details');
        Route::put('/{invoice}/data', 'updateData')->name('updateData');
        Route::post('/{invoice}/approve', 'approve')->name('approve');
        Route::post('/{invoice}/reject', 'reject')->name('reject');
        Route::put('/{invoice}/return-pending', 'returnInvoiceToPendingStatus')->name('return.pending');
        Route::put('/{invoice}/locations', 'updateLocations')->name('locations.update');
        Route::get('/next-sequence', 'nextSequence')->name('next-sequence');
        Route::get('/{invoice}', 'show')->name('show');
        Route::put('/{invoice}/save-details', 'saveDetails')->name('details.save');
        Route::put('/{invoice}/finalize', 'finalize')->name('finalize');
        Route::post('/bulk-delete', 'bulkDelete')->name('bulk-delete');
        Route::post('/sync-all', 'syncAll')->name('sync-all');
        Route::post('/sync-dronena', 'syncDronena')->name('sync-dronena');
        Route::post('/sync-drocerca', 'syncDrocerca')->name('sync-drocerca');
        Route::post('/sync-mafarta', 'syncMafarta')->name('sync-mafarta');
        Route::post('/sync-cristmedicals', 'syncCristmedicals')->name('sync-cristmedicals');
        Route::post('/sync-dromega', 'syncDromega')->name('sync-dromega');
        Route::post('/sync-drosymca', 'syncDrosymca')->name('sync-drosymca');
        Route::delete('/{invoice}', 'destroy')->name('destroy');

        Route::put('/{invoice}', 'update')->name('update');
        Route::get('/supplier/debts', [InvoiceController::class, 'getSupplierDebts']);
        Route::post('/{invoice}/photo', 'uploadPhoto')->name('photo.upload');
    });

    // Devoluciones de Facturas
    Route::prefix('invoice-returns')->name('invoice-returns.')->controller(InvoiceReturnController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::patch('/{id}/status', 'updateStatus')->name('updateStatus');
        Route::patch('/invoice/{invoiceId}/status', 'updateInvoiceStatus')->name('updateInvoiceStatus');
    });

    // Rutas de Proveedores
    Route::get('/suppliers/stats', [SupplierController::class, 'stats']);
    Route::resource("suppliers", SupplierController::class)->except(["create", "edit", "show"]);
    Route::prefix("suppliers")->group(function () {
        Route::get("/{supplier}/connection", [SupplierController::class, "connectionServiceSupplier"]);
        Route::get("/supplier-connection-statuses", [SupplierController::class, "getConnectionStatus"]);
        Route::post("/{supplier}/payment-rules", [SupplierController::class, "storePaymentRules"]);
        Route::get("/{supplier}/payment-rules", [SupplierController::class, "getPaymentRules"]);
        Route::post("/{supplier}/laboratories", [SupplierController::class, "storeLaboratory"]);
        Route::get("/{supplier}/laboratories", [SupplierController::class, "getLaboratoryLinks"]);
        Route::get("/{supplier}/pending-invoices", [SupplierController::class, "getPendingInvoices"]);
        Route::post("/{supplier}/discounts", [SupplierController::class, "storeDiscounts"]);
        Route::get("/{supplier}/discounts", [SupplierController::class, "getDiscounts"]);
        Route::get("/{supplier}/products", [SupplierController::class, "getSupplierProducts"]);
        Route::get("/connections", [SupplierController::class, "getSupplierConnections"]);
        Route::get("available-products", [SupplierController::class, "getProducts"]);
        Route::get("available-laboratories", [SupplierController::class, "getLaboratories"]);
        Route::get("available-suppliers", [SupplierController::class, "getSuppliers"]);
        Route::post("add-product-to-order", [SupplierController::class, "addProductToOrder"]);
        Route::post("/{supplier}/import", [SupplierController::class, "importData"]);
        Route::delete("/{supplier}/delete-products", [SupplierController::class, "deleteProducts"]);
        Route::get('/{supplier}/first-connection', [SupplierController::class, 'getSupplierFirstConnection']);
        Route::post('/{supplier}/apply-discount', [SupplierController::class, 'applyGlobalDiscount']);
        Route::post('/products/delete-old', [SupplierController::class, 'deleteOldProducts']);
        Route::post('/update-all-job', [SupplierController::class, 'dispatchUpdateAllJob']);
        Route::patch('/{id}/toggle-order', [SupplierController::class, 'toggleOrder']);
        Route::get('/disabled', [SupplierController::class, 'getDisabledSuppliers']);
        Route::patch('/{supplier}/toggle-status', [SupplierController::class, 'toggleSupplierStatus']);
        Route::patch('/product-suppliers/{productSupplier}/toggle-status', [SupplierController::class, 'toggleProductSupplierStatus']);
        Route::post("/{supplier}/generate-public-token", [SupplierController::class, "generatePublicToken"]);
        // Rutas de configuración FTP/API autoadministrable
        Route::get('/{supplier}/connection-config', [SupplierController::class, 'getConnectionConfig']);
        Route::post('/{supplier}/connection-config', [SupplierController::class, 'saveConnectionConfig']);
    });

    Route::prefix("suppliers/purchase-orders")->group(function () {
        Route::get("/stats", [PurchaseOrderController::class, "getStats"]);
        Route::post("/{autoOrder}/confirm-sent", [PurchaseOrderController::class, "confirmSent"]);
        Route::post('/{autoOrder}/resend-ftp', [PurchaseOrderController::class, 'resendFtp']);
        Route::post('/{autoOrder}/finish', [PurchaseOrderController::class, 'finish']);
        Route::post("/{autoOrder}/revert-to-sent", [PurchaseOrderController::class, "revertToSent"]);
        Route::get("/", [PurchaseOrderController::class, "getPurchaseOrders"]);
        Route::get("/{autoOrder}/export", [PurchaseOrderController::class, "getExportData"]);
        Route::delete("/{autoOrder}", [PurchaseOrderController::class, "destroy"]);
        Route::put("/{autoOrder}", [PurchaseOrderController::class, "updateDetails"]);
        Route::get("/history", [PurchaseOrderController::class, "getPurchaseOrderHistory"]);
        Route::get("/{autoOrder}", [PurchaseOrderDetailController::class, "getPurchaseOrderDetails"]);
        Route::put("/details/update-status/{autoOrderDetail}", [PurchaseOrderDetailController::class, "updateDetailStatus"]);
        Route::delete("/details/{autoOrderDetail}", [PurchaseOrderDetailController::class, "destroy"]);
        Route::get("/history/{autoOrder}", [PurchaseOrderDetailController::class, "getPurchaseOrderDetailsHistory"]);
        Route::post("/{autoOrder}/reject-pending", [PurchaseOrderController::class, "rejectPendingDetails"]);
    });

    Route::prefix("suppliers/purchase-orders-laboratory")->group(function () {
        Route::get("/stats", [\App\Http\Controllers\Api\PurchaseOrderByLaboratoryController::class, "getStats"]);
        Route::get("/", [\App\Http\Controllers\Api\PurchaseOrderByLaboratoryController::class, "getLaboratories"]);
        Route::get("/{laboratoryId}/details", [\App\Http\Controllers\Api\PurchaseOrderByLaboratoryController::class, "getDetails"]);
        Route::get("/{laboratoryId}/export", [\App\Http\Controllers\Api\PurchaseOrderByLaboratoryController::class, "getExportData"]);
    });

    Route::prefix("supplier-laboratories")->group(function () {
        Route::get("/{supplier}/discount-rules", [SupplierLaboratoryController::class, "getDiscountRules"]);
        Route::post("/{supplier}/discount-rules", [SupplierLaboratoryController::class, "storeDiscountRule"]);
    });

    // Asistente IA
    Route::prefix("suppliers-ia-order-assistant")->group(function () {
        Route::post("/filtrar-paginate", [SuppliersIaOrderAssistantController::class, "filtrarPaginate"]);
        Route::post("/stats", [SuppliersIaOrderAssistantController::class, "stats"]);
        Route::get("/products-without-supplier", [SuppliersIaOrderAssistantController::class, "getProductosMarcados"]);
        Route::post("/products-without-supplier/{id}/toggle-scarce", [SuppliersIaOrderAssistantController::class, "toggleScarce"]);
        Route::post("/direct-order", [SuppliersIaOrderAssistantController::class, "directOrder"]);
        Route::prefix("generate-order")->group(function () {
            Route::post("/creat", [SuppliersIaOrderAssistantController::class, "generarOrden"]);
            Route::post("/products-to-request", [SuppliersIaOrderAssistantController::class, "generateListProductoToRequest"]);
            Route::post("/products-without-supplier", [SuppliersIaOrderAssistantController::class, "consultarProductosSinProveedor"]);
            Route::post('/unique-opportunity-page', [SuppliersIaOrderAssistantController::class, 'getUniqueOpportunityPagination']);
            Route::post("/products-replenish-page", [SuppliersIaOrderAssistantController::class, "getReplenishPagination"]);
        });
        // Acciones Asistente IA
        Route::post("/add-to-order", [IaAssistantActionController::class, "addToOrder"]);
        Route::post("/add-multiple-to-order", [IaAssistantActionController::class, "addMultipleToOrder"]);
        Route::post("/products/{product}/ignore", [IaAssistantActionController::class, "ignore"]);
        Route::post("/products/{product}/update-manual-quantity", [IaAssistantActionController::class, "updateManualQuantity"]);
        Route::post("/products/{product}/update-barcode", [IaAssistantActionController::class, "updateBarcode"]);
        Route::post("/clear-ignored", [IaAssistantActionController::class, "clearIgnored"]);
        Route::get("/exportar-colombianos", [SuppliersIaOrderAssistantController::class, "exportarColombianos"]);
    });

    Route::prefix("suppliers-ia-assistant-report")->group(function () {
        Route::post('/filtrar-paginate', [SupplierIaAssistantReportController::class, 'filtrarPaginate']);
        Route::post('/filtrar-without-paginate', [SupplierIaAssistantReportController::class, 'filtrarWithoutPaginate']);
        Route::post('/stats', [SupplierIaAssistantReportController::class, 'stats']);
        Route::post('/exportar/excel', [SupplierIaAssistantReportController::class, 'exportarExcel']);
        Route::get('/consult-products', [SupplierIaAssistantReportController::class, 'consultProduct']);
        Route::post('/clear-ignore-until', [SupplierIaAssistantReportController::class, 'clearIgnoreUntil']);
    });

    // Rutas de feedback para el sistema de matching IA
    Route::prefix("supplier-ai-match")->group(function () {
        Route::post('/reject', [SupplierAiMatchController::class, 'reject']);
        Route::post('/accept', [SupplierAiMatchController::class, 'accept']);
    });

    // Rutas para configuración de automatización de pedidos (Auto-Replenishment)
    Route::apiResource('auto-replenishment-configs', AutoReplenishmentConfigController::class)->parameters([
        'auto-replenishment-configs' => 'config',
    ]);
    Route::post('auto-replenishment-configs/{config}/run', [AutoReplenishmentConfigController::class, 'run']);

    Route::prefix("market-opportunities")->group(function () {
        Route::get("/", [MarketOpportunityController::class, "index"]);
        Route::get("/export", [MarketOpportunityController::class, "export"]);
    });
    Route::prefix("bi")->group(function () {
        Route::get("/abc", [\App\Http\Controllers\Api\Bi\AbcReportController::class, "generateReport"]);
        Route::get("/sku-margin", [\App\Http\Controllers\Api\Bi\SkuReportController::class, "generateReport"]);
        Route::get("/sku-margin/export", [\App\Http\Controllers\Api\Bi\SkuReportController::class, "export"]);
        
        // BI: Reportes de Productos
        Route::get("/products/dashboard", [\App\Http\Controllers\Api\Bi\ProductMasterReportController::class, "getDashboard"]);
        Route::get("/products/trends", [\App\Http\Controllers\Api\Bi\ProductMasterReportController::class, "getTrends"]);
        Route::get("/products/cross-selling", [\App\Http\Controllers\Api\Bi\ProductMasterReportController::class, "getCrossSelling"]);
        Route::get("/products/rankings", [\App\Http\Controllers\Api\Bi\ProductMasterReportController::class, "getRankings"]);

        // BI: Reportes de TPV
        Route::get("/pos/dashboard", [\App\Http\Controllers\Api\Bi\PosAnalyticsReportController::class, "index"]);

        // BI: Reportes de Laboratorios
        Route::prefix('laboratories')->group(function () {
            Route::get('/dashboard', [\App\Http\Controllers\Api\Bi\LaboratoryMasterReportController::class, 'index']);
            Route::get('/rankings', [\App\Http\Controllers\Api\Bi\LaboratoryMasterReportController::class, 'getRankings']);
            Route::get('/{id}/deep-dive', [\App\Http\Controllers\Api\Bi\LaboratoryMasterReportController::class, 'getDeepDive']);
            Route::get('/benchmarking', [\App\Http\Controllers\Api\Bi\LaboratoryMasterReportController::class, 'getBenchmarking']);
            Route::get('/catalogs', [\App\Http\Controllers\Api\Bi\LaboratoryMasterReportController::class, 'getFilterCatalogs']);
        });

        // Dashboard de Vencimientos
        Route::get("/expiry", [\App\Http\Controllers\Api\Bi\ExpiryReportController::class, "index"]);

        // Reporte de Devoluciones a Proveedores — canje preventivo por vencimiento 90 días
        Route::get("/supplier-returns", [\App\Http\Controllers\Api\Bi\SupplierReturnsController::class, "index"]);

        // Dashboard de Inventarios Cíclicos
        Route::get("/inventory-cyclic", [\App\Http\Controllers\Api\Bi\InventoryCyclicReportController::class, "index"]);

        // BI: Reportes de Descuentos y Promociones
        Route::get("/discounts/dashboard", [\App\Http\Controllers\Api\Bi\DiscountReportController::class, "dashboard"]);
        Route::get("/discounts/audit", [\App\Http\Controllers\Api\Bi\DiscountReportController::class, "audit"]);

        // BI: Analítica de Clientes (Lifecycle & RFM)
        Route::get("/customers/dashboard", [\App\Http\Controllers\Api\Bi\CustomerAnalyticsController::class, "index"]);

        // BI: Balanced Scorecard de Empleados (Talento & Productividad)
        Route::prefix('employees')->group(function () {
            Route::get('/dashboard', [\App\Http\Controllers\Api\Bi\EmployeeAnalyticsController::class, 'index']);
            Route::get('/compare', [\App\Http\Controllers\Api\Bi\EmployeeAnalyticsController::class, 'compare']);
            Route::get('/{id}/detail', [\App\Http\Controllers\Api\Bi\EmployeeAnalyticsController::class, 'detail']);
        });
    });
    
    Route::prefix("users")->group(function () {
        Route::get("/", [UserController::class, "getAll"]);
    });
    // Finanzas
    Route::prefix("finances")->group(function () {
        Route::get('/reports/abc-analysis', \App\Http\Controllers\Api\ProductAbcReportController::class);
        Route::prefix("profitability")->group(function () {
            Route::get("/", [ProfitabilityController::class, "consultOne"]);
            Route::get("/products", [ProfitabilityController::class, "getProductsForProfitability"]);
            Route::post("/store", [ProfitabilityController::class, "store"]);
            Route::post("/{id}", [ProfitabilityController::class, "edit"]);
            Route::prefix("product")->group(function () {
                Route::post("/toggle-lock", [ProfitabilityController::class, "toggleLock"]);
                Route::get("/{id}", [ProfitabilityController::class, "getProduct"]);
                Route::post("/update", [ProfitabilityController::class, "editProfitabilityProduct"]);
                Route::post("/store", [ProfitabilityController::class, "storeProfitabilityProduct"]);
            });
        });
        Route::prefix("exchange-rates")->group(function () {
            Route::get("/", [ExchangeRateController::class, "consultAll"]);
            Route::get("/apiDollar", [ExchangeRateController::class, "apiDollar"]);
            Route::post("/store", [ExchangeRateController::class, "store"]);
            Route::get("/consultOneCOP", [ExchangeRateController::class, "consultOneCOP"]);
            Route::get("/consultOneBCV", [ExchangeRateController::class, "consultOneBCV"]);
            Route::get("/consultOneBINANCE", [ExchangeRateController::class, "consultOneBINANCE"]);
            Route::get("/consultOneEUR", [ExchangeRateController::class, "consultOneEUR"]);
            Route::get("/consultOneCOPC", [ExchangeRateController::class, "consultOneCOPC"]);
            Route::get("/consultOneBsCOP", [ExchangeRateController::class, "consultOneBsCOP"]);
            Route::get("/consultOneCOPS", [ExchangeRateController::class, "consultOneCOPS"]);
            Route::post("/updateBCVDollar", [ExchangeRateController::class, "updateBCVDollar"]);
        });

        // pending payments
        Route::prefix("pending-payments")->group(function () {
            Route::get('/credito-fiscal', [PendingPaymentsController::class, 'getCreditoFiscal']);
            Route::get("/", [PendingPaymentsController::class, "index"]);
            Route::get("/statistics", [PendingPaymentsController::class, "getStatistics"]);
            Route::get("/suppliers", [PendingPaymentsController::class, "getSuppliers"]);
            Route::get("/supplier/{supplierId}/invoices", [PendingPaymentsController::class, "getSupplierInvoices"]);
            Route::post("/process-payment", [PendingPaymentsController::class, "processPayment"]);
            Route::post("/upload-receipt", [PendingPaymentsController::class, "uploadReceipt"]);
            Route::post("/get-paid-amount", [PendingPaymentsController::class, "getPaidAmount"]);
            Route::get('expenses-history', [PendingPaymentsController::class, 'getExpensesHistory']);
            Route::patch('/invoices/{invoiceId}/update-date', [PendingPaymentsController::class, 'updatePaymentDate']);
            Route::patch('/invoices/{invoiceId}/mark-as-paid', [PendingPaymentsController::class, 'markAsPaidDirectly']);
            Route::post('/invoices/bulk-mark-as-paid', [PendingPaymentsController::class, 'bulkMarkAsPaid']);
            Route::post('/invoices/bulk-mark-as-pending', [PendingPaymentsController::class, 'bulkMarkAsPending']);
        });


        // ISSUE #3: Rutas para facturas indexadas
        Route::prefix("invoices")->group(function () {
            Route::put("/{invoiceId}/toggle-indexed", [PendingPaymentsController::class, "toggleIndexedStatus"]);
        });

        // payment history
        Route::prefix("payment-history")->group(function () {
            Route::get("/", [PendingPaymentsController::class, "getPaymentHistory"]);
        });

        Route::prefix('transactions')->group(function () {
            Route::get('', [TransactionController::class, 'getAll']);
            Route::get('/stats', [TransactionController::class, 'getByType']);
            Route::get('/wallets', [TransactionController::class, 'getWallets']);
            Route::get('/income-summary', [TransactionController::class, 'getIncomeSummary']);
            Route::get('/export/excel', [TransactionController::class, 'exportExcel']);
            Route::get('/cash-status', [TransactionController::class, 'getCashStatus']);
            Route::post('/adjustment', [TransactionController::class, 'adjustBalance']);
        });

        Route::prefix('payslips')->group(function () {
            Route::get('', [PayslipController::class, 'index']);
            Route::post('', [PayslipController::class, 'store']);
            Route::post('/regenerate-history', [PayslipController::class, 'regenerateHistory']);
            Route::put('/{payslip}/finalize', [PayslipController::class, 'finalize']);
            Route::put('/{payslip}/reopen', [PayslipController::class, 'reopen']);
            Route::delete('/{payslip}', [PayslipController::class, 'destroy']);
            Route::get('/{payslip}/download/excel', [PayslipController::class, 'downloadExcel']);
            Route::get('/{payslip}/download/pdf', [PayslipController::class, 'downloadPdf']);
            Route::get('/download-bulk-pdf', [PayslipController::class, 'downloadBulkPdf']);
            Route::get('/{payslip}/data/{type}', [PayslipController::class, 'getData']);
            Route::put('/{payslip}/vouchers', [PayslipController::class, 'updateVouchers']);
            Route::get('/{payslip}/employees/{employee}/vouchers', [PayslipController::class, 'getVouchers']);
        });

        Route::prefix('process-audits')->group(function () {
            Route::get('', [ProcessAuditController::class, 'index']);
            Route::post('', [ProcessAuditController::class, 'store']);
            Route::get('flows', [ProcessAuditController::class, 'indexFlows']);
            Route::post('flows', [ProcessAuditController::class, 'storeFlow']);
            Route::delete('flows/{id}', [ProcessAuditController::class, 'destroyFlow']);
        });

        Route::prefix("cash-closure")->group(function () {
            Route::get("/", [CashClosureController::class, "getCashClosure"]);
            Route::get('/closingHistory', [CashClosureController::class, 'getClosingHistory']);
            Route::post('/generate-pdf', [CashClosureController::class, 'generate'])->name('api.cashClosure.generatePdf');
            Route::post("/close", [CashClosureController::class, "closeCash"]);
            Route::get('/orders', [CashClosureController::class, 'getCashClosureOrders']);
            Route::get('/sales/summary', [CashClosureController::class, 'getSummarysales']);
            Route::get('/dailyCash', [CashClosureController::class, 'getDailyCashTable']);
            Route::get('/monthlyCash', [CashClosureController::class, 'getMonthlyCashTable']);
            Route::get('/sellerCash', [CashClosureController::class, 'getSellerCashTable']);
            Route::get('/sellers', [CashClosureController::class, 'getSellersWithClosures']);
            Route::get('/monthlyCashclosing', [CashClosureController::class, 'getmonthlyCashclosing']);
            Route::post('/downloadReport', [CashClosureController::class, 'downloadReport']);
            Route::post('/PrintReport', [CashClosureController::class, 'printdReport']);
            Route::get('/monthlyCashclosingAllSellers', [CashClosureController::class, 'getmonthlyCashclosingAllSellers']);
            Route::patch('/confirm-reference', [CashClosureController::class, 'confirmReference']);
            Route::patch('/update-blind-amounts', [CashClosureController::class, 'updateBlindAmounts']);
            Route::post('/mismatches/accept', [\App\Http\Controllers\Api\Finance\MismatchManagementController::class, 'acceptMismatch']);
            Route::get('/download-pdf/{id}', [CashClosureController::class, 'downloadDirectPdf']);
            Route::get('/{id}', [CashClosureController::class, 'show']);
        });

        Route::prefix("expenses")->group(function () {
            Route::post("/", [ExpensesController::class, "filterWithoutPaginate"]);
            Route::post("/create-normal", [ExpensesController::class, "createExpense"]);
            //Route::post("/create-recurrence", [ExpensesController::class, "createExpenseRecurrente"]);
            Route::post("/edit/{id}", [ExpensesController::class, "editExpense"]);
            Route::post("/filter-paginate", [ExpensesController::class, "filterWithPaginate"]);
            Route::post("/stats", [ExpensesController::class, "getStats"]);
            Route::post("/exportar/excel", [ExpensesController::class, "exportExcel"]);
            Route::post("/change-status", [ExpensesController::class, "changeStatus"]);
            Route::post("/upload-file-invoice", [ExpensesController::class, "uploadFileInvoice"]);
            Route::prefix("category")->group(function () {
                Route::get("/", [ExpenseCategoryController::class, "getAll"]);
                Route::post("/", [ExpenseCategoryController::class, "store"]);
                Route::put("/{id}", [ExpenseCategoryController::class, "update"]);
                Route::delete("/{id}", [ExpenseCategoryController::class, "destroy"]);
            });
        });

        // Balance General
        Route::get("/balance-general", [App\Http\Controllers\Api\Accounting\BalanceController::class, "index"]);
    });
    Route::prefix('furniture')->name('furniture.')->controller(FurnitureController::class)->group(function () {
        Route::get('/value', 'getValue')->name('value');
        Route::get('/depreciation', 'getDepreciation')->name('depreciation');
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::put('/{furniture}', 'update')->name('update');
        Route::delete('/{furniture}', 'destroy')->name('delete');
    });

    Route::prefix('loans')->name('loans.')->controller(LoanController::class)->group(function () {
        Route::get('/balance', 'getBalance')->name('balance');
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::post('/{loan}/payments', 'addPayment')->name('addPayment');
        Route::put('/{loan}', 'update')->name('update');
        Route::delete('/{loan}', 'destroy')->name('delete');
    });

    Route::prefix('cleaning-activities')->group(function () {
        Route::get('/', [CleaningActivityController::class, 'index']);
        Route::post('/', [CleaningActivityController::class, 'store']);
        Route::put('/{cleaningActivity}', [CleaningActivityController::class, 'update']);
        Route::delete('/{cleaningActivity}', [CleaningActivityController::class, 'destroy']);
    });

    // Rutas para gestión de laboratorios asignados a empleados
    Route::prefix('employee-laboratories')->group(function () {
        Route::get('/', [EmployeeLaboratoryController::class, 'index']);
        Route::post('/', [EmployeeLaboratoryController::class, 'store']);
        Route::delete('/{employee}/{laboratoryId}', [EmployeeLaboratoryController::class, 'destroy']);
    });

    // Rutas para gestión de productos asignados a empleados
    Route::prefix('employee-products')->group(function () {
        Route::get('/', [EmployeeProductController::class, 'index']);
        Route::post('/', [EmployeeProductController::class, 'store']);
        Route::delete('/{employee}/{productId}', [EmployeeProductController::class, 'destroy']);
        Route::get('/stats', [EmployeeProductController::class, 'stats']);
    });
    Route::prefix('islr')->group(function () {
        Route::get('/summary', [IslrController::class, 'getIslrSummary']);
        Route::get('/gross-income', [IslrController::class, 'getGrossIncome']);
        Route::get('/deductions', [IslrController::class, 'getDeductions']);
        Route::get('/tax-unit', [IslrController::class, 'getTaxUnit']);
        Route::post('/tax-unit', [IslrController::class, 'updateTaxUnit']);

        // Rutas para Declaraciones ISLR
        Route::get('/declarations/latest', [IslrController::class, 'getLatestDeclaration']);
        Route::get('/declarations', [IslrController::class, 'getDeclaration']);
        Route::post('/declarations', [IslrController::class, 'createDeclaration']);
        Route::put('/declarations/{id}', [IslrController::class, 'updateDeclaration']);
        Route::delete('/declarations/{id}', [IslrController::class, 'deleteDeclaration']);
        Route::patch('/declarations/{id}/mark-paid', [IslrController::class, 'markAsPaid']);
        Route::patch('/declarations/{id}/mark-unpaid', [IslrController::class, 'markAsUnpaid']);
    });
    Route::prefix('employee-cleaning-activities')->group(function () {
        Route::get('/', [EmployeeCleaningActivityController::class, 'index']);
        Route::get('/assignments', [EmployeeCleaningActivityController::class, 'assignments']);
        Route::post('/', [EmployeeCleaningActivityController::class, 'store']);
        Route::delete('/{employee}/{activityId}', [EmployeeCleaningActivityController::class, 'destroy']);
        Route::patch('/{employee}/{activityId}/status', [EmployeeCleaningActivityController::class, 'updateStatus']);
        Route::get('/stats', [EmployeeCleaningActivityController::class, 'stats']);
    });


    Route::prefix('my-cleaning-activities')->group(function () {
        Route::get('/', [EmployeeCleaningActivityController::class, 'myActivities']);
        Route::post('/{executionId}/status', [EmployeeCleaningActivityController::class, 'updateMyActivityStatus']);
    });
    // Fiscal Printer Queue (Solicitado desde TPV/Admin)
    Route::prefix('fiscal')->group(function () {
        Route::post('/queue/{order}', [\App\Http\Controllers\Api\FiscalPrinterController::class, 'queue']);
        Route::post('/commands', [\App\Http\Controllers\Api\FiscalPrinterController::class, 'storeCommand']);
    });

    Route::prefix('retentions')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\RetentionController::class, 'index']);
        Route::post('/bulk-generate', [\App\Http\Controllers\Api\RetentionController::class, 'bulkGenerate']);
        Route::post('/batch-generate-all', [\App\Http\Controllers\Api\RetentionController::class, 'batchGenerateAll']);
        Route::post('/omit-until-date', [\App\Http\Controllers\Api\RetentionController::class, 'omitUntilDate']);
        Route::post('/restore-omitted', [\App\Http\Controllers\Api\RetentionController::class, 'restoreOmitted']);
        Route::get('/download', [\App\Http\Controllers\Api\RetentionController::class, 'downloadPdf']);
        Route::get('/{id}', [\App\Http\Controllers\Api\RetentionController::class, 'show']);
        Route::put('/{id}', [\App\Http\Controllers\Api\RetentionController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\RetentionController::class, 'destroy']);
    });
});


Route::prefix('supervisor')->group(function () {
    Route::get('/cleaning-executions', [EmployeeCleaningActivityController::class, 'supervisorExecutions']);
    Route::get('/cleaning-executions/stats', [EmployeeCleaningActivityController::class, 'supervisorStats']);
    Route::post('/cleaning-executions/{executionId}/approve', [EmployeeCleaningActivityController::class, 'approveExecution']);
    Route::post('/cleaning-executions/{executionId}/reject', [EmployeeCleaningActivityController::class, 'rejectExecution']);
    Route::post('/cleaning-executions/{executionId}/cancel', [EmployeeCleaningActivityController::class, 'cancelExecution']);
});


