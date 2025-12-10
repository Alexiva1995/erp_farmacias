<?php

use App\Http\Controllers\Api\CleaningActivityController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DonationController;
use App\Http\Controllers\Api\EmployeeCleaningActivityController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\IslrController;
use App\Http\Controllers\Api\EmployeeLaboratoryController;
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
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\SupplierLaboratoryController;
use App\Http\Controllers\Api\FiscalController;
use App\Http\Controllers\Api\InventoryStockController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PendingPaymentsController;
use App\Http\Controllers\Api\CreditsController;
use App\Http\Controllers\Api\ExpenseCategoryController;
use App\Http\Controllers\Api\ExpensesController;
use App\Http\Controllers\Api\SupplierIaAssistantReportController;
use App\Http\Controllers\Api\SuppliersIaOrderAssistantController;
use App\Http\Controllers\Api\UserController;
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

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Rutas de autenticación
Route::post("/login", [LoginController::class, "login"]);
Route::post("/two-factor-challenge", [LoginController::class, "verify2FA"]);

// Rutas públicas (no requieren autenticación ni middleware de estado)
Route::get("/public/exchange-rates", [ResourceController::class, "getExchangeRates"]);

// TEMPORAL: Estado de Resultados público para debugging
Route::prefix("finances")->group(function () {
    // income statement (Estado de Resultados) - TEMPORALMENTE PÚBLICO
    Route::get("/income-statement", [FinancialStatementController::class, "index"]);
    Route::get("/income-statement/summary", [FinancialStatementController::class, "getSummary"]);
    Route::get("/income-statement/details", [FinancialStatementController::class, "getDetails"]);
});

