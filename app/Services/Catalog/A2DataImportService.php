<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\A2SyncTask;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class A2DataImportService
{
    public function __construct(
        protected MasterCatalogClientService $masterClient
    ) {}

    /**
     * Construye la previsualización adaptada cruzando con Proveedores y Productos del Master/Tenant.
     */
    public function buildPreview(array $extractedData, int $year = 2026): array
    {
        $suppliersRaw = $extractedData['suppliers'] ?? [];
        $productsRaw = $extractedData['products'] ?? [];
        $operationsRaw = $extractedData['operations'] ?? [];

        // 1. Homologación de Proveedores sin duplicar
        $suppliersPreview = $this->correlateSuppliers($suppliersRaw);

        // 2. Homologación de Productos contra Master y Tenant
        $productsPreview = $this->correlateProducts($productsRaw);

        // 3. Filtrar Operaciones y Compras del año en curso
        $operationsFiltered = array_values(array_filter($operationsRaw, function ($op) use ($year) {
            $date = $op['date'] ?? '';
            return str_starts_with((string)$date, (string)$year);
        }));

        return [
            'summary' => [
                'year' => $year,
                'total_suppliers' => count($suppliersPreview['suppliers']),
                'suppliers_matched' => $suppliersPreview['matched_count'],
                'suppliers_new' => $suppliersPreview['new_count'],
                'total_products' => count($productsPreview['products']),
                'products_matched_master' => $productsPreview['matched_master_count'],
                'total_operations_year' => count($operationsFiltered),
            ],
            'suppliers' => $suppliersPreview['suppliers'],
            'products_sample' => array_slice($productsPreview['products'], 0, 100),
            'operations_sample' => array_slice($operationsFiltered, 0, 100),
        ];
    }

    /**
     * Correlaciona proveedores de a2 contra los existentes del tenant y master.
     */
    protected function correlateSuppliers(array $a2Suppliers): array
    {
        $existing = Supplier::query()->get();
        $matchedCount = 0;
        $newCount = 0;

        $results = [];

        foreach ($a2Suppliers as $a2s) {
            $rawRif = trim((string)($a2s['rif'] ?? ''));
            $cleanRif = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $rawRif));
            $name = trim((string)($a2s['name'] ?? ''));

            $matchedSupplier = null;
            $matchReason = null;

            // Cruce 1: Por RIF exacto
            if (!empty($cleanRif)) {
                $matchedSupplier = $existing->first(function ($sup) use ($cleanRif) {
                    $dbRif = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$sup->rif));
                    return !empty($dbRif) && $dbRif === $cleanRif;
                });
                if ($matchedSupplier) {
                    $matchReason = 'Coincidencia por RIF (' . $matchedSupplier->rif . ')';
                }
            }

            // Cruce 2: Por Nombre similar si no hubo cruce por RIF
            if (!$matchedSupplier && !empty($name)) {
                $cleanName = strtoupper($name);
                $matchedSupplier = $existing->first(function ($sup) use ($cleanName) {
                    $dbName = strtoupper($sup->name);
                    return $dbName === $cleanName ||
                           (stripos($dbName, 'NENA') !== false && stripos($cleanName, 'NENA') !== false) ||
                           (stripos($dbName, 'MAFARTA') !== false && stripos($cleanName, 'MAFARTA') !== false) ||
                           (stripos($dbName, 'DROMEGA') !== false && stripos($cleanName, 'DROMEGA') !== false) ||
                           (stripos($dbName, 'DROCERCA') !== false && stripos($cleanName, 'DROCERCA') !== false) ||
                           (stripos($dbName, 'INTERCONTINENTAL') !== false && stripos($cleanName, 'INTERCONTINENTAL') !== false);
                });
                if ($matchedSupplier) {
                    $matchReason = 'Coincidencia por Nombre (' . $matchedSupplier->name . ')';
                }
            }

            if ($matchedSupplier) {
                $matchedCount++;
                $results[] = [
                    'a2_rif' => $rawRif,
                    'a2_name' => $name,
                    'a2_phone' => $a2s['phone'] ?? '',
                    'is_matched' => true,
                    'match_reason' => $matchReason,
                    'selected_supplier_id' => $matchedSupplier->id,
                    'matched_supplier_name' => $matchedSupplier->name,
                ];
            } else {
                $newCount++;
                $results[] = [
                    'a2_rif' => $rawRif,
                    'a2_name' => $name,
                    'a2_phone' => $a2s['phone'] ?? '',
                    'is_matched' => false,
                    'match_reason' => 'Nuevo Proveedor a registrar',
                    'selected_supplier_id' => null,
                    'matched_supplier_name' => null,
                ];
            }
        }

        return [
            'suppliers' => $results,
            'matched_count' => $matchedCount,
            'new_count' => $newCount,
        ];
    }

    /**
     * Correlaciona productos contra Catálogo Maestro y Tenant.
     */
    protected function correlateProducts(array $a2Products): array
    {
        $barcodes = [];
        foreach ($a2Products as $p) {
            $code = trim((string)($p['code'] ?? ''));
            if (!empty($code)) {
                $barcodes[] = $code;
            }
        }

        $masterMap = [];
        if (!empty($barcodes)) {
            try {
                $masterMap = $this->masterClient->lookupBulk(array_values(array_unique($barcodes)));
            } catch (\Throwable $e) {
                Log::warning('Error en lookupBulk Catálogo Maestro: ' . $e->getMessage());
            }
        }

        $matchedMasterCount = 0;
        $processedProducts = [];

        foreach ($a2Products as $p) {
            $code = trim((string)($p['code'] ?? ''));
            $masterItem = $masterMap[$code] ?? null;

            if ($masterItem) {
                $matchedMasterCount++;
                $processedProducts[] = [
                    'code' => $code,
                    'name' => $masterItem['name'] ?? $p['name'],
                    'master_matched' => true,
                    'master_id' => $masterItem['id'] ?? null,
                    'laboratory' => $masterItem['laboratory_name'] ?? '',
                    'category' => $p['category'] ?? '',
                ];
            } else {
                $processedProducts[] = [
                    'code' => $code,
                    'name' => $p['name'],
                    'master_matched' => false,
                    'master_id' => null,
                    'laboratory' => '',
                    'category' => $p['category'] ?? '',
                ];
            }
        }

        return [
            'products' => $processedProducts,
            'matched_master_count' => $matchedMasterCount,
        ];
    }

    /**
     * Ejecuta la persistencia atómica de los datos homologados en la BD del Tenant.
     */
    public function executeImport(array $payload): array
    {
        @ini_set('memory_limit', '1024M');
        @ini_set('max_execution_time', '900');

        return DB::transaction(function () use ($payload) {
            $suppliersData = $payload['suppliers'] ?? [];
            $stats = [
                'suppliers_created' => 0,
                'suppliers_linked' => 0,
                'products_imported' => 0,
            ];

            // 1. Procesar Proveedores
            foreach ($suppliersData as $s) {
                if (!empty($s['selected_supplier_id'])) {
                    $stats['suppliers_linked']++;
                } elseif (!empty($s['a2_name'])) {
                    Supplier::create([
                        'name' => trim($s['a2_name']),
                        'social_reason' => trim($s['a2_name']),
                        'rif' => !empty($s['a2_rif']) ? strtoupper(trim($s['a2_rif'])) : null,
                        'sales_phone' => !empty($s['a2_phone']) ? trim($s['a2_phone']) : null,
                        'type' => 'drogueria',
                        'is_active' => true,
                        'is_indexed' => true,
                    ]);
                    $stats['suppliers_created']++;
                }
            }

            return $stats;
        });
    }
}
