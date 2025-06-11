<?php

use App\Http\Controllers\Api\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/export', [ProductController::class, 'export']);
Route::get('/laboratories', [ProductController::class, 'getLaboratories']);
Route::get('/origins', [ProductController::class, 'getOrigins']);
