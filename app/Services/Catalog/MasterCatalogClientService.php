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
                    $response = Http::timeout(5)
                        ->withHeaders([
                            'X-Master-Key' => $masterKey,
                            'Accept'       => 'application/json',
                        ])
                        ->post("{$masterUrl}/products", $productData);

                    if ($response->successful()) {
                        $data = $response->json();
                        return $data['product'] ?? null;
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
}
