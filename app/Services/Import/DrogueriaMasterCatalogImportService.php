<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Models\Laboratory;
use App\Models\Product;
use App\Models\ProductLot;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DrogueriaMasterCatalogImportService
{
    /**
     * Obtiene el nombre de la conexión a la base de datos central/Master.
     */
    protected function getCentralConnectionName(): string
    {
        $isTenant = function_exists('tenancy') && tenancy()->initialized;

        if ($isTenant) {
            return config('tenancy.database.central_connection', 'mysql');
        }

        return DB::getDefaultConnection();
    }

    /**
     * Resuelve los IDs de proveedor equivalentes (mismo ID, RIF o nombre) en una conexión dada.
     *
     * @param string $connection
     * @param int $masterSupplierId
     * @param object|null $supplier
     * @return array<int>
     */
    protected function resolveMatchingSupplierIds(string $connection, int $masterSupplierId, ?object $supplier = null): array
    {
        $ids = [$masterSupplierId];

        try {
            if (!$supplier) {
                $supplier = DB::connection($connection)->table('suppliers')->where('id', $masterSupplierId)->first();
            }

            if ($supplier) {
                $aliasQuery = DB::connection($connection)->table('suppliers');
                $hasCondition = false;

                if (!empty($supplier->rif)) {
                    $aliasQuery->where('rif', $supplier->rif);
                    $hasCondition = true;
                }

                if (!empty($supplier->name)) {
                    if ($hasCondition) {
                        $aliasQuery->orWhere('name', $supplier->name);
                    } else {
                        $aliasQuery->where('name', $supplier->name);
                        $hasCondition = true;
                    }
                }

                if ($hasCondition) {
                    $aliasIds = $aliasQuery->pluck('id')->map(fn($id) => (int) $id)->all();
                    $ids = array_values(array_unique(array_merge($ids, $aliasIds)));
                }
            }
        } catch (\Throwable) {
        }

        return $ids;
    }

    /**
     * Determina la conexión y los IDs donde residen los productos de un proveedor (Master o Tenant local).
     *
     * @param int $masterSupplierId
     * @param object|null $supplier
     * @return array{connection: string, supplier_ids: array<int>, count: int}
     */
    protected function resolveCatalogSource(int $masterSupplierId, ?object $supplier = null): array
    {
        $central = $this->getCentralConnectionName();
        $isTenant = function_exists('tenancy') && tenancy()->initialized;
        $local = $isTenant ? DB::getDefaultConnection() : null;

        // 1. Probar en conexión central/Master
        $centralIds = $this->resolveMatchingSupplierIds($central, $masterSupplierId, $supplier);
        $centralCount = (int) DB::connection($central)->table('product_suppliers')->whereIn('supplier_id', $centralIds)->count();

        if ($centralCount > 0) {
            return [
                'connection' => $central,
                'supplier_ids' => $centralIds,
                'count' => $centralCount,
            ];
        }

        // 2. Si en central da 0 y estamos en un tenant, verificar conexión local del tenant
        if ($local && $local !== $central) {
            $localIds = $this->resolveMatchingSupplierIds($local, $masterSupplierId, $supplier);
            $localCount = (int) DB::connection($local)->table('product_suppliers')->whereIn('supplier_id', $localIds)->count();

            if ($localCount > 0) {
                return [
                    'connection' => $local,
                    'supplier_ids' => $localIds,
                    'count' => $localCount,
                ];
            }
        }

        return [
            'connection' => $central,
            'supplier_ids' => $centralIds,
            'count' => 0,
        ];
    }

    /**
     * Obtiene los proveedores del Master con el conteo de productos asociados en product_suppliers.
     * Soporta tanto el catálogo de la base central como catálogos locales en el tenant y resolución de alias.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMasterSuppliersWithCatalogCount(): array
    {
        $centralConnection = $this->getCentralConnectionName();
        $isTenant = function_exists('tenancy') && tenancy()->initialized;
        $localConnection = $isTenant ? DB::getDefaultConnection() : null;

        // 1. Obtener todos los proveedores de la base central
        $suppliers = DB::connection($centralConnection)
            ->table('suppliers')
            ->select(
                'suppliers.id',
                'suppliers.name',
                'suppliers.social_reason',
                'suppliers.rif',
                'suppliers.credit_days',
                'suppliers.is_active'
            )
            ->get();

        // 2. Mapear conteos en base central
        $centralCounts = DB::connection($centralConnection)
            ->table('product_suppliers')
            ->whereNotNull('supplier_id')
            ->groupBy('supplier_id')
            ->pluck(DB::raw('COUNT(id) as cnt'), 'supplier_id')
            ->all();

        // 3. Mapear conteos en tenant local (si aplica)
        $localCounts = [];
        if ($localConnection && $localConnection !== $centralConnection) {
            try {
                $localCounts = DB::connection($localConnection)
                    ->table('product_suppliers')
                    ->whereNotNull('supplier_id')
                    ->groupBy('supplier_id')
                    ->pluck(DB::raw('COUNT(id) as cnt'), 'supplier_id')
                    ->all();
            } catch (\Throwable) {
            }
        }

        // Indexar proveedores por RIF y nombre normalizado para consolidar alias
        $rifMap = [];
        $nameMap = [];
        foreach ($suppliers as $s) {
            if (!empty($s->rif)) {
                $rifKey = strtoupper(trim((string) $s->rif));
                $rifMap[$rifKey][] = (int) $s->id;
            }
            if (!empty($s->name)) {
                $nameKey = strtoupper(trim((string) $s->name));
                $nameMap[$nameKey][] = (int) $s->id;
            }
        }

        $resultRows = collect();

        foreach ($suppliers as $supp) {
            $suppId = (int) $supp->id;
            $relatedIds = [$suppId];

            if (!empty($supp->rif)) {
                $rifKey = strtoupper(trim((string) $supp->rif));
                if (isset($rifMap[$rifKey])) {
                    $relatedIds = array_merge($relatedIds, $rifMap[$rifKey]);
                }
            }

            if (!empty($supp->name)) {
                $nameKey = strtoupper(trim((string) $supp->name));
                if (isset($nameMap[$nameKey])) {
                    $relatedIds = array_merge($relatedIds, $nameMap[$nameKey]);
                }
            }

            $relatedIds = array_unique($relatedIds);

            // Sumar conteos de los IDs relacionados en central y local
            $cCount = 0;
            foreach ($relatedIds as $rid) {
                $cCount += (int) ($centralCounts[$rid] ?? 0);
            }

            $lCount = 0;
            foreach ($relatedIds as $rid) {
                $lCount += (int) ($localCounts[$rid] ?? 0);
            }

            $catalogItemsCount = max($cCount, $lCount);

            $resultRows->push((object) [
                'id' => $suppId,
                'name' => $supp->name,
                'social_reason' => $supp->social_reason,
                'rif' => $supp->rif,
                'credit_days' => $supp->credit_days,
                'is_active' => $supp->is_active,
                'catalog_items_count' => $catalogItemsCount,
            ]);
        }

        // 4. Incluir proveedores huérfanos que tengan productos en product_suppliers
        $existingIds = $resultRows->pluck('id')->filter()->all();

        $allOrphans = collect();
        foreach ($centralCounts as $sid => $cnt) {
            if (!in_array((int) $sid, $existingIds, true)) {
                $allOrphans[(int) $sid] = (int) $cnt;
            }
        }
        foreach ($localCounts as $sid => $cnt) {
            if (!in_array((int) $sid, $existingIds, true)) {
                $allOrphans[(int) $sid] = max((int) ($allOrphans[(int) $sid] ?? 0), (int) $cnt);
            }
        }

        foreach ($allOrphans as $orphanId => $orphanCount) {
            $resultRows->push((object) [
                'id' => (int) $orphanId,
                'name' => "Proveedor Catálogo #{$orphanId}",
                'social_reason' => null,
                'rif' => null,
                'credit_days' => 15,
                'is_active' => true,
                'catalog_items_count' => (int) $orphanCount,
            ]);
        }

        // Ordenar con prioridad a los que tengan catálogo cargado
        $sorted = $resultRows->sortByDesc('catalog_items_count')->values();

        return $sorted->map(function ($row) {
            return [
                'id' => (int) $row->id,
                'name' => (string) ($row->name ?? "Proveedor #{$row->id}"),
                'social_reason' => $row->social_reason ? (string) $row->social_reason : null,
                'rif' => $row->rif ? (string) $row->rif : null,
                'credit_days' => $row->credit_days !== null ? (int) $row->credit_days : null,
                'is_active' => (bool) ($row->is_active ?? true),
                'catalog_items_count' => (int) $row->catalog_items_count,
            ];
        })->toArray();
    }

    /**
     * Genera una previsualización de los productos del catálogo de un proveedor.
     *
     * @param int $masterSupplierId
     * @param int $limit
     * @return array<string, mixed>
     */
    public function previewSupplierCatalog(int $masterSupplierId, int $limit = 20): array
    {
        $central = $this->getCentralConnectionName();

        $supplier = DB::connection($central)
            ->table('suppliers')
            ->where('id', $masterSupplierId)
            ->first();

        $source = $this->resolveCatalogSource($masterSupplierId, $supplier);

        if (!$supplier) {
            if ($source['count'] > 0) {
                $supplier = (object) [
                    'id' => $masterSupplierId,
                    'name' => "Proveedor Catálogo #{$masterSupplierId}",
                    'rif' => '',
                    'credit_days' => 15,
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Proveedor no encontrado en la base central.',
                    'data' => null,
                ];
            }
        }

        $query = DB::connection($source['connection'])
            ->table('product_suppliers')
            ->whereIn('supplier_id', $source['supplier_ids']);

        $totalItems = (clone $query)->count();
        $withStockCount = (clone $query)->where('quantity', '>', 0)->count();
        $avgCost = (float) ((clone $query)->avg('unit_cost_usd') ?: 0);

        $items = (clone $query)
            ->orderByDesc('quantity')
            ->limit($limit)
            ->get()
            ->map(function ($row) use ($supplier) {
                $cost = (float) ($row->unit_cost_usd_with_discount ?: $row->unit_cost_usd ?: 0);
                return [
                    'id' => (int) $row->id,
                    'cod_supplier' => (string) ($row->cod_supplier ?? ''),
                    'name' => (string) ($row->name ?? 'Sin nombre'),
                    'barcode' => (string) ($row->barcode_match ?? ''),
                    'laboratory' => (string) ($row->laboratory ?? ''),
                    'quantity' => (float) ($row->quantity ?? 0),
                    'unit_cost_usd' => $cost,
                    'credit_days' => $row->credit_days !== null ? (int) $row->credit_days : ($supplier->credit_days ? (int) $supplier->credit_days : null),
                    'expiration' => $row->expiration ? (string) $row->expiration : null,
                ];
            });

        return [
            'success' => true,
            'supplier' => [
                'id' => (int) $supplier->id,
                'name' => (string) $supplier->name,
                'rif' => (string) ($supplier->rif ?? ''),
                'credit_days' => $supplier->credit_days ? (int) $supplier->credit_days : null,
            ],
            'metrics' => [
                'total_items' => $totalItems,
                'with_stock_count' => $withStockCount,
                'avg_cost_usd' => round($avgCost, 2),
            ],
            'preview_items' => $items,
        ];
    }

    /**
     * Importa masivamente los productos del proveedor seleccionado hacia el inventario local de la droguería.
     *
     * @param int $masterSupplierId
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function importCatalogToDrogueriaInventory(int $masterSupplierId, array $options): array
    {
        $central = $this->getCentralConnectionName();

        $supplier = DB::connection($central)
            ->table('suppliers')
            ->where('id', $masterSupplierId)
            ->first();

        $source = $this->resolveCatalogSource($masterSupplierId, $supplier);

        if (!$supplier) {
            if ($source['count'] > 0) {
                $supplier = (object) [
                    'id' => $masterSupplierId,
                    'name' => "Proveedor Catálogo #{$masterSupplierId}",
                    'credit_days' => 15,
                ];
            } else {
                throw new \InvalidArgumentException('El proveedor especificado no existe en la base central.');
            }
        }

        $marginPercentage = isset($options['margin_percentage']) ? (float) $options['margin_percentage'] : 18.0;
        $defaultCreditDays = isset($options['default_credit_days']) ? (int) $options['default_credit_days'] : ($supplier->credit_days ? (int) $supplier->credit_days : 0);
        $defaultStockIfZero = isset($options['default_stock_if_zero']) ? (float) $options['default_stock_if_zero'] : 100.0;
        $onlyWithStock = !empty($options['only_with_stock']);

        $query = DB::connection($source['connection'])
            ->table('product_suppliers')
            ->whereIn('supplier_id', $source['supplier_ids']);

        if ($onlyWithStock) {
            $query->where('quantity', '>', 0);
        }

        $createdProducts = 0;
        $updatedProducts = 0;
        $createdLots = 0;
        $createdLabs = 0;
        $totalUnitsLoaded = 0.0;

        // Caché en memoria de laboratorios locales para evitar consultas repetitivas
        $laboratoryCache = Laboratory::pluck('id', 'name')->toArray();

        // Precarga de laboratorios y mapeos desde el Master para enriquecer productos sin laboratorio informado
        $masterLabsById = DB::connection($central)
            ->table('laboratories')
            ->pluck('name', 'id')
            ->toArray();

        $masterProductsByBarcode = DB::connection($central)
            ->table('products')
            ->whereNotNull('laboratory_id')
            ->whereNotNull('barcode')
            ->whereRaw("TRIM(barcode) != ''")
            ->pluck('laboratory_id', 'barcode')
            ->toArray();

        $masterProductsByName = DB::connection($central)
            ->table('products')
            ->whereNotNull('laboratory_id')
            ->whereNotNull('name')
            ->pluck('laboratory_id', 'name')
            ->toArray();

        $masterOtherSuppliersLabByBarcode = DB::connection($central)
            ->table('product_suppliers')
            ->whereNotNull('laboratory')
            ->whereRaw("TRIM(laboratory) != ''")
            ->whereNotNull('barcode_match')
            ->pluck('laboratory', 'barcode_match')
            ->toArray();

        $query->orderBy('id')->chunk(250, function (Collection $chunk) use (
            $marginPercentage,
            $defaultCreditDays,
            $defaultStockIfZero,
            $masterLabsById,
            $masterProductsByBarcode,
            $masterProductsByName,
            $masterOtherSuppliersLabByBarcode,
            &$createdProducts,
            &$updatedProducts,
            &$createdLots,
            &$createdLabs,
            &$totalUnitsLoaded,
            &$laboratoryCache
        ) {
            DB::transaction(function () use (
                $chunk,
                $marginPercentage,
                $defaultCreditDays,
                $defaultStockIfZero,
                $masterLabsById,
                $masterProductsByBarcode,
                $masterProductsByName,
                $masterOtherSuppliersLabByBarcode,
                &$createdProducts,
                &$updatedProducts,
                &$createdLots,
                &$createdLabs,
                &$totalUnitsLoaded,
                &$laboratoryCache
            ) {
                foreach ($chunk as $row) {
                    $name = trim((string) ($row->name ?? ''));
                    if (empty($name)) {
                        continue;
                    }

                    $barcode = trim((string) ($row->barcode_match ?? ''));
                    $labName = trim((string) ($row->laboratory ?? ''));

                    // Si el proveedor no informa laboratorio o es Generales, resolver desde el Master
                    if (empty($labName) || strcasecmp($labName, 'Generales') === 0) {
                        if (!empty($barcode) && isset($masterProductsByBarcode[$barcode])) {
                            $mLabId = $masterProductsByBarcode[$barcode];
                            $labName = $masterLabsById[$mLabId] ?? '';
                        }
                        if ((empty($labName) || strcasecmp($labName, 'Generales') === 0) && isset($masterProductsByName[$name])) {
                            $mLabId = $masterProductsByName[$name];
                            $labName = $masterLabsById[$mLabId] ?? '';
                        }
                        if ((empty($labName) || strcasecmp($labName, 'Generales') === 0) && !empty($barcode) && isset($masterOtherSuppliersLabByBarcode[$barcode])) {
                            $labName = trim((string) $masterOtherSuppliersLabByBarcode[$barcode]);
                        }
                    }

                    if (empty($labName)) {
                        $labName = 'Generales';
                    }

                    // 1. Resolver Laboratorio
                    if (!isset($laboratoryCache[$labName])) {
                        $lab = Laboratory::firstOrCreate(['name' => $labName]);
                        $laboratoryCache[$labName] = $lab->id;
                        $createdLabs++;
                    }
                    $labId = $laboratoryCache[$labName];

                    // 2. Costo y Precio de Venta
                    $unitCostUsd = (float) ($row->unit_cost_usd_with_discount ?: $row->unit_cost_usd ?: 1.0);
                    $salePrice = round($unitCostUsd * (1 + ($marginPercentage / 100)), 2);
                    $creditDays = $row->credit_days !== null ? (int) $row->credit_days : $defaultCreditDays;

                    // 3. Cantidad a cargar
                    $quantity = (float) ($row->quantity ?? 0);
                    if ($quantity <= 0) {
                        $quantity = $defaultStockIfZero;
                    }

                    // 4. Buscar o crear producto local
                    $product = null;
                    if (!empty($barcode)) {
                        $product = Product::where('barcode', $barcode)->first();
                    }
                    if (!$product) {
                        $product = Product::where('name', $name)->first();
                    }

                    $isNewProduct = false;
                    if (!$product) {
                        $product = Product::create([
                            'name' => $name,
                            'barcode' => !empty($barcode) ? $barcode : null,
                            'laboratory_id' => $labId,
                            'unit_cost' => $unitCostUsd,
                            'sale_price' => $salePrice,
                            'credit_days' => $creditDays,
                            'stock' => $quantity,
                            'iva' => true,
                            'active_ingredient' => $row->active_ingredient ?? null,
                            'requires_cold_chain' => false,
                        ]);
                        $createdProducts++;
                        $isNewProduct = true;
                    } else {
                        // Actualizar información comercial y sumar stock
                        $product->unit_cost = $unitCostUsd;
                        $product->sale_price = $salePrice;
                        $product->credit_days = $creditDays;
                        $product->laboratory_id = $labId;
                        $product->stock = (float) $product->stock + $quantity;
                        $product->save();
                        $updatedProducts++;
                    }

                    // 5. Crear lote para FEFO
                    $expiration = $row->expiration ? Carbon::parse($row->expiration) : Carbon::now()->addMonths(14);
                    if ($expiration->isPast()) {
                        $expiration = Carbon::now()->addMonths(12);
                    }

                    $lotNumber = 'LOT-' . ($row->cod_supplier ?: $product->id) . '-' . $expiration->format('ym');

                    ProductLot::create([
                        'product_id' => $product->id,
                        'supplier_id' => $row->supplier_id ?? null,
                        'lot_number' => $lotNumber,
                        'expiration_date' => $expiration,
                        'quantity' => $quantity,
                        'unit_cost' => $unitCostUsd,
                        'location' => 'ALMACEN-CENTRAL',
                    ]);

                    $createdLots++;
                    $totalUnitsLoaded += $quantity;
                }
            });
        });

        return [
            'success' => true,
            'message' => "Catálogo de {$supplier->name} importado exitosamente al inventario.",
            'data' => [
                'supplier_id' => $masterSupplierId,
                'supplier_name' => $supplier->name,
                'created_products' => $createdProducts,
                'updated_products' => $updatedProducts,
                'created_lots' => $createdLots,
                'created_laboratories' => $createdLabs,
                'total_units_loaded' => $totalUnitsLoaded,
            ],
        ];
    }
}
