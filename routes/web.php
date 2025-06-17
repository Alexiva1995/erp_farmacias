<?php

use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    require base_path('routes/api.php');
});

Route::get('{any?}', function() {
    return view('application');
})->where('any', '.*');
