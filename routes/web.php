<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

// Ruta para el archivo de prueba del Estado de Resultados
Route::get('/test-income-statement', function () {
    return response()->file(public_path('test_income_statement.html'));
});

Route::get('{any?}', function () {
    return view('application');
})->where('any', '.*');
