<?php

use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\LotController;
use Illuminate\Support\Facades\Route;

Route::get('/products', [ProductController::class, 'index']);
Route::resource('product-lots', LotController::class)->except(['create', 'edit']);
