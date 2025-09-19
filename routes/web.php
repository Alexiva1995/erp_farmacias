<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('{any?}', function () {
    return view('application');
})->where('any', '.*');
