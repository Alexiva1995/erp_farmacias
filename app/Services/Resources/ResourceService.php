<?php

declare(strict_types=1);

namespace App\Services\Resources;

use App\Models\GroupsLaboratory;
use App\Models\Category;
use App\Models\Laboratory;
use App\Models\Origin;
use App\Models\Supplier;
use App\Models\ExchangeRate;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ResourceService
{
    /**
     * Limpia la caché de laboratorios para la conexión actual y variantes conocidas.
     */
    public static function clearLaboratoriesCache(): void
    {
        $conn = \Illuminate\Support\Facades\DB::getDefaultConnection();
        Cache::forget('resources.laboratories');
        Cache::forget('resources.laboratories.mysql');
        Cache::forget('resources.laboratories.master_catalog');
        Cache::forget('resources.laboratories.tenant');
        if ($conn) {
            Cache::forget("resources.laboratories.{$conn}");
        }
    }

    /**
     * Obtiene una lista de todos los laboratorios con su grupo y conteo de productos, utilizando caché.
     */
    public function getLaboratories(): Collection
    {
        $conn = \Illuminate\Support\Facades\DB::getDefaultConnection();
        return Cache::remember("resources.laboratories.{$conn}", now()->addDay(), function () {
            return Laboratory::withCount('products')
                ->orderBy('name')
                ->get(['id', 'name', 'group_id']);
        });
    }

    /**
     * Obtiene una lista de todos los grupos de laboratorios, utilizando caché.
     */
    public function getLaboratoryGroups(): Collection
    {
        $conn = \Illuminate\Support\Facades\DB::getDefaultConnection();
        return Cache::remember("resources.laboratory_groups.{$conn}", now()->addDay(), function () {
            return GroupsLaboratory::orderBy('name')->get(['id', 'name']);
        });
    }

    /**
     * Obtiene una lista de todos los orígenes, utilizando caché.
     */
    public function getOrigins(): Collection
    {
        $conn = \Illuminate\Support\Facades\DB::getDefaultConnection();
        return Cache::remember("resources.origins.{$conn}", now()->addDay(), function () {
            return Origin::orderBy('name')->get(['id', 'name']);
        });
    }

    /**
     * Obtiene una lista de todos los proveedores, utilizando caché.
     */
    public function getSuppliers(): Collection
    {
        $conn = \Illuminate\Support\Facades\DB::getDefaultConnection();
        return Cache::remember("resources.suppliers.{$conn}", now()->addDay(), function () {
            return Supplier::orderBy('name')->get(['id', 'name']);
        });
    }

    /**
     * Obtiene una lista de todas las categorías aplicando filtros solicitados.
     */
    public function getCategories(array $filters = []): Collection
    {
        if (isset($filters['type']) && $filters['type'] === 'dishes') {
            $isRestaurant = \App\Models\GeneralSetting::first()?->business_type === 'restaurant';

            if ($isRestaurant) {
                return Category::orderBy('name')->get(['id', 'name']);
            }

            return Category::whereHas('dishes')
                ->orWhereDoesntHave('products')
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        return Category::orderBy('name')->get(['id', 'name']);
    }

    public function getAllProducts(): Collection
    {
        $conn = \Illuminate\Support\Facades\DB::getDefaultConnection();
        return Cache::remember("resources.products.{$conn}", now()->addDay(), function () {
            return Product::orderBy('name')->get();
        });
    }

    /**
     * Clave de caché de una tasa individual, aislada por conexión (inquilino).
     */
    public static function exchangeRateCacheKey(string $currencyCode, bool $fallback = false): string
    {
        $conn = \Illuminate\Support\Facades\DB::getDefaultConnection();
        $key = "resources.exchange_rate.{$conn}.{$currencyCode}";

        return $fallback ? "{$key}.fallback" : $key;
    }

    /**
     * Clave de caché del listado de tasas, aislada por conexión (inquilino).
     */
    public static function allExchangeRatesCacheKey(): string
    {
        $conn = \Illuminate\Support\Facades\DB::getDefaultConnection();

        return "resources.all_exchange_rates.{$conn}";
    }

    /**
     * Obtiene una sola tasa, utilizando caché y una estrategia robusta de recuperación (Fallback Cache).
     */
    public function getExchangeRate(string $currencyCode): float
    {
        if ($currencyCode === 'BS' || $currencyCode === 'VES') {
            $setting = \App\Models\GeneralSetting::first();
            $tpvRateType = $setting?->tpv_rate_type;

            if ($tpvRateType === 'binance') {
                $currencyCode = 'BINANCE';
            } elseif ($tpvRateType === 'eur') {
                $currencyCode = 'EUR';
            } elseif ($tpvRateType === 'bcv') {
                $currencyCode = 'BS';
            } else {
                $isRestaurant = $setting?->business_type === 'restaurant';
                $currencyCode = $isRestaurant ? 'BINANCE' : 'EUR';
            }
        }

        $cacheKey = self::exchangeRateCacheKey($currencyCode);
        $fallbackKey = self::exchangeRateCacheKey($currencyCode, true);

        try {
            // Intentar obtener la tasa fresca o desde la caché con expiración
            $rate = Cache::remember($cacheKey, now()->addHours(1), function () use ($currencyCode, $fallbackKey) {
                if ($currencyCode === 'COP' || $currencyCode === 'COPC') {
                    $exchangeRate = ExchangeRate::where('currency_code', $currencyCode)
                        ->where('rate', '>=', 100)
                        ->latest('id')
                        ->first();

                    if (!$exchangeRate && $currencyCode === 'COP') {
                        $exchangeRate = ExchangeRate::where('currency_code', 'COPC')
                            ->where('rate', '>=', 100)
                            ->latest('id')
                            ->first();
                    }
                } else {
                    $exchangeRate = ExchangeRate::where('currency_code', $currencyCode)->latest('id')->first();
                    if (!$exchangeRate && ($currencyCode === 'BCV' || $currencyCode === 'BS')) {
                        $exchangeRate = ExchangeRate::whereIn('currency_code', ['BS', 'BCV', 'VES'])->latest('id')->first();
                    }
                }

                if ($exchangeRate) {
                    $val = (float) $exchangeRate->rate;
                    // Almacenar en caché persistente (sin expiración) como fallback de seguridad
                    Cache::forever($fallbackKey, $val);
                    return $val;
                }

                // Si no hay registro fresco pero hay fallback, usarlo
                if (Cache::has($fallbackKey)) {
                    $cachedFallback = (float) Cache::get($fallbackKey);
                    if ($currencyCode !== 'COP' && $currencyCode !== 'COPC' || $cachedFallback >= 100) {
                        return $cachedFallback;
                    }
                }

                return ($currencyCode === 'COP' || $currencyCode === 'COPC') ? 3300.0 : 1.0;
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
                $cachedFallback = (float) Cache::get($fallbackKey);
                if ($currencyCode !== 'COP' && $currencyCode !== 'COPC' || $cachedFallback >= 100) {
                    return $cachedFallback;
                }
            }
            return ($currencyCode === 'COP' || $currencyCode === 'COPC') ? 3300.0 : 1.0;
        }
    }

    /**
     * Obtiene todas las tasas, utilizando caché o filtradas por fecha histórica.
     */
    public function getAllExchangeRate(?string $date = null): Collection
    {
        if (!empty($date)) {
            try {
                $parsedDate = \Carbon\Carbon::parse($date)->endOfDay();
                $historicalRates = ExchangeRate::query()
                    ->whereIn('id', function ($query) use ($parsedDate) {
                        $query->selectRaw('MAX(id)')
                            ->from('exchange_rates')
                            ->where('created_at', '<=', $parsedDate)
                            ->groupBy('currency_code');
                    })
                    ->orderBy('currency_code')
                    ->get(['currency_code', 'rate', 'source']);

                if ($historicalRates->isNotEmpty()) {
                    return $historicalRates;
                }
            } catch (\Throwable $e) {
                Log::warning("Error al consultar tasas para fecha '{$date}': " . $e->getMessage());
            }
        }

        return Cache::remember(self::allExchangeRatesCacheKey(), now()->addHours(1), function () {
            $rates = ExchangeRate::query()
                ->whereIn('id', function ($query) {
                    $query->selectRaw('MAX(id)')
                        ->from('exchange_rates')
                        ->groupBy('currency_code');
                })
                ->orderBy('currency_code')
                ->get(['currency_code', 'rate', 'source']);

            // Proteger las tasas de COP y COPC si hay valores anómalos menores a 100
            foreach ($rates as $item) {
                if (in_array($item->currency_code, ['COP', 'COPC']) && floatval($item->rate) < 100) {
                    $validRate = ExchangeRate::where('currency_code', $item->currency_code)
                        ->where('rate', '>=', 100)
                        ->latest('id')
                        ->value('rate');
                    $item->rate = $validRate ?: 3300.0;
                }
            }

            return $rates;
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

        return $this->loadProductDetails($foundProduct);
    }

    public function loadProductDetails(Product $product): Product
    {
        if (!$product) {
            throw new ModelNotFoundException("Producto no encontrado.");
        }

        $product->load([
            'laboratory',
        ]);

        $product->loadSum('lots', 'quantity');

        $validStockSum = $product->lots()
            ->where('expiration_date', '>=', now())
            ->where('quantity', '>', 0)
            ->sum('quantity');

        $product->setAttribute('valid_stock_sum', (int) ($validStockSum ?? 0));

        return $product;
    }
}
