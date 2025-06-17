<?php

use App\Http\Controllers\Api\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::get('/products', [ProductController::class, 'index']);
Route::put('/products/{product}', [ProductController::class, 'updateProducts']);
Route::post('/products', [ProductController::class, 'store']);
Route::delete('/products/{product}/related/{related_product}', [ProductController::class, 'removeRelatedProduct'])
    ->name('products.related.destroy');
Route::delete('/products/{product}', [ProductController::class, 'destroy']);
Route::get('/products/export', [ProductController::class, 'export']);
Route::get('/laboratories', [ProductController::class, 'getLaboratories']);
Route::get('/origins', [ProductController::class, 'getOrigins']);
Route::get('/categories', [ProductController::class, 'getCategories']);
Route::get('/suppliers', [ProductController::class, 'getSuppliers']);
