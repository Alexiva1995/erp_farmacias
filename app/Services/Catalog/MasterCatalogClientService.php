<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MasterCatalogClientService
{
    public function __construct(
        protected MasterCatalogService $localMasterService
    ) {}

    /**
     * Consultar un producto por código de barra en el catálogo maestro (directo en tovaerp_master).
     */
    public function lookupByBarcode(string $barcode): array
    {
        $res = $this->localMasterService->lookupByBarcode($barcode);
        $res['source'] = 'master_db';
        return $res;
    }

    /**
     * Consultar múltiples productos por códigos de barra en el catálogo maestro (directo en tovaerp_master).
     */
    public function lookupBulk(array $barcodes): array
    {
        return $this->localMasterService->lookupBulk($barcodes);
    }

    /**
     * Registrar producto en el Catálogo Maestro para obtener ID oficial unificado.
     */
    public function registerProductInMaster(array $productData): ?array
    {
        $res = $this->localMasterService->registerMasterProduct($productData);
        return $res['product'] ?? null;
    }

    /**
     * Registrar laboratorio en el Catálogo Maestro para obtener ID oficial unificado.
     */
    public function registerLaboratoryInMaster(array $data): ?array
    {
        $res = $this->localMasterService->registerMasterLaboratory($data);
        return $res['laboratory'] ?? null;
    }

    /**
     * Registrar grupo en el Catálogo Maestro para obtener ID oficial unificado.
     */
    public function registerGroupInMaster(array $data): ?array
    {
        $res = $this->localMasterService->registerMasterGroup($data);
        return $res['group'] ?? null;
    }

    /**
     * Registrar proveedor en el Catálogo Maestro para obtener ID oficial unificado.
     */
    public function registerSupplierInMaster(array $data): ?array
    {
        $res = $this->localMasterService->registerMasterSupplier($data);
        return $res['supplier'] ?? null;
    }

    /**
     * Registrar origen en el Catálogo Maestro para obtener ID oficial unificado.
     */
    public function registerOriginInMaster(array $data): ?array
    {
        $res = $this->localMasterService->registerMasterOrigin($data);
        return $res['origin'] ?? null;
    }

    /**
     * Registrar categoría en el Catálogo Maestro para obtener ID oficial unificado.
     */
    public function registerCategoryInMaster(array $data): ?array
    {
        $res = $this->localMasterService->registerMasterCategory($data);
        return $res['category'] ?? null;
    }

    /**
     * Asegura que el producto exista localmente en el tenant consultándolo por código de barra en el Master.
     * Si no existe localmente pero sí en el Master, crea el producto y sus dependencias (laboratorio, categoría, origen)
     * manteniendo el ID oficial unificado del Master.
     */
    public function ensureLocalProductFromBarcode(string $barcode): ?Product
    {
        $barcode = trim($barcode);
        if (empty($barcode)) {
            return null;
        }

        // 1. Si ya existe localmente en el tenant, retornarlo directamente
        $local = Product::where('barcode', $barcode)->first();
        if ($local) {
            return $local;
        }

        // 2. Consultar al Master (tovaerp_master)
        $lookup = $this->lookupByBarcode($barcode);
        if (empty($lookup['found']) || empty($lookup['product'])) {
            return null;
        }

        $masterData = $lookup['product'];
        $masterId = (int) ($masterData['id'] ?? 0);
        if ($masterId <= 0) {
            return null;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        try {
            // 3. Resolver Laboratorio local conservando el ID oficial del Master
            $labId = $masterData['laboratory_id'] ?? null;
            if ($labId && !empty($masterData['laboratory_name']) && Schema::hasTable('laboratories')) {
                DB::table('laboratories')->updateOrInsert(
                    ['id' => $labId],
                    ['name' => $masterData['laboratory_name'], 'updated_at' => now()]
                );
            }

            // 4. Resolver Categoría local conservando el ID oficial del Master
            $catId = $masterData['category_id'] ?? null;
            if ($catId && !empty($masterData['category_name']) && Schema::hasTable('categories')) {
                DB::table('categories')->updateOrInsert(
                    ['id' => $catId],
                    ['name' => $masterData['category_name'], 'updated_at' => now()]
                );
            }

            // 5. Resolver Origen local conservando el ID oficial del Master
            $origId = $masterData['origin_id'] ?? null;
            if ($origId && !empty($masterData['origin_name']) && Schema::hasTable('origins')) {
                DB::table('origins')->updateOrInsert(
                    ['id' => $origId],
                    ['name' => $masterData['origin_name'], 'updated_at' => now()]
                );
            }

            // 6. Insertar producto en el Tenant con el mismo ID oficial del Master
            $productColumns = Schema::getColumnListing('products');
            $insertData = [
                'name'              => $masterData['name'],
                'barcode'           => $masterData['barcode'] ?? $barcode,
                'active_ingredient' => $masterData['active_ingredient'] ?? null,
                'laboratory_id'     => $labId,
                'category_id'       => $catId,
                'origin_id'         => $origId,
                'sale_price'        => (float) ($masterData['sale_price'] ?? 0),
                'unit_cost'         => (float) ($masterData['unit_cost'] ?? 0),
                'description'       => $masterData['description'] ?? null,
                'presentation'      => $masterData['presentation'] ?? null,
                'unit_of_measure'   => $masterData['unit_of_measure'] ?? null,
                'psychotropic'      => (bool) ($masterData['psychotropic'] ?? false),
                'iva'               => (float) ($masterData['iva'] ?? 0),
                'photo_url'         => $masterData['photo_url'] ?? null,
                'is_active'         => true,
                'is_deleted'        => false,
                'created_at'        => now(),
                'updated_at'        => now(),
            ];

            $insertData = array_intersect_key($insertData, array_flip($productColumns));

            DB::table('products')->updateOrInsert(
                ['id' => $masterId],
                $insertData
            );
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }

        return Product::find($masterId);
    }

    /**
     * Descarga en bloque catálogos del Master.
     */
    public function fetchMasterEntities(array $entities = ['laboratories', 'origins', 'groups', 'categories', 'suppliers']): array
    {
        $data = $this->localMasterService->exportEntities($entities);
        return [
            'success' => true,
            'data'    => $data,
            'status'  => 200,
        ];
    }
}
