<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Laboratory;
use App\Models\Product;
use App\Models\ProductLot;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class OnboardingLegacyImportService
{
    public function __construct(
        protected MasterCatalogClientService $masterClient
    ) {}

    /**
     * Ejecuta el proceso de importación y onboarding desde los archivos Excel legados.
     *
     * @param string $productsFilePath Ruta absoluta o relativa al archivo general de productos
     * @param string $lotsFilePath Ruta absoluta o relativa al archivo de lotes
     * @param bool $syncWithMaster Si se debe homologar y registrar contra el Catálogo Maestro
     * @param callable|null $progressCallback Callback opcional para seguimiento en CLI (fase, paso, total)
     * @return array Estadísticas del proceso
     */
    public function import(
        string $productsFilePath,
        string $lotsFilePath,
        bool $syncWithMaster = true,
        ?callable $progressCallback = null
    ): array {
        @ini_set('memory_limit', '1024M');
        @ini_set('max_execution_time', '1200');
        @set_time_limit(1200);

        // 1. Extraer datos del listado de productos
        if ($progressCallback) {
            $progressCallback('parsing_products', 0, 100, 'Leyendo archivo de productos...');
        }
        $productsData = $this->parseProductsFile($productsFilePath);

        // 2. Extraer datos del listado de lotes
        if ($progressCallback) {
            $progressCallback('parsing_lots', 0, 100, 'Leyendo archivo de lotes...');
        }
        $lotsData = $this->parseLotsFile($lotsFilePath);

        // 3. Consolidar catálogo y aplicar regla de límite/ajuste de stock estricto
        if ($progressCallback) {
            $progressCallback('consolidating', 0, 100, 'Consolidando catálogo y ajustando cantidades de lotes...');
        }
        $consolidated = $this->consolidateDataAndAdjustLots($productsData, $lotsData);

        $totalItems = count($consolidated);
        $barcodes = array_values(array_map('strval', array_keys($consolidated)));

        // 4. Homologación con Catálogo Maestro
        $masterMap = [];
        if ($syncWithMaster) {
            if ($progressCallback) {
                $progressCallback('master_lookup', 0, count($barcodes), 'Consultando Catálogo Maestro...');
            }
            $masterMap = $this->masterClient->lookupBulk($barcodes);
        }

        // 5. Pre-cargar datos locales para optimizar queries
        $existingProducts = Product::withoutGlobalScope('not_deleted')
            ->withTrashed()
            ->whereIn('barcode', $barcodes)
            ->get()
            ->keyBy('barcode');

        $existingIds = Product::withoutGlobalScope('not_deleted')
            ->withTrashed()
            ->pluck('id')
            ->flip()
            ->toArray();

        $stats = [
            'total_products'                 => $totalItems,
            'created'                        => 0,
            'updated'                        => 0,
            'matched_master'                 => 0,
            'registered_master'              => 0,
            'total_lots_created'             => 0,
            'lots_reduced_for_cap'           => 0,
            'lots_extended_for_shortage'     => 0,
            'traceability_movements_created' => 0,
            'total_consolidated_stock'       => 0.0,
        ];

        // 6. Procesar cada producto dentro de transacciones atómicas individuales con reintentos automáticos
        $processedCount = 0;

        foreach ($consolidated as $barcode => $item) {
            DB::transaction(function () use (
                $barcode,
                $item,
                $syncWithMaster,
                &$masterMap,
                $existingProducts,
                &$existingIds,
                &$stats
            ) {
                $this->processProductItem(
                    (string) $barcode,
                    $item,
                    $syncWithMaster,
                    $masterMap,
                    $existingProducts,
                    $existingIds,
                    $stats
                );
            }, 5);

            $processedCount++;
            if ($progressCallback && ($processedCount % 25 === 0 || $processedCount === $totalItems)) {
                $progressCallback('importing', $processedCount, $totalItems, "Importando productos ({$processedCount}/{$totalItems})...");
            }
        }

        Log::info('[OnboardingLegacyImport] Importación finalizada exitosamente', $stats);

        return $stats;
    }

    /**
     * Parsea el archivo de productos (Listado de Productos 03-10-2026.xls).
     */
    protected function parseProductsFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("El archivo de productos no existe en la ruta: {$filePath}");
        }

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();

        $products = [];

        // Los datos inician en la fila 7 (encabezados en fila 6)
        for ($r = 7; $r <= $highestRow; $r++) {
            $barcode = trim((string) $sheet->getCell("B{$r}")->getValue());
            $name = trim((string) $sheet->getCell("E{$r}")->getValue());

            if (empty($barcode) || empty($name)) {
                continue;
            }

            $lab = trim((string) $sheet->getCell("F{$r}")->getValue());
            $category = trim((string) $sheet->getCell("G{$r}")->getValue());
            $unit = trim((string) $sheet->getCell("H{$r}")->getValue());
            $stockRaw = (string) $sheet->getCell("I{$r}")->getValue();
            $drogueriaPriceRaw = (string) $sheet->getCell("K{$r}")->getValue();
            $costRaw = (string) $sheet->getCell("L{$r}")->getValue();

            $stock = (float) str_replace(',', '.', preg_replace('/[^\d,.-]/', '', $stockRaw ?: '0'));
            $drogueriaPrice = (float) str_replace(',', '.', preg_replace('/[^\d,.-]/', '', $drogueriaPriceRaw ?: '0'));
            $cost = (float) str_replace(',', '.', preg_replace('/[^\d,.-]/', '', $costRaw ?: '0'));

            $products[$barcode] = [
                'barcode'         => $barcode,
                'name'            => $name,
                'laboratory'      => !empty($lab) ? $lab : null,
                'category'        => !empty($category) ? $category : null,
                'unit_of_measure' => !empty($unit) ? $unit : 'UND',
                'target_stock'    => max(0.0, $stock),
                'wholesale_price' => max(0.0, $drogueriaPrice),
                'cost'            => max(0.0, $cost),
            ];
        }

        return $products;
    }

    /**
     * Parsea el archivo de lotes (Listado de Productos lotes 03-10-2026.xls).
     */
    protected function parseLotsFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("El archivo de lotes no existe en la ruta: {$filePath}");
        }

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();

        $lotsByBarcode = [];

        // Los datos inician en la fila 8 (encabezados en fila 7)
        for ($r = 8; $r <= $highestRow; $r++) {
            $barcode = trim((string) $sheet->getCell("E{$r}")->getValue());
            $name = trim((string) $sheet->getCell("F{$r}")->getValue());

            if (empty($barcode) || empty($name)) {
                continue;
            }

            $category = trim((string) $sheet->getCell("B{$r}")->getValue());
            $lab = trim((string) $sheet->getCell("G{$r}")->getValue());
            $lotNumber = trim((string) $sheet->getCell("H{$r}")->getValue());
            $expDateRaw = trim((string) $sheet->getCell("J{$r}")->getValue());
            $stockRaw = (string) $sheet->getCell("K{$r}")->getValue();
            $costRaw = (string) $sheet->getCell("L{$r}")->getValue();
            $activeIngredient = trim((string) $sheet->getCell("N{$r}")->getValue());
            $unit = trim((string) $sheet->getCell("O{$r}")->getValue());

            $stock = (float) str_replace(',', '.', preg_replace('/[^\d,.-]/', '', $stockRaw ?: '0'));
            $cost = (float) str_replace(',', '.', preg_replace('/[^\d,.-]/', '', $costRaw ?: '0'));

            // Parsear fecha de vencimiento (formato común d/m/Y)
            $parsedDate = '2028-12-31';
            if (!empty($expDateRaw)) {
                try {
                    $dt = Carbon::createFromFormat('d/m/Y', $expDateRaw);
                    if ($dt !== false) {
                        $parsedDate = $dt->format('Y-m-d');
                    }
                } catch (\Throwable) {
                    try {
                        $parsedDate = Carbon::parse($expDateRaw)->format('Y-m-d');
                    } catch (\Throwable) {
                        $parsedDate = '2028-12-31';
                    }
                }
            }

            $cleanLotNumber = (!empty($lotNumber) && $lotNumber !== '-') ? $lotNumber : 'LOT-INICIAL';

            $lotsByBarcode[$barcode][] = [
                'barcode'           => $barcode,
                'name'              => $name,
                'category'          => !empty($category) ? $category : null,
                'laboratory'        => !empty($lab) ? $lab : null,
                'lot_number'        => $cleanLotNumber,
                'expiration_date'   => $parsedDate,
                'stock'             => max(0.0, $stock),
                'cost'              => max(0.0, $cost),
                'active_ingredient' => !empty($activeIngredient) ? $activeIngredient : null,
                'unit_of_measure'   => !empty($unit) ? $unit : 'UND',
            ];
        }

        return $lotsByBarcode;
    }

    /**
     * Cruza ambos archivos y aplica el ajuste estricto para que la sumatoria de lotes coincida con target_stock.
     */
    protected function consolidateDataAndAdjustLots(array $productsData, array $lotsData): array
    {
        $allBarcodes = array_unique(array_merge(array_keys($productsData), array_keys($lotsData)));
        $consolidated = [];

        foreach ($allBarcodes as $barcode) {
            $pInfo = $productsData[$barcode] ?? null;
            $rawLots = $lotsData[$barcode] ?? [];

            // 1. Determinar datos base del producto
            $firstLot = !empty($rawLots) ? $rawLots[0] : null;

            $name = $pInfo['name'] ?? ($firstLot['name'] ?? 'PRODUCTO SIN NOMBRE');
            $laboratory = $pInfo['laboratory'] ?? ($firstLot['laboratory'] ?? null);
            $category = $pInfo['category'] ?? ($firstLot['category'] ?? null);
            $unitOfMeasure = $pInfo['unit_of_measure'] ?? ($firstLot['unit_of_measure'] ?? 'UND');
            $activeIngredient = $firstLot['active_ingredient'] ?? null;
            $unitCost = ($pInfo && $pInfo['cost'] > 0) ? $pInfo['cost'] : ($firstLot['cost'] ?? 0.0);
            $wholesalePrice = $pInfo['wholesale_price'] ?? $unitCost;

            // 2. Determinar stock objetivo autorizado
            $targetStock = 0.0;
            if ($pInfo !== null) {
                $targetStock = (float) $pInfo['target_stock'];
            } else {
                // Si no estaba en el listado general pero sí en lotes, la suma de los lotes es el stock
                $targetStock = (float) array_sum(array_column($rawLots, 'stock'));
            }

            // 3. Aplicar regla de ajuste de lotes
            $adjustedLots = [];
            $sumRawLots = (float) array_sum(array_column($rawLots, 'stock'));

            if (empty($rawLots) || $sumRawLots <= 0) {
                // Caso A: No hay lotes registrados -> Se crea lote inicial con el targetStock
                $adjustedLots[] = [
                    'lot_number'      => 'LOT-INICIAL',
                    'expiration_date' => '2028-12-31',
                    'quantity'        => $targetStock,
                    'unit_cost'       => $unitCost,
                ];
            } else {
                // Caso B: Hay lotes. Se ordenan por fecha de vencimiento (más cercanos a vencer primero)
                usort($rawLots, function ($a, $b) {
                    return strcmp($a['expiration_date'], $b['expiration_date']);
                });

                if ($sumRawLots > $targetStock) {
                    // Lotes exceden el stock del listado general -> Reducir automáticamente
                    $budget = $targetStock;
                    foreach ($rawLots as $lotItem) {
                        if ($budget <= 0) {
                            break;
                        }
                        $take = min((float) $lotItem['stock'], $budget);
                        if ($take > 0) {
                            $adjustedLots[] = [
                                'lot_number'      => $lotItem['lot_number'],
                                'expiration_date' => $lotItem['expiration_date'],
                                'quantity'        => $take,
                                'unit_cost'       => ($lotItem['cost'] > 0) ? (float) $lotItem['cost'] : $unitCost,
                            ];
                            $budget -= $take;
                        }
                    }
                } elseif ($sumRawLots < $targetStock) {
                    // Lotes son inferiores al stock del listado general -> Preservar lotes y agregar remanente
                    foreach ($rawLots as $lotItem) {
                        if ($lotItem['stock'] > 0) {
                            $adjustedLots[] = [
                                'lot_number'      => $lotItem['lot_number'],
                                'expiration_date' => $lotItem['expiration_date'],
                                'quantity'        => (float) $lotItem['stock'],
                                'unit_cost'       => ($lotItem['cost'] > 0) ? (float) $lotItem['cost'] : $unitCost,
                            ];
                        }
                    }

                    $difference = $targetStock - $sumRawLots;
                    if ($difference > 0) {
                        $adjustedLots[] = [
                            'lot_number'      => 'LOT-INICIAL',
                            'expiration_date' => '2028-12-31',
                            'quantity'        => $difference,
                            'unit_cost'       => $unitCost,
                        ];
                    }
                } else {
                    // La sumatoria coincide exactamente
                    foreach ($rawLots as $lotItem) {
                        $adjustedLots[] = [
                            'lot_number'      => $lotItem['lot_number'],
                            'expiration_date' => $lotItem['expiration_date'],
                            'quantity'        => (float) $lotItem['stock'],
                            'unit_cost'       => ($lotItem['cost'] > 0) ? (float) $lotItem['cost'] : $unitCost,
                        ];
                    }
                }
            }

            // Si el target stock es 0, asegurar un único lote en 0 si no hay
            if ($targetStock <= 0 && empty($adjustedLots)) {
                $adjustedLots[] = [
                    'lot_number'      => 'LOT-INICIAL',
                    'expiration_date' => '2028-12-31',
                    'quantity'        => 0.0,
                    'unit_cost'       => $unitCost,
                ];
            }

            $consolidated[$barcode] = [
                'barcode'           => $barcode,
                'name'              => $name,
                'laboratory'        => $laboratory,
                'category'          => $category,
                'unit_of_measure'   => $unitOfMeasure,
                'active_ingredient' => $activeIngredient,
                'unit_cost'         => $unitCost,
                'wholesale_price'   => $wholesalePrice,
                'target_stock'      => $targetStock,
                'lots'              => $adjustedLots,
                'had_reduction'     => ($sumRawLots > $targetStock),
                'had_extension'     => ($sumRawLots < $targetStock && $sumRawLots > 0),
            ];
        }

        return $consolidated;
    }

    /**
     * Procesa la inserción o actualización de un producto y sus lotes en la base de datos.
     */
    protected function processProductItem(
        string|int $barcode,
        array $item,
        bool $syncWithMaster,
        array &$masterMap,
        $existingProducts,
        array &$existingIds,
        array &$stats
    ): void {
        $barcode = trim((string) $barcode);
        $targetStock = (float) $item['target_stock'];
        $unitCost = (float) $item['unit_cost'];
        $wholesalePrice = (float) $item['wholesale_price'];
        $salePrice = ($wholesalePrice > 0) ? $wholesalePrice : $unitCost;

        // 1. Homologar con Catálogo Maestro si está activo
        $masterProduct = $masterMap[$barcode] ?? null;

        if (!$masterProduct && $syncWithMaster) {
            // Registrar producto nuevo en el Catálogo Maestro remoto
            $masterCreated = $this->masterClient->registerProductInMaster([
                'name'              => $item['name'],
                'barcode'           => $barcode,
                'active_ingredient' => $item['active_ingredient'],
                'laboratory_name'   => $item['laboratory'],
                'category_name'     => $item['category'],
                'unit_cost'         => $unitCost,
                'sale_price'        => $salePrice,
                'unit_of_measure'   => $item['unit_of_measure'],
            ]);

            if ($masterCreated && !empty($masterCreated['id'])) {
                $masterProduct = $masterCreated;
                $masterMap[$barcode] = $masterCreated;
                $stats['registered_master']++;
            }
        } elseif ($masterProduct) {
            $stats['matched_master']++;
        }

        // 2. Resolver Laboratorio local
        $labName = $masterProduct['laboratory_name'] ?? $item['laboratory'];
        $labId = null;
        if (!empty($labName)) {
            $lab = Laboratory::firstOrCreate(
                ['name' => trim($labName)],
                ['created_at' => now(), 'updated_at' => now()]
            );
            $labId = $lab->id;
        }

        // 3. Resolver Categoría local
        $catName = $masterProduct['category_name'] ?? $item['category'];
        $catId = null;
        if (!empty($catName)) {
            $cat = Category::firstOrCreate(
                ['name' => trim($catName)],
                ['created_at' => now(), 'updated_at' => now()]
            );
            $catId = $cat->id;
        }

        // 4. Nombre y atributos oficiales
        $finalName = !empty($masterProduct['name']) ? trim((string) $masterProduct['name']) : $item['name'];
        $finalActiveIngredient = !empty($masterProduct['active_ingredient'])
            ? trim((string) $masterProduct['active_ingredient'])
            : $item['active_ingredient'];

        $productData = [
            'name'                   => $finalName,
            'description'            => $item['name'],
            'barcode'                => $barcode,
            'active_ingredient'      => $finalActiveIngredient,
            'laboratory_id'          => $labId,
            'category_id'            => $catId,
            'unit_cost'              => $unitCost,
            'sale_price'             => $salePrice,
            'stock'                  => $targetStock,
            'unit_of_measure'        => $item['unit_of_measure'],
            'lotification_completed' => true,
            'is_active'              => true,
            'is_deleted'             => false,
            'sales_average'          => 0.0,
        ];

        // 5. Asignar ID oficial unificado si viene del Master o reutilizar producto existente
        $existingProduct = $existingProducts->get($barcode);
        if (!$existingProduct) {
            $existingProduct = Product::withoutGlobalScope('not_deleted')
                ->withTrashed()
                ->where('barcode', $barcode)
                ->first();
        }

        $isNew = !$existingProduct;

        if ($isNew && !empty($masterProduct['id'])) {
            $targetId = (int) $masterProduct['id'];
            if (!isset($existingIds[$targetId]) && !Product::withoutGlobalScope('not_deleted')->withTrashed()->where('id', $targetId)->exists()) {
                $productData['id'] = $targetId;
                $existingIds[$targetId] = true;
            }
        }

        if ($isNew) {
            try {
                $product = Product::create($productData);
            } catch (\Illuminate\Database\QueryException $e) {
                // Si falla por ID o por coincidencia concurrente de barcode, recuperar o reintentar
                if (isset($productData['id'])) {
                    unset($productData['id']);
                }
                $product = Product::withoutGlobalScope('not_deleted')
                    ->withTrashed()
                    ->where('barcode', $barcode)
                    ->first();

                if ($product) {
                    if ($product->trashed()) {
                        $product->restore();
                    }
                    $product->update($productData);
                    $stats['updated']++;
                } else {
                    $product = Product::create($productData);
                    $stats['created']++;
                }
            }
            $existingProducts->put($barcode, $product);
            $existingIds[$product->id] = true;
            if (!isset($product)) {
                $stats['created']++;
            }
        } else {
            $product = $existingProduct;
            if ($product->trashed()) {
                $product->restore();
            }
            $product->update($productData);
            $existingProducts->put($barcode, $product);
            $existingIds[$product->id] = true;
            $stats['updated']++;
        }

        // 6. Eliminar lotes existentes de este producto e insertar los lotes ajustados
        ProductLot::where('product_id', $product->id)->delete();

        $runningStock = 0.0;
        $currentUserId = Auth::id();

        foreach ($item['lots'] as $lot) {
            $qty = (float) $lot['quantity'];
            $cost = (float) $lot['unit_cost'];

            $createdLot = ProductLot::create([
                'product_id'      => $product->id,
                'lot_number'      => $lot['lot_number'],
                'expiration_date' => $lot['expiration_date'],
                'quantity'        => $qty,
                'unit_cost'       => $cost,
                'amount_usd'      => round($qty * $cost, 2),
            ]);

            $stats['total_lots_created']++;

            // Registrar movimiento de trazabilidad de ingreso/onboarding inicial si hay cantidad
            if ($qty > 0) {
                InventoryMovement::create([
                    'product_id'     => $product->id,
                    'product_lot_id' => $createdLot->id,
                    'movement_type'  => 'adjustment',
                    'quantity'       => $qty,
                    'invoice_id'     => null,
                    'supplier_id'    => null,
                    'order_id'       => null,
                    'user_id'        => $currentUserId,
                    'stock_before'   => $runningStock,
                    'stock_after'    => $runningStock + $qty,
                    'movement_date'  => now(),
                ]);

                $runningStock += $qty;
                $stats['traceability_movements_created']++;
            }
        }

        if (!empty($item['had_reduction'])) {
            $stats['lots_reduced_for_cap']++;
        }
        if (!empty($item['had_extension'])) {
            $stats['lots_extended_for_shortage']++;
        }
        $stats['total_consolidated_stock'] += $targetStock;
    }
}