// Rutas protegidas que requieren autenticación (Sanctum)
Route::middleware("auth:sanctum")->group(function () {
    Route::get("/user", function (Request $request) {
        return $request->user();
    });
    Route::post("/logout", [LoginController::class, "logout"]);

    // Rutas de Productos
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/productsAll', [ProductController::class, 'getProducts']);
    Route::get('/products/autocomplete', [ProductController::class, 'forAutocomplete']);
    Route::put('/products/{product}', [ProductController::class, 'updateProducts']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);
    Route::get('/products/export', [ProductController::class, 'export']);
    Route::delete('/products/{product}/unassign-group', [ProductController::class, 'unassignProductFromGroup']);
    Route::get('/products/search-by-barcode', [ProductController::class, 'searchByBarcode']);
    Route::get('/products/inventory/value', [ProductController::class, 'getInventoryValue']);

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
    Route::get("/origins", [ResourceController::class, "getOrigins"]);
    Route::get("/categories", [ResourceController::class, "getCategories"]);
    Route::get("/suppliers", [ResourceController::class, "getSuppliers"]);
    Route::get("/products/all", [ResourceController::class, "getAllProducts"]);
    Route::get("/barcode/{barcode}", [ResourceController::class, "findProductByBarcode"]);
    Route::get("/product/{product}", [ResourceController::class, "findProductById"]);

    // Rutas de Expiraciones
    Route::get("/products/expirations", [ExpirationController::class, "index"]);
    Route::get("/products/expirations-all", [ExpirationController::class, "getExpiringAll"]);
    Route::put("/lots/{lot}/expire", [ExpirationController::class, "expire"]);
    Route::post("/lots/expire-multiple", [ExpirationController::class, "expireMultiple"]);
    Route::get("/expired-logs/summary", [ExpirationController::class, "getSummary"]);
    Route::get("/expired-logs", [ExpirationController::class, "getLotExpired"]);
    Route::post("/expirations/adjust-prices/preview", [App\Http\Controllers\Api\ExpirationController::class, "previewPriceAdjustment"]);
    Route::post("/expirations/adjust-expired-prices", [ExpirationController::class, "adjustExpiredProductsPrices"]);
    Route::get("/expirations/month/{month}/adjustment-status", [ExpirationController::class, "checkMonthAdjustmentStatus"]);
    Route::get("/price-adjustments", [ExpirationController::class, "getPriceAdjustmentHistory"]);
    Route::get("/price-adjustments/month/{month}", [ExpirationController::class, "getMonthPriceAdjustments"]);

    // Rutas de Donaciones
    Route::post("/donations", [DonationController::class, "create"]);
    Route::get("/donations/month/{month}/data", [DonationController::class, "getMonthlyDonationData"]);

    // Rutas de Lotes de Productos
    Route::resource('product-lots', LotController::class)->except(['create', 'edit']);
    Route::get('/product-without-lots', [LotController::class, 'productsWithInconsistentStock']);
    Route::get('/products-without-lots', [LotController::class, 'productsWithoutLot']);
    Route::get('/available-suppliers', [LotController::class, 'availableSuppliers']);
    Route::post('/product-lots/batch-update', [LotController::class, 'batchUpdate']);
    Route::get('lots/available-stock/{productId}', [LotController::class, 'getAvailableStock']);
    Route::get('lots/product/{productId}', [LotController::class, 'getProductLots']);

    // Rutas de Inventario
    Route::get("/products/count", [InventoryCycleController::class, "getProductCount"]);
    Route::post("/products/count/{countId}/process", [InventoryCycleController::class, "processCountAction"]);
    Route::prefix("inventory")->group(function () {
        Route::get("cycle/active", [InventoryCycleController::class, "getActiveCycleStatus"])->name("inventory.cycle.active");
        Route::get("products", [InventoryCycleController::class, "getProductsForInventory"])->name("inventory.products.index");
        Route::get("/cash-close-items", [InventoryCycleController::class, "getCashCloseItems"]);
        Route::post("/cycle/close", [InventoryCycleController::class, "closeActiveCycle"]);
        Route::post("/cycle/create", [InventoryCycleController::class, "createCycle"]);
        Route::get('/cycle/summary', [InventoryCycleController::class, 'getCycleSummary']);
        Route::get('/cycle/{cycleId}', [InventoryCycleController::class, 'getCycleInfo']);
        Route::prefix('count')->group(function () {
            Route::get('/invoices/count', [InventoryCycleController::class, 'getInvoiceCount']);
            Route::post('/invoices/{countId}/process', [InventoryCycleController::class, 'processInvoiceCountAction']);
            Route::post('/invoice-count/{productId}', [InventoryCycleController::class, 'storeInvoiceCount']);
            Route::get('/invoice-details-to-count', [InventoryCycleController::class, 'getInvoiceDetailsToCount']);
            Route::get('/', [InventoryCycleController::class, 'getProductCount']);
            Route::post('{product}', [InventoryCycleController::class, 'storeProductCount']);
            Route::post('{countId}/process', [InventoryCycleController::class, 'processCountAction']);
            Route::post('{count}/action', [InventoryCycleController::class, 'processCountAction']);
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
        Route::get("/quotation", [QuotationController::class, "index"]);
        Route::get("/quotation/{product}", [QuotationController::class, "show"]);
        Route::post("/quotations", [QuotationController::class, "store"]);
        Route::get("/quotations/{quotationId}/products", [QuotationController::class, "showProducts"]);
        Route::get("/order", [OrderController::class, "index"]);
        Route::get("/order/client/{Identification}", [OrderController::class, "consultByIdentification"]);
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/order/seller/my-open-order', [OrderController::class, 'getMyOpenOrder']);
        Route::post('/orders/{order}/items', [OrderController::class, 'storeOrderItem']);
        Route::patch('/orders/{order}', [OrderController::class, 'updateOrderTotals']);
        Route::delete('/orders/{order}/items/{item}', [OrderController::class, 'deleteOrderDetail']);
        Route::patch('/orders/{order}/abandon', [OrderController::class, 'abandonOrder']);
        Route::post('/orders/{orderId}/complete', [OrderController::class, 'completeOrder']);
        Route::patch('/order/{order}/reserve', [OrderController::class, 'reserveOrder']);
        Route::patch('/order/{order}/reserveAdd', [OrderController::class, 'reserveAddOrder']);
        Route::get('/orders/cancelled', [OrderController::class, 'getCancelledOrder']);
        Route::get('/orders/completed', [OrderController::class, 'getcompletedOrder']);
        Route::get('/orders/all', [OrderController::class, 'getAllOrder']);
        Route::get('/orders/abandoned', [OrderController::class, 'getAbandonedOrder']);
        Route::get('/orders/{orderId}/print', [OrderController::class, 'getCPrintOrder']);
        Route::patch('/orders/{order}/cancelled', [OrderController::class, 'cancelledOrder']);
        Route::get('/credits', [CreditsController::class, 'index']);
        Route::put('/credits/status', [CreditsController::class, 'updateCreditStatus']);
        Route::post('/credits/complete', [CreditsController::class, 'completeCredits']);
        Route::post('/credits/details', [CreditsController::class, 'showDetails']);
        Route::get('/returns', [ReturnsController::class, 'index']);
        Route::post('/returns/search-orders', [ReturnsController::class, 'searchOrders']);
        Route::post('/returns/product', [ReturnsController::class, 'returnsProduct']);
        Route::patch('/returns/{returnEntryId}/approved', [ReturnsController::class, 'approvedReturn']);
        // Rutas de Promociones
        Route::prefix("promotions")->group(function () {
            Route::prefix("individual")->group(function () {
                Route::get('/', [IndividualOfferController::class, "index"]);
                Route::post('/', [IndividualOfferController::class, "store"]);
                Route::put('/{id}', [IndividualOfferController::class, "update"]);
                Route::delete('/{id}', [IndividualOfferController::class, 'destroy']);
            });
            Route::prefix("category")->group(function () {
                Route::get('/', [CategoryOfferController::class, "index"]);
                Route::post('/', [CategoryOfferController::class, "store"]);
                Route::put('/{id}', [CategoryOfferController::class, "update"]);
                Route::delete('/{id}', [CategoryOfferController::class, 'destroy']);
            });
            Route::prefix("company-offer")->group(function () {
                Route::get('/', [CompanyOfferController::class, "index"]);
                Route::post('/', [CompanyOfferController::class, "store"]);
                Route::put('/{id}', [CompanyOfferController::class, "update"]);
                Route::delete('/{id}', [CompanyOfferController::class, 'destroy']);
            });
            Route::prefix("doctor-offer")->group(function () {
                Route::get('/', [DoctorOfferController::class, "index"]);
                Route::post('/', [DoctorOfferController::class, "store"]);
                Route::put('/{id}', [DoctorOfferController::class, "update"]);
                Route::delete('/{id}', [DoctorOfferController::class, 'destroy']);
            });
            Route::prefix("expiration-offer")->group(function () {
                Route::get('/', [ExpirationOfferController::class, "index"]);
                Route::post('/', [ExpirationOfferController::class, "store"]);
                Route::put('/{id}', [ExpirationOfferController::class, "update"]);
                Route::delete('/{id}', [ExpirationOfferController::class, 'destroy']);
                Route::get('/available-product-lots', [ExpirationOfferController::class, 'getAvailableProductLots']);
            });
            Route::prefix("product-packs")->group(function () {
                Route::get('/', [ProductPackController::class, "index"]);
                Route::post('/', [ProductPackController::class, "store"]);
                Route::get('/{id}', [ProductPackController::class, "show"]);
                Route::put('/{id}', [ProductPackController::class, "update"]);
                Route::delete('/{id}', [ProductPackController::class, 'destroy']);
            });
            Route::prefix("prescription-offer")->group(function () {
                Route::get('/', [PrescriptionOfferController::class, "index"]);
                Route::post('/', [PrescriptionOfferController::class, "store"]);
                Route::get('/{id}', [PrescriptionOfferController::class, "show"]);
                Route::put('/{id}', [PrescriptionOfferController::class, "update"]);
                Route::delete('/{id}', [PrescriptionOfferController::class, 'destroy']);
            });
        });
    });
    Route::get('debito-fiscal', [OrderController::class, 'getDebitoFiscal']);
    Route::get('fiscal-history', [OrderController::class, 'getFiscalHistoryData']);
});

// Rutas de Trazabilidad
Route::prefix("sales/report")->controller(TraceabilityController::class)->group(function () {
    Route::get("/", "index")->name("api.sales.report.index");
    Route::get("/filterByPsychotropics", "filterByPsychotropics");
    Route::get("/export", "export")->name("api.sales.report.export");
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

    Route::prefix("clients")->group(function () {
        Route::post("/", [ClientController::class, "create"]);
        Route::get("/", [ClientController::class, "consultAll"]);
        Route::get("/{id}", [ClientController::class, "consultById"]);
        Route::delete("/{id}", [CompanyController::class, "deleteById"]);
        Route::post("/edit/{id}", [ClientController::class, "edit"]);
        Route::post("/filtrar", [ClientController::class, "filtrar"]);
        Route::post("/filtrar-sin-paginar", [ClientController::class, "filtrarSinPaginar"]);
        Route::get("/exportar/excel", [ClientController::class, "exportarExcel"]);
    });

    Route::prefix("lottery")->group(function () {
        Route::post("/filtrar-ordenes-sin-paginar", [LotteryController::class, "filtrarOrdenesWithoutPaginate"]);
        Route::post("/filtrar-ordenes", [LotteryController::class, "filtrarOrdenesPaginate"]);
    });
});

Route::prefix('rrhh')->group(function () {
    Route::prefix('employees')->group(function () {
        Route::get('/', [EmployeeController::class, 'list']);
        Route::post('/', [EmployeeController::class, 'store']);
        Route::get('/{employee}', [EmployeeController::class, 'profile']);
        Route::get('/{employee}/vouchers', [EmployeeController::class, 'getVouchers']);
        Route::post('/{employee}/voucher', [EmployeeController::class, 'storeVoucher']);
        Route::delete('/{employee}', [EmployeeController::class, 'deleteEmployee']);
        Route::put('/{employee}/documents', [EmployeeController::class, 'storeDocuments']);
        Route::get('/{employee}/download/{file}', [EmployeeController::class, 'downloadDocument']);
        Route::delete('/vouchers/{voucher}', [EmployeeController::class, 'deleteVoucher']);
        Route::put('/{employee}', [EmployeeController::class, 'update']);
        Route::put('/{employee}/fire', [EmployeeController::class, 'fire']);
        Route::put('/{employee}/reset-2fa', [EmployeeController::class, 'reset2FA']);
    });

    Route::prefix('social-benefits')->group(function () {
        Route::get('/employees', [SocialBenefitController::class, 'index']);
        Route::post('/employees/{employee}/payment', [SocialBenefitController::class, 'payment']);
        Route::get('/employees/{employee}/settlement-data', [SocialBenefitController::class, 'getSettlementData']);
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
    Route::get('/{invoice}/details', 'getDetails')->name('details');
    Route::get('/{invoice}/suggested-details', 'getSuggestedDetails')->name('suggested-details');
    Route::put('/{invoice}/data', 'updateData')->name('updateData');
    Route::post('/{invoice}/approve', 'approve')->name('approve');
    Route::post('/{invoice}/reject', 'reject')->name('reject');
    Route::put('/{invoice}/locations', 'updateLocations')->name('locations.update');
    Route::get('/{invoice}', 'show')->name('show');
    Route::put('/{invoice}/save-details', 'saveDetails')->name('details.save');
    Route::put('/{invoice}/finalize', 'finalize')->name('finalize');
    Route::delete('/{invoice}', 'destroy')->name('destroy');
    Route::put('/{invoice}', 'update')->name('update');
    Route::get('/supplier/debts', [InvoiceController::class, 'getSupplierDebts']);
});

// Rutas de Proveedores
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
    Route::post("add-product-to-order", [SupplierController::class, "addProductToOrder"]);
    Route::post("/{supplier}/import", [SupplierController::class, "importData"]);
    Route::delete("/{supplier}/delete-products", [SupplierController::class, "deleteProducts"]);
    Route::get('/{supplier}/first-connection', [SupplierController::class, 'getSupplierFirstConnection']);
    Route::post('/{supplier}/apply-discount', [SupplierController::class, 'applyGlobalDiscount']);
});

Route::prefix("suppliers/purchase-orders")->group(function () {
    Route::get("/", [PurchaseOrderController::class, "getPurchaseOrders"]);
    Route::get("/{autoOrder}/export", [PurchaseOrderController::class, "getExportData"]);
    Route::delete("/{autoOrder}", [PurchaseOrderController::class, "destroy"]);
    Route::put("/{autoOrder}", [PurchaseOrderController::class, "updateDetails"]);
    Route::get("/history", [PurchaseOrderController::class, "getPurchaseOrderHistory"]);
    Route::get("/{autoOrder}", [PurchaseOrderDetailController::class, "getPurchaseOrderDetails"]);
    Route::put("/details/update-status/{autoOrderDetail}", [PurchaseOrderDetailController::class, "updateDetailStatus"]);
    Route::delete("/details/{autoOrderDetail}", [PurchaseOrderDetailController::class, "destroy"]);
    Route::get("/history/{autoOrder}", [PurchaseOrderDetailController::class, "getPurchaseOrderDetailsHistory"]);
});
Route::prefix("supplier-laboratories")->group(function () {
    Route::get("/{supplier}/discount-rules", [SupplierLaboratoryController::class, "getDiscountRules"]);
    Route::post("/{supplier}/discount-rules", [SupplierLaboratoryController::class, "storeDiscountRule"]);
});

// Asistente IA
Route::prefix("suppliers-ia-order-assistant")->group(function () {
    Route::post("/filtrar-paginate", [SuppliersIaOrderAssistantController::class, "filtrarPaginate"]);
    Route::prefix("generate-order")->group(function () {
        Route::post("/creat", [SuppliersIaOrderAssistantController::class, "generarOrden"]);
        Route::post("/products-to-request", [SuppliersIaOrderAssistantController::class, "generateListProductoToRequest"]);
        Route::post("/products-without-supplier", [SuppliersIaOrderAssistantController::class, "consultarProductosSinProveedor"]);
        Route::post('/unique-opportunity-page', [SuppliersIaOrderAssistantController::class, 'getUniqueOpportunityPagination']);
    });
});

Route::prefix("suppliers-ia-assistant-report")->group(function () {
    Route::post('/filtrar-paginate', [SupplierIaAssistantReportController::class, 'filtrarPaginate']);
    Route::post('/filtrar-without-paginate', [SupplierIaAssistantReportController::class, 'filtrarWithoutPaginate']);
    Route::post('/exportar/excel', [SupplierIaAssistantReportController::class, 'exportarExcel']);
    Route::get('/consult-products', [SupplierIaAssistantReportController::class, 'consultProduct']);
});
Route::prefix("users")->group(function () {
    Route::get("/", [UserController::class, "getAll"]);
});
// Finanzas
Route::prefix("finances")->group(function () {
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
    Route::prefix("exchange-rates")->group(function () {
        Route::get("/", [ExchangeRateController::class, "consultAll"]);
        Route::get("/apiDollar", [ExchangeRateController::class, "apiDollar"]);
        Route::post("/store", [ExchangeRateController::class, "store"]);
        Route::get("/consultOneCOP", [ExchangeRateController::class, "consultOneCOP"]);
        Route::get("/consultOneBCV", [ExchangeRateController::class, "consultOneBCV"]);
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
    });

    Route::prefix('payslips')->group(function () {
        Route::get('', [PayslipController::class, 'index']);
        Route::put('/{payslip}/finalize', [PayslipController::class, 'finalize']);
        Route::get('/{payslip}/download/excel', [PayslipController::class, 'downloadExcel']);
        Route::get('/{payslip}/data/{type}', [PayslipController::class, 'getData']);
        Route::put('/{payslip}/vouchers', [PayslipController::class, 'updateVouchers']);
        Route::get('/{payslip}/employees/{employee}/vouchers', [PayslipController::class, 'getVouchers']);
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
        Route::get('/monthlyCashclosing', [CashClosureController::class, 'getmonthlyCashclosing']);
        Route::post('/downloadReport', [CashClosureController::class, 'downloadReport']);
        Route::post('/PrintReport', [CashClosureController::class, 'printdReport']);
        Route::get('/monthlyCashclosingAllSellers', [CashClosureController::class, 'getmonthlyCashclosingAllSellers']);
    });

    Route::prefix("expenses")->group(function () {
        Route::post("/", [ExpensesController::class, "filterWithoutPaginate"]);
        Route::post("/create-normal", [ExpensesController::class, "createExpense"]);
        //Route::post("/create-recurrence", [ExpensesController::class, "createExpenseRecurrente"]);
        Route::post("/edit/{id}", [ExpensesController::class, "editExpense"]);
        Route::post("/filter-paginate", [ExpensesController::class, "filterWithPaginate"]);
        Route::post("/exportar/excel", [ExpensesController::class, "exportExcel"]);
        Route::post("/change-status", [ExpensesController::class, "changeStatus"]);
        Route::post("/upload-file-invoice", [ExpensesController::class, "uploadFileInvoice"]);
        Route::prefix("category")->group(function () {
            Route::get("/", [ExpenseCategoryController::class, "getAll"]);
        });
    });
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
Route::prefix('dashboard')->group(function () {
    Route::get('/revenue-report', [DashboardController::class, 'getRevenueReport']);
    Route::get('/stats', [DashboardController::class, 'getDashboardStats']);
    Route::get('/total-income', [DashboardController::class, 'getTotalIncome']);
    Route::get('/deductible-expenses', [DashboardController::class, 'getDeductibleExpenses']);
    Route::get('/non-deductible-expenses', [DashboardController::class, 'getNonDeductibleExpenses']); // Nueva
});
Route::prefix('employee-cleaning-activities')->group(function () {
    Route::get('/', [EmployeeCleaningActivityController::class, 'index']);
    Route::post('/', [EmployeeCleaningActivityController::class, 'store']);
    Route::delete('/{employee}/{activityId}', [EmployeeCleaningActivityController::class, 'destroy']);
    Route::patch('/{employee}/{activityId}/status', [EmployeeCleaningActivityController::class, 'updateStatus']);
    Route::get('/stats', [EmployeeCleaningActivityController::class, 'stats']);
});
Route::prefix('my-cleaning-activities')->group(function () {
    Route::get('/', [EmployeeCleaningActivityController::class, 'myActivities']);
    Route::post('/{executionId}/status', [EmployeeCleaningActivityController::class, 'updateMyActivityStatus']);
});
Route::prefix('supervisor')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/cleaning-executions', [EmployeeCleaningActivityController::class, 'supervisorExecutions']);
    Route::get('/cleaning-executions/stats', [EmployeeCleaningActivityController::class, 'supervisorStats']);
    Route::post('/cleaning-executions/{executionId}/approve', [EmployeeCleaningActivityController::class, 'approveExecution']);
    Route::post('/cleaning-executions/{executionId}/reject', [EmployeeCleaningActivityController::class, 'rejectExecution']);
    Route::post('/cleaning-executions/{executionId}/cancel', [EmployeeCleaningActivityController::class, 'cancelExecution']);
});
