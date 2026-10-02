<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/app/{any?}', function ($any = null) {
        $seoTitle = 'Tova - Cerebro Operativo';
        $seoDescription = 'Explora nuestro catálogo de productos y gestiona tu inventario con la plataforma inteligente Tova.';

        return view('application', compact('seoTitle', 'seoDescription'));
    })->where('any', '.*');
});
