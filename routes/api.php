<?php

use App\Http\Controllers\Api\InvestmenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


//Productos

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
