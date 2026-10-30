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
     * Obtiene los proveedores del Master con el conteo de productos asociados en product_suppliers.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMasterSuppliersWithCatalogCount(): array
    {
        $connection = $this->getCentralConnectionName();

        $rows = DB::connection($connection)
            ->table('suppliers')
            ->leftJoin('product_suppliers', function ($join) {
                $join->on('suppliers.id', '=', 'product_suppliers.supplier_id');
            })
            ->select(
                'suppliers.id',
                'suppliers.name',
                'suppliers.social_reason',
                'suppliers.rif',
                'suppliers.credit_days',
                DB::raw('COUNT(product_suppliers.id) as catalog_items_count')
            )
            ->whereNull('suppliers.deleted_at')
            ->groupBy(
                'suppliers.id',
                'suppliers.name',
                'suppliers.social_reason',
                'suppliers.rif',
                'suppliers.credit_days'
            )
            ->orderByDesc('catalog_items_count')
            ->orderBy('suppliers.name')
            ->get();

        return $rows->map(function ($row) {
            return [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'social_reason' => $row->social_reason ? (string) $row->social_reason : null,
                'rif' => $row->rif ? (string) $row->rif : null,
                'credit_days' => $row->credit_days !== null ? (int) $row->credit_days : null,
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
        $connection = $this->getCentralConnectionName();

        $supplier = DB::connection($connection)
            ->table('suppliers')
            ->where('id', $masterSupplierId)
            ->first();

        if (!$supplier) {
            return [
                'success' => false,
                'message' => 'Proveedor no encontrado en la base central.',
                'data' => null,
            ];
        }

        $query = DB::connection($connection)
            ->table('product_suppliers')
            ->where('supplier_id', $masterSupplierId);

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
        $connection = $this->getCentralConnectionName();

        $supplier = DB::connection($connection)
            ->table('suppliers')
            ->where('id', $masterSupplierId)
            ->first();

        if (!$supplier) {
            throw new \InvalidArgumentException('El proveedor especificado no existe en la base central.');
        }

        $marginPercentage = isset($options['margin_percentage']) ? (float) $options['margin_percentage'] : 18.0;
        $defaultCreditDays = isset($options['default_credit_days']) ? (int) $options['default_credit_days'] : ($supplier->credit_days ? (int) $supplier->credit_days : 0);
        $defaultStockIfZero = isset($options['default_stock_if_zero']) ? (float) $options['default_stock_if_zero'] : 100.0;
        $onlyWithStock = !empty($options['only_with_stock']);

        $query = DB::connection($connection)
            ->table('product_suppliers')
            ->where('supplier_id', $masterSupplierId);

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

        $query->orderBy('id')->chunk(250, function (Collection $chunk) use (
            $marginPercentage,
            $defaultCreditDays,
            $defaultStockIfZero,
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
                    $labName = trim((string) ($row->laboratory ?? 'Generales'));
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
