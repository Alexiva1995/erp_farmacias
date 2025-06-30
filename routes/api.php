<?php

use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\LotController;
use App\Http\Controllers\Api\InventoryAdjustmentController;
use App\Http\Controllers\Api\InvestmenController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [LoginController::class, 'login']);

Route::post('/two-factor-challenge', [LoginController::class, 'verify2FA']);


// Rutas protegidas que requieren autenticación
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/logout', [LoginController::class, 'logout']);
});

Route::get('/products', [InvestmenController::class, 'index']);
Route::put('/products/{product}', [InvestmenController::class, 'updateProducts']);
Route::post('/products', [InvestmenController::class, 'store']);
Route::delete('/products/{product}/related/{related_product}', [InvestmenController::class, 'removeRelatedProduct'])
    ->name('products.related.destroy');
Route::delete('/products/{product}', [InvestmenController::class, 'destroy']);
Route::get('/products/export', [InvestmenController::class, 'export']);
Route::get('/laboratories', [InvestmenController::class, 'getLaboratories']);

//Origins

Route::get('/origins', [InvestmenController::class, 'getOrigins']);

//Categories

Route::get('/categories', [InvestmenController::class, 'getCategories']);

//Suppliers

Route::get('/suppliers', [InvestmenController::class, 'getSuppliers']);

//Expirations

Route::get('/products/expirations', [InvestmenController::class, 'getExpirations']);
Route::put('/lots/{lot}/expire', [InvestmenController::class, 'expireLot']);

//Lotes
Route::resource('product-lots', LotController::class)->except(['create', 'edit']);
Route::get('/product-without-lots', [LotController::class, 'productsWithInconsistentStock']);
Route::get('/products-without-lots', [LotController::class, 'productsWithoutLot']);
Route::get('/available-suppliers', [LotController::class, 'availableSuppliers']);

//Inventory
Route::get('/cyclic', [InvestmenController::class, 'getProductAll']);
Route::post('/adjustments/{product}/validate-barcode', [InventoryAdjustmentController::class, 'validateBarcode']);
Route::post('/adjustments/process-count', [InventoryAdjustmentController::class, 'processCount']);

//CRM
Route::prefix("crm")->group(function () {

    // Company
    Route::prefix("companies")->group(function () {
        Route::post("/",                             [CompanyController::class, "create"]);
        Route::get("/",                              [CompanyController::class, "consultAll"]);
        Route::get("/{id}",                          [CompanyController::class, "consultById"]);
        Route::delete("/{id}",                       [CompanyController::class, "deleteById"]);
        Route::post("/edit/{id}",                    [CompanyController::class, "edit"]);
        Route::post("/filrar",                       [CompanyController::class, "filrar"]);
    });

    // Client
    Route::prefix("clients")->group(function () {
        Route::post("/",                             [ClientController::class, "create"]);
        Route::get("/",                              [ClientController::class, "consultAll"]);
        Route::get("/{id}",                          [ClientController::class, "consultById"]);
        Route::delete("/{id}",                       [ClientController::class, "deleteById"]);
        Route::post("/edit/{id}",                    [ClientController::class, "edit"]);
        Route::post("/filrar",                       [ClientController::class, "filrar"]);
    });
});
