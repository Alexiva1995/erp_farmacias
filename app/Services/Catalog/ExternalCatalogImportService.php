<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\Laboratory;
use App\Models\Origin;
use App\Models\Product;
use App\Models\ProductLot;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ExternalCatalogImportService
{
    public function __construct(
        protected MasterCatalogClientService $masterClient
    ) {}

    /**
     * Procesa la importación masiva de catálogo y ventas externas desde Excel o CSV.
     */
    public function processImport(UploadedFile $file, ?string $cutoffDate = null, bool $isInitialLoad = true): array
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(300);

        $date = $cutoffDate ? Carbon::parse($cutoffDate) : Carbon::today();
        $monthsElapsed = max(1, (int) $date->month);

        $rows = $this->extractRowsFromFile($file);

        if (empty($rows)) {
            throw new \RuntimeException('El archivo no contiene filas de datos o el formato no es válido.');
        }

        $stats = [
            'total_rows'           => count($rows),
            'created'              => 0,
            'updated'              => 0,
            'matched_with_master'  => 0,
            'lots_updated'         => 0,
            'total_stock'          => 0,
        ];

        // Lotes de 100 registros para alta velocidad y transacciones atómicas
        $chunks = array_chunk($rows, 100);

        foreach ($chunks as $chunk) {
            DB::transaction(function () use ($chunk, $date, $monthsElapsed, $isInitialLoad, &$stats) {
                foreach ($chunk as $row) {
                    $this->processRow($row, $date, $monthsElapsed, $isInitialLoad, $stats);
                }
            });
        }

        return $stats;
    }

    /**
     * Extrae las filas del archivo ya sea XLSX, XLS o CSV normalizando encabezados.
     */
    private function extractRowsFromFile(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $path = $file->getRealPath();

        if (in_array($extension, ['xlsx', 'xls'])) {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray(null, true, true, false);
        } else {
            // CSV / TXT
            $handle = fopen($path, 'r');
            $data = [];
            while (($line = fgetcsv($handle, 0, ';')) !== false || ($line = fgetcsv($handle, 0, ',')) !== false) {
                if (!empty($line)) {
                    $data[] = $line;
                }
            }
            fclose($handle);
        }

        if (count($data) < 2) {
            return [];
        }

        // Obtener encabezados limpios
        $rawHeaders = array_shift($data);
        $headers = [];
        foreach ($rawHeaders as $idx => $h) {
            $cleaned = strtoupper(trim((string) $h));
            $headers[$idx] = $cleaned;
        }

        $normalizedRows = [];

        foreach ($data as $row) {
            if (empty($row) || !is_array($row)) {
                continue;
            }

            $mapped = [];
            foreach ($row as $idx => $val) {
                $headerName = $headers[$idx] ?? (string) $idx;
                $mapped[$headerName] = is_string($val) ? trim($val) : $val;
            }

            // Normalizar referencias y códigos
            $barcode = (string) ($mapped['PRD_REFERENCIA'] ?? $mapped['REFERENCIA'] ?? $mapped['BARCODE'] ?? $mapped['CODIGO_BARRA'] ?? '');
            $barcode = preg_replace('/^\.+/', '', trim($barcode));

            if (empty($barcode)) {
                continue;
            }

            $normalizedRows[] = [
                'code'        => (string) ($mapped['PRD_CODIGO'] ?? $mapped['CODIGO'] ?? ''),
                'barcode'     => $barcode,
                'name'        => (string) ($mapped['PRD_DESCRIPCION'] ?? $mapped['DESCRIPCION'] ?? $mapped['NAME'] ?? ''),
                'stock'       => (float) str_replace(',', '.', (string) ($mapped['EIN_EXISTENCIA'] ?? $mapped['EXISTENCIA'] ?? $mapped['STOCK'] ?? '0')),
                'cost'        => (float) str_replace(',', '.', (string) ($mapped['TPC_COSTOACTUAL'] ?? $mapped['COSTO'] ?? $mapped['COST'] ?? '0')),
                'tax_type'    => strtoupper((string) ($mapped['DIM_EXENTO'] ?? $mapped['EXENTO'] ?? $mapped['IVA'] ?? 'E')),
                'sales_accum' => (float) str_replace(',', '.', (string) ($mapped['EIN_EXISTENCIADIFERIDA'] ?? $mapped['VENTAS_ACUMULADAS'] ?? $mapped['VENTAS'] ?? '0')),
            ];
        }

        return $normalizedRows;
    }

    /**
     * Procesa una fila individual de producto, homologando con Master y calculando ventas.
     */
    private function processRow(array $row, Carbon $date, int $monthsElapsed, bool $isInitialLoad, array &$stats): void
    {
        $barcode = $row['barcode'];
        $rawName = $row['name'];
        $stock = max(0, (int) round($row['stock']));
        $cost = max(0.0, (float) $row['cost']);
        $isGravable = ($row['tax_type'] === 'G' || $row['tax_type'] === '1' || $row['tax_type'] === 'SI');
        $currentSalesAccum = max(0.0, (float) $row['sales_accum']);

        // 1. Consultar Catálogo Maestro por código de barra
        $masterLookup = $this->masterClient->lookupByBarcode($barcode);
        $masterProduct = $masterLookup['found'] ? ($masterLookup['product'] ?? null) : null;

        // Regla: Si está en Master, se mantiene el nombre oficial del Master; si no, el del archivo
        $finalName = !empty($masterProduct['name']) ? trim((string) $masterProduct['name']) : $rawName;

        if ($masterLookup['found']) {
            $stats['matched_with_master']++;
        }

        // 2. Buscar producto existente localmente
        $product = Product::where('barcode', $barcode)->first();
        $isNew = !$product;

        // 3. Cálculo de Promedio de Ventas Mensual
        $salesAverage = 0.0;
        if ($isInitialLoad || !$product || empty($product->external_sales_date)) {
            // Primera carga del año: Promedio = Acumulado / Meses transcurridos
            $salesAverage = round($currentSalesAccum / $monthsElapsed, 2);
        } else {
            // Carga periódica: Calcular delta de ventas
            $prevAccum = (float) ($product->external_accumulated_sales ?? 0);
            $delta = max(0.0, $currentSalesAccum - $prevAccum);
            
            if ($delta > 0) {
                // Incorporar el delta al promedio mensual
                $salesAverage = round(($delta + ($product->sales_average ?? 0)) / 2, 2);
            } else {
                $salesAverage = (float) ($product->sales_average ?? round($currentSalesAccum / $monthsElapsed, 2));
            }
        }

        // 4. Datos a persistir en Product
        $productData = [
            'name'                       => $finalName,
            'description'                => $rawName,
            'barcode'                    => $barcode,
            'stock'                      => $stock,
            'unit_cost'                  => $cost,
            'sale_price'                 => $cost,
            'iva'                        => $isGravable,
            'is_colombian_origin'        => false,
            'is_novaventa'               => false,
            'psychotropic'               => false,
            'is_active'                  => true,
            'is_deleted'                 => false,
            'is_favorite'                => false,
            'no_pvp'                     => false,
            'lotification_completed'     => true,
            'sales_average'              => $salesAverage,
            'sales_average_updated_at'   => now(),
            'external_accumulated_sales' => $currentSalesAccum,
            'external_sales_date'        => $date->format('Y-m-d'),
        ];

        // Homologar relaciones si provienen del Master
        if ($masterProduct) {
            if (!empty($masterProduct['laboratory_id'])) {
                $productData['laboratory_id'] = $masterProduct['laboratory_id'];
            }
            if (!empty($masterProduct['category_id'])) {
                $productData['category_id'] = $masterProduct['category_id'];
            }
            if (!empty($masterProduct['origin_id'])) {
                $productData['origin_id'] = $masterProduct['origin_id'];
            }
            if (!empty($masterProduct['group_id'])) {
                $productData['group_id'] = $masterProduct['group_id'];
            }
        }

        if ($isNew) {
            $product = Product::create($productData);
            $stats['created']++;
        } else {
            $product->update($productData);
            $stats['updated']++;
        }

        // 5. Crear / Actualizar Lote de Inventario en product_lots
        $lot = ProductLot::where('product_id', $product->id)->first();

        if ($lot) {
            $lot->update([
                'quantity'   => $stock,
                'unit_cost'  => $cost,
                'amount_usd' => round($stock * $cost, 2),
            ]);
        } else {
            ProductLot::create([
                'product_id'      => $product->id,
                'lot_number'      => 'LOT-INICIAL',
                'expiration_date' => '2028-12-31',
                'quantity'        => $stock,
                'unit_cost'       => $cost,
                'amount_usd'      => round($stock * $cost, 2),
            ]);
        }

        $stats['lots_updated']++;
        $stats['total_stock'] += $stock;
    }
}
