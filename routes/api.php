<?php

use App\Http\Controllers\Api\DonationController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\LotController;
use App\Http\Controllers\Api\InventoryAdjustmentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Controllers\Api\ExpirationController;
use App\Http\Controllers\Api\QuotationController;

Route::post('/login', [LoginController::class, 'login']);

Route::post('/two-factor-challenge', [LoginController::class, 'verify2FA']);


// Rutas protegidas que requieren autenticación
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/logout', [LoginController::class, 'logout']);
});

// Products
Route::get('/products', [ProductController::class, 'index']);
Route::put('/products/{product}', [ProductController::class, 'updateProducts']);
Route::post('/products', [ProductController::class, 'store']);
Route::delete('/products/{product}', [ProductController::class, 'destroy']);
Route::get('/products/export', [ProductController::class, 'export']);

// Group Products
Route::get('/groups', [GroupController::class, 'index']);
Route::post('/groups', [GroupController::class, 'store']);
Route::get('/groups/search', [GroupController::class, 'search']);
Route::put('/groups/{group}', [GroupController::class, 'update']);
Route::delete('/groups/{group}', [GroupController::class, 'destroy']);

Route::delete('/products/{product}/unassign-group', [ProductController::class, 'unassignProductFromGroup']);

// Basic Resources
Route::get('/laboratories', [ResourceController::class, 'getLaboratories']);
Route::get('/origins', [ResourceController::class, 'getOrigins']);
Route::get('/categories', [ResourceController::class, 'getCategories']);
Route::get('/suppliers', [ResourceController::class, 'getSuppliers']);

// Expirations
Route::get('/products/expirations', [ExpirationController::class, 'index']);
Route::put('/lots/{lot}/expire', [ExpirationController::class, 'expire']);
Route::post('/lots/expire-multiple', [ExpirationController::class, 'expireMultiple']);
Route::get('/expired-logs/summary', [ExpirationController::class, 'getSummary']);
Route::get('/expired-logs', [ExpirationController::class, 'getLotExpired']);

//Donation 
Route::post('/donations', [DonationController::class, 'create']);

//Lotes
Route::resource('product-lots', LotController::class)->except(['create', 'edit']);
Route::get('/product-without-lots', [LotController::class, 'productsWithInconsistentStock']);
Route::get('/products-without-lots', [LotController::class, 'productsWithoutLot']);
Route::get('/available-suppliers', [LotController::class, 'availableSuppliers']);

//Inventory
Route::get('/cyclic', [ProductController::class, 'getProductAll']);
Route::post('/adjustments/{product}/validate-barcode', [InventoryAdjustmentController::class, 'validateBarcode']);
Route::post('/adjustments/process-count', [InventoryAdjustmentController::class, 'processCount']);

//TPV
//Quotation
Route::get('/quotation', [QuotationController::class, 'index']);
Route::get('/quotation/{product}', [QuotationController::class, 'show']);
Route::get('/quotation/barcode/{barcode}', [ResourceController::class, 'findProductByBarcode']);
