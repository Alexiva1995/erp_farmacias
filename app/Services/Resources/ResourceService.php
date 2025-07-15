<?php

namespace App\Services\Resources;

use App\Models\Category;
use App\Models\Laboratory;
use App\Models\Origin;
use App\Models\Supplier;
use App\Models\ExchangeRate;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ResourceService
{
    /**
     * Obtiene una lista de todos los laboratorios, utilizando caché.
     */
    public function getLaboratories(): Collection
    {
        // 'Cache::remember' revisa si la clave 'resources.laboratories' existe en la caché.
        // Si existe, la devuelve.
        // Si no, ejecuta la función, guarda el resultado en la caché por 24 horas y lo devuelve.
        return Cache::remember('resources.laboratories', now()->addDay(), function () {
            return Laboratory::orderBy('name')->get(['id', 'name']);
        });
    }

    /**
     * Obtiene una lista de todos los orígenes, utilizando caché.
     */
    public function getOrigins(): Collection
    {
        return Cache::remember('resources.origins', now()->addDay(), function () {
            return Origin::orderBy('name')->get(['id', 'name']);
        });
    }

    /**
     * Obtiene una lista de todos los proveedores, utilizando caché.
     */
    public function getSuppliers(): Collection
    {
        return Cache::remember('resources.suppliers', now()->addDay(), function () {
            return Supplier::orderBy('name')->get(['id', 'name']);
        });
    }

    /**
     * Obtiene una lista de todas las categorías, utilizando caché.
     */
    public function getCategories(): Collection
    {
        return Cache::remember('resources.categories', now()->addDay(), function () {
            return Category::orderBy('name')->get(['id', 'name']);
        });
    }

    /**
     * Obtiene una sola tasa, utilizando caché.
     */
    public function getExchangeRate(string $currencyCode): float
    {
        $cacheKey = "resources.exchangeRate_{$currencyCode}";
        // $cachedRate = Cache::remember($cacheKey, now()->addDay(), function () use ($currencyCode) {
        $exchangeRate = ExchangeRate::where('currency_code', $currencyCode)->first();
        if ($exchangeRate) {
            return (float) $exchangeRate->rate;
        }
        return 1.0;
        // });
        return $cachedRate;
    }

    /**
     * Obtiene todas las tasa, utilizando caché.
     */
    public function getAllExchangeRate(): Collection
    {
        return Cache::remember('resources.all_exchange_rates', now()->addDay(), function () {
            return ExchangeRate::orderBy('currency_code')->get(['currency_code', 'rate', 'source']);
        });
    }


    /**
     * Obtiene una lista de todas las categorías, utilizando caché.
     */
    public function getProductByBarcode(string $barcode): Product
    {
        $cacheKey = "product.barcode.{$barcode}";
        //   $product = Cache::remember($cacheKey, now()->addDay(), function () use ($barcode) {
        $foundProduct = Product::where('barcode', $barcode)->first();
        if (!$foundProduct) {
            throw new ModelNotFoundException("Producto con código de barras '{$barcode}' no encontrado.");
        }
        return $foundProduct;
        // });
        $product->loadMissing('laboratory');

        return $product;
    }

}
