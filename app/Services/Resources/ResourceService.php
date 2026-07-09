<?php

declare(strict_types=1);

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
     * Obtiene una lista de todas las categorías, utilizando caché y filtros.
     */
    public function getCategories(array $filters = []): Collection
    {
        if (isset($filters['type']) && $filters['type'] === 'dishes') {
            return Cache::remember('resources.categories.dishes', now()->addDay(), function () {
                return Category::whereHas('dishes')
                    ->orWhereDoesntHave('products')
                    ->orderBy('name')
                    ->get(['id', 'name']);
            });
        }

        return Cache::remember('resources.categories', now()->addDay(), function () {
            return Category::orderBy('name')->get(['id', 'name']);
        });
    }

    public function getAllProducts(): Collection
    {
        return Cache::remember('resources.products', now()->addDay(), function () {
            return Product::orderBy('name')->get();
        });
    }
    /**
     * Obtiene una sola tasa, utilizando caché y una estrategia robusta de recuperación (Fallback Cache).
     */
    public function getExchangeRate(string $currencyCode): float
    {
        if ($currencyCode === 'BS' || $currencyCode === 'VES') {
            $isRestaurant = \App\Models\GeneralSetting::first()?->business_type === 'restaurant';
            $currencyCode = $isRestaurant ? 'BINANCE' : 'EUR';
        }

        $cacheKey = "resources.exchange_rate.{$currencyCode}";
        $fallbackKey = "resources.exchange_rate.{$currencyCode}.fallback";

        try {
            // Intentar obtener la tasa fresca o desde la caché con expiración
            $rate = Cache::remember($cacheKey, now()->addHours(1), function () use ($currencyCode, $fallbackKey) {
                $exchangeRate = ExchangeRate::where('currency_code', $currencyCode)->first();
                if ($exchangeRate) {
                    $val = (float) $exchangeRate->rate;
                    // Almacenar en caché persistente (sin expiración) como fallback de seguridad
                    Cache::forever($fallbackKey, $val);
                    return $val;
                }
                
                // Si no hay registro fresco pero hay fallback, usarlo
                if (Cache::has($fallbackKey)) {
                    return (float) Cache::get($fallbackKey);
                }

                return 1.0;
            });

            return $rate;
        } catch (\Exception $e) {
            // Registrar el error para observabilidad detallando el fallo de base de datos/caché
            Log::error("Fallo al recuperar tasa de cambio para '{$currencyCode}' en ResourceService::getExchangeRate. Se utilizará fallback.", [
                'currency_code' => $currencyCode,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Fallback ante cualquier error de red, timeout o fallo de base de datos
            if (Cache::has($fallbackKey)) {
                return (float) Cache::get($fallbackKey);
            }
            return 1.0;
        }
    }

    /**
     * Obtiene todas las tasa, utilizando caché.
     */
    public function getAllExchangeRate(): Collection
    {
        return Cache::remember('resources.all_exchange_rates', now()->addHours(1), function () {
            return ExchangeRate::orderBy('currency_code')->get(['currency_code', 'rate', 'source']);
        });
    }


    /**
     * Obtiene una lista de todas las categorías, utilizando caché.
     */
    public function getProductByBarcode(string $barcode): Product
    {
        $foundProduct = Product::where('barcode', $barcode)->first();
        if (!$foundProduct) {
            throw new ModelNotFoundException("Producto con código de barras '{$barcode}' no encontrado.");
        }

        $foundProduct->loadMissing('laboratory');

        return $foundProduct;
    }

    public function loadProductDetails(Product $product): Product
    {
        $product->load([
            'laboratory',
        ]);

        if (!$product) {
            throw new ModelNotFoundException("Producto no encontrado.");
        }
        $product->loadSum('lots', 'quantity');
        return $product;
    }

}
