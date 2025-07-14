<?php

use App\Http\Controllers\api\ExchangeRateController;
use App\Http\Controllers\Api\LotController;
use App\Http\Controllers\Api\InventoryAdjustmentController;
use App\Http\Controllers\Api\InvestmenController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Api\ProfitabilityController;
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
        Route::post("/store", [ExchangeRateController::class, "store"]);
    });
});
