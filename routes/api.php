<?php

use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\LotController;
use Illuminate\Support\Facades\Route;

Route::get('/products', [ProductController::class, 'index']);
Route::get('/product-lots', [LotController::class, 'index']);
