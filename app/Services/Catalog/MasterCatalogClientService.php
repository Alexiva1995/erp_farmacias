<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MasterCatalogClientService
{
    public function __construct(
        protected MasterCatalogService $localMasterService
    ) {}

    /**
     * Consultar un producto por código de barra en el catálogo maestro.
     */
    public function lookupByBarcode(string $barcode): array
    {
        $barcode = trim($barcode);
        if (empty($barcode)) {
            return ['found' => false, 'product' => null];
        }

        $role = config('catalog.role', 'standalone');

        // Si esta instancia es esclava, consultar el endpoint HTTP del Master
        if ($role === 'slave') {
            $masterUrl = config('catalog.master_url');
            $masterKey = config('catalog.master_key');

            if (!empty($masterUrl)) {
                try {
                    $response = Http::timeout(4)
                        ->withHeaders([
                            'X-Master-Key' => $masterKey,
                            'Accept'       => 'application/json',
                        ])
                        ->get("{$masterUrl}/lookup", ['barcode' => $barcode]);

                    if ($response->successful()) {
                        $data = $response->json();
                        if (!empty($data['found']) && !empty($data['product'])) {
                            $data['source'] = 'master_remote';
                            return $data;
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning("Error al consultar Catálogo Maestro remoto para código {$barcode}: " . $e->getMessage());
                }
            }
        }

        // Si es master o standalone, buscar localmente
        $localResult = $this->localMasterService->lookupByBarcode($barcode);
        $localResult['source'] = 'local';

        return $localResult;
    }

    /**
     * Consultar múltiples productos por códigos de barra en el catálogo maestro (Bulk Lookup).
     */
    public function lookupBulk(array $barcodes): array
    {
        $cleanBarcodes = array_values(array_filter(array_unique(array_map('trim', $barcodes))));
        if (empty($cleanBarcodes)) {
            return [];
        }

        $role = config('catalog.role', 'standalone');

        if ($role === 'slave') {
            $masterUrl = config('catalog.master_url');
            $masterKey = config('catalog.master_key');

            if (!empty($masterUrl)) {
                try {
                    $response = Http::timeout(10)
                        ->withHeaders([
                            'X-Master-Key' => $masterKey,
                            'Accept'       => 'application/json',
                        ])
                        ->post("{$masterUrl}/lookup-bulk", ['barcodes' => $cleanBarcodes]);

                    if ($response->successful()) {
                        $json = $response->json();
                        return $json['data'] ?? [];
                    }
                } catch (\Throwable $e) {
                    Log::warning("Error al consultar Catálogo Maestro remoto en bloque: " . $e->getMessage());
                }
            }
        }

        return $this->localMasterService->lookupBulk($cleanBarcodes);
    }

    /**
     * Registrar producto en el Catálogo Maestro para obtener ID oficial unificado.
     */
    public function registerProductInMaster(array $productData): ?array
    {
        $role = config('catalog.role', 'standalone');

        if ($role === 'slave') {
            $masterUrl = config('catalog.master_url');
            $masterKey = config('catalog.master_key');

            if (!empty($masterUrl)) {
                try {
                    $payload = $productData;
                    if (isset($payload['iva'])) {
                        $payload['iva'] = (bool) $payload['iva'];
                    }

                    $response = Http::timeout(5)
                        ->withHeaders([
                            'X-Master-Key' => $masterKey,
                            'Accept'       => 'application/json',
                        ])
                        ->post("{$masterUrl}/products", $payload);

                    if ($response->successful()) {
                        $data = $response->json();
                        return $data['product'] ?? null;
                    } else {
                        Log::error("Error Master Catalog HTTP {$response->status()}: " . $response->body());
                    }
                } catch (\Throwable $e) {
                    Log::error("Error al registrar producto en Catálogo Maestro remoto: " . $e->getMessage());
                }
            }
        }

        return null;
    }

    /**
     * Registrar laboratorio en el Catálogo Maestro para obtener ID oficial unificado.
     */
    public function registerLaboratoryInMaster(array $data): ?array
    {
        $role = config('catalog.role', 'standalone');

        if ($role === 'slave') {
            $masterUrl = config('catalog.master_url');
            $masterKey = config('catalog.master_key');

            if (!empty($masterUrl)) {
                try {
                    $response = Http::timeout(5)
                        ->withHeaders([
                            'X-Master-Key' => $masterKey,
                            'Accept'       => 'application/json',
                        ])
                        ->post("{$masterUrl}/laboratories", $data);

                    if ($response->successful()) {
                        $responseData = $response->json();
                        return $responseData['laboratory'] ?? null;
                    }
                } catch (\Throwable $e) {
                    Log::error("Error al registrar laboratorio en Catálogo Maestro remoto: " . $e->getMessage());
                }
            }
        }

        return null;
    }

    /**
     * Registrar grupo en el Catálogo Maestro para obtener ID oficial unificado.
     */
    public function registerGroupInMaster(array $data): ?array
    {
        $role = config('catalog.role', 'standalone');

        if ($role === 'slave') {
            $masterUrl = config('catalog.master_url');
            $masterKey = config('catalog.master_key');

            if (!empty($masterUrl)) {
                try {
                    $response = Http::timeout(5)
                        ->withHeaders([
                            'X-Master-Key' => $masterKey,
                            'Accept'       => 'application/json',
                        ])
                        ->post("{$masterUrl}/groups", $data);

                    if ($response->successful()) {
                        $responseData = $response->json();
                        return $responseData['group'] ?? null;
                    }
                } catch (\Throwable $e) {
                    Log::error("Error al registrar grupo en Catálogo Maestro remoto: " . $e->getMessage());
                }
            }
        }

        return null;
    }

    /**
     * Registrar proveedor en el Catálogo Maestro para obtener ID oficial unificado.
     */
    public function registerSupplierInMaster(array $data): ?array
    {
        $role = config('catalog.role', 'standalone');

        if ($role === 'slave') {
            $masterUrl = config('catalog.master_url');
            $masterKey = config('catalog.master_key');

            if (!empty($masterUrl)) {
                try {
                    $response = Http::timeout(5)
                        ->withHeaders([
                            'X-Master-Key' => $masterKey,
                            'Accept'       => 'application/json',
                        ])
                        ->post("{$masterUrl}/suppliers", $data);

                    if ($response->successful()) {
                        $responseData = $response->json();
                        return $responseData['supplier'] ?? null;
                    }
                } catch (\Throwable $e) {
                    Log::error("Error al registrar proveedor en Catálogo Maestro remoto: " . $e->getMessage());
                }
            }
        }

        return null;
    }

    /**
     * Registrar origen en el Catálogo Maestro para obtener ID oficial unificado.
     */
    public function registerOriginInMaster(array $data): ?array
    {
        $role = config('catalog.role', 'standalone');

        if ($role === 'slave') {
            $masterUrl = config('catalog.master_url');
            $masterKey = config('catalog.master_key');

            if (!empty($masterUrl)) {
                try {
                    $response = Http::timeout(5)
                        ->withHeaders([
                            'X-Master-Key' => $masterKey,
                            'Accept'       => 'application/json',
                        ])
                        ->post("{$masterUrl}/origins", $data);

                    if ($response->successful()) {
                        $responseData = $response->json();
                        return $responseData['origin'] ?? null;
                    }
                } catch (\Throwable $e) {
                    Log::error("Error al registrar origen en Catálogo Maestro remoto: " . $e->getMessage());
                }
            }
        }

        return null;
    }

    /**
     * Registrar categoría en el Catálogo Maestro para obtener ID oficial unificado.
     */
    public function registerCategoryInMaster(array $data): ?array
    {
        $role = config('catalog.role', 'standalone');

        if ($role === 'slave') {
            $masterUrl = config('catalog.master_url');
            $masterKey = config('catalog.master_key');

            if (!empty($masterUrl)) {
                try {
                    $response = Http::timeout(5)
                        ->withHeaders([
                            'X-Master-Key' => $masterKey,
                            'Accept'       => 'application/json',
                        ])
                        ->post("{$masterUrl}/categories", $data);

                    if ($response->successful()) {
                        $responseData = $response->json();
                        return $responseData['category'] ?? null;
                    }
                } catch (\Throwable $e) {
                    Log::error("Error al registrar categoría en Catálogo Maestro remoto: " . $e->getMessage());
                }
            }
        }

        return null;
    }

    /**
     * Asegura que el producto exista localmente consultándolo por código de barra en el Master.
     * Si no existe localmente pero sí en el Master, crea el producto y sus dependencias (laboratorio, categoría, origen)
     * manteniendo el ID unificado oficial.
     */
    public function ensureLocalProductFromBarcode(string $barcode): ?Product
    {
        $barcode = trim($barcode);
        if (empty($barcode)) {
            return null;
        }

        // 1. Si ya existe localmente, retornarlo directamente
        $local = Product::where('barcode', $barcode)->first();
        if ($local) {
            return $local;
        }

        // 2. Consultar al Master
        $lookup = $this->lookupByBarcode($barcode);
        if (empty($lookup['found']) || empty($lookup['product'])) {
            return null;
        }

        $masterData = $lookup['product'];
        $masterId = (int) ($masterData['id'] ?? 0);
        if ($masterId <= 0) {
            return null;
        }

        // 3. Resolver Laboratorio local
        $labId = null;
        if (!empty($masterData['laboratory_name'])) {
            $lab = \Illuminate\Support\Facades\DB::table('laboratories')
                ->where('name', $masterData['laboratory_name'])
                ->first();
            if (!$lab) {
                $labId = \Illuminate\Support\Facades\DB::table('laboratories')->insertGetId([
                    'name'       => $masterData['laboratory_name'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $labId = $lab->id;
            }
        }

        // 4. Resolver Categoría local
        $catId = null;
        if (!empty($masterData['category_name'])) {
            $cat = \Illuminate\Support\Facades\DB::table('categories')
                ->where('name', $masterData['category_name'])
                ->first();
            if (!$cat) {
                $catId = \Illuminate\Support\Facades\DB::table('categories')->insertGetId([
                    'name'       => $masterData['category_name'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $catId = $cat->id;
            }
        }

        // 5. Resolver Origen local
        $originId = null;
        if (!empty($masterData['origin_name'])) {
            $orig = \Illuminate\Support\Facades\DB::table('origins')
                ->where('name', $masterData['origin_name'])
                ->first();
            if (!$orig) {
                $originId = \Illuminate\Support\Facades\DB::table('origins')->insertGetId([
                    'name'       => $masterData['origin_name'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $originId = $orig->id;
            }
        }

        // 6. Insertar producto localmente con el mismo ID oficial del Master
        \Illuminate\Support\Facades\DB::table('products')->updateOrInsert(
            ['id' => $masterId],
            [
                'name'              => $masterData['name'],
                'barcode'           => $masterData['barcode'] ?? $barcode,
                'active_ingredient' => $masterData['active_ingredient'] ?? null,
                'laboratory_id'     => $labId,
                'category_id'       => $catId,
                'origin_id'         => $originId,
                'sale_price'        => (float) ($masterData['sale_price'] ?? 0),
                'unit_cost'         => (float) ($masterData['unit_cost'] ?? 0),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );

        return Product::find($masterId);
    }

    /**
     * Descarga en bloque catálogos del Master (laboratorios, categorías, proveedores, orígenes, grupos).
     */
    public function fetchMasterEntities(array $entities = ['laboratories', 'origins', 'groups', 'categories', 'suppliers']): array
    {
        $role = config('catalog.role', 'standalone');

        if ($role === 'slave') {
            $masterUrl = config('catalog.master_url');
            $masterKey = config('catalog.master_key');

            if (!empty($masterUrl)) {
                try {
                    $response = Http::timeout(20)
                        ->withHeaders([
                            'X-Master-Key' => $masterKey,
                            'Accept'       => 'application/json',
                        ])
                        ->get("{$masterUrl}/bulk-export", [
                            'entities' => implode(',', $entities),
                            'api_key'  => $masterKey,
                        ]);

                    if ($response->successful()) {
                        $json = $response->json();
                        return [
                            'success' => true,
                            'data'    => $json['data'] ?? [],
                            'status'  => $response->status(),
                        ];
                    } else {
                        Log::error("Error Master Catalog HTTP {$response->status()}: " . $response->body());
                        return [
                            'success' => false,
                            'error'   => "HTTP {$response->status()}: " . $response->body(),
                            'status'  => $response->status(),
                            'data'    => [],
                        ];
                    }
                } catch (\Throwable $e) {
                    Log::error("Error al descargar entidades del Catálogo Maestro: " . $e->getMessage());
                    return [
                        'success' => false,
                        'error'   => $e->getMessage(),
                        'data'    => [],
                    ];
                }
            }
        }

        return ['success' => false, 'data' => []];
    }
}

