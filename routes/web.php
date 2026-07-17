<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

// Ruta para el archivo de prueba del Estado de Resultados
Route::get('/test-income-statement', function () {
    return response()->file(public_path('test_income_statement.html'));
});

Route::get('/p/suppliers/upload/{token}', function () {
    return view('application');
});

Route::get('/p/orders/confirm/{hash}', function () {
    return view('application');
});

Route::get('{any?}', function ($any = null) {
    $seoTitle = 'Tova - Cerebro Operativo';
    $seoDescription = 'Explora nuestro catálogo de productos y gestiona tu inventario con la plataforma inteligente Tova.';

    $settings = \DB::table('general_settings')->first();
    $isMinimarket = $settings && $settings->business_type === 'minimarket';

    if (($any && str_contains($any, 'tova-store')) || (!$any && $isMinimarket)) {
        $seoTitle = 'Tova Store - Tienda Online';
        $seoDescription = 'Explora nuestro catálogo exclusivo de productos, promociones y categorías directamente en nuestra tienda online oficial.';
    }

    return view('application', compact('seoTitle', 'seoDescription'));
})->where('any', '.*');
