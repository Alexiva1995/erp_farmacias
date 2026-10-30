<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Laboratory;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Servicio para sincronizar laboratorios desde la base de datos Master
 * y reasignar automáticamente los productos del tenant a su laboratorio real.
 */
class MasterLaboratorySyncService
{
    /**
     * Obtiene el nombre de la conexión central/Master.
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
     * Sincroniza todos los laboratorios de la base central hacia el tenant local.
     *
     * @return array{imported: int, total_master: int}
     */
    public function syncLaboratoriesFromMaster(): array
    {
        $central = $this->getCentralConnectionName();

        $masterLabs = DB::connection($central)
            ->table('laboratories')
            ->select('id', 'name')
            ->whereNotNull('name')
            ->whereRaw("TRIM(name) != ''")
            ->orderBy('id')
            ->get();

        $imported = 0;
        $localLabsCache = Laboratory::pluck('id', 'name')->toArray();

        foreach ($masterLabs as $mLab) {
            $cleanName = trim((string) $mLab->name);
            if (!isset($localLabsCache[$cleanName])) {
                $lab = Laboratory::create([
                    'name' => $cleanName,
                ]);
                $localLabsCache[$cleanName] = $lab->id;
                $imported++;
            }
        }

        return [
            'imported' => $imported,
            'total_master' => $masterLabs->count(),
        ];
    }

    /**
     * Cruza los productos del tenant contra el Master y reasigna su laboratorio real.
     * Prioridad 1: Por código de barras (barcode) contra products del Master.
     * Prioridad 2: Por nombre del producto contra products del Master.
     * Prioridad 3: Por código de barras contra product_suppliers del Master.
     *
     * @param bool $onlyGenerales Si es true, solo reubica los que están en 'GENERALES' o sin laboratorio.
     * @return array{remapped: int, remaining_generales: int, total_evaluated: int}
     */
    public function remapTenantProductsLaboratories(bool $onlyGenerales = true): array
    {
        // 1. Asegurar laboratorios del Master en el tenant
        $this->syncLaboratoriesFromMaster();

        $central = $this->getCentralConnectionName();
        $generalesLab = Laboratory::where('name', 'GENERALES')->orWhere('name', 'Generales')->first();
        $generalesId = $generalesLab?->id;

        // 2. Caché de laboratorios del Master (id => name)
        $masterLabsById = DB::connection($central)
            ->table('laboratories')
            ->pluck('name', 'id')
            ->toArray();

        // 3. Caché de productos del Master con laboratorio:
        // A) Indexado por código de barras
        $masterProductsByBarcode = DB::connection($central)
            ->table('products')
            ->whereNotNull('laboratory_id')
            ->whereNotNull('barcode')
            ->whereRaw("TRIM(barcode) != ''")
            ->select('barcode', 'laboratory_id')
            ->get()
            ->keyBy(fn($item) => trim((string) $item->barcode));

        // B) Indexado por nombre de producto normalizado
        $masterProductsByName = DB::connection($central)
            ->table('products')
            ->whereNotNull('laboratory_id')
            ->whereNotNull('name')
            ->select('name', 'laboratory_id')
            ->get()
            ->keyBy(fn($item) => strtoupper(trim((string) $item->name)));

        // C) Caché de ofertas de proveedores en Master con laboratorio informado
        $masterSupplierOffersByBarcode = DB::connection($central)
            ->table('product_suppliers')
            ->whereNotNull('laboratory')
            ->whereRaw("TRIM(laboratory) != ''")
            ->whereNotNull('barcode_match')
            ->whereRaw("TRIM(barcode_match) != ''")
            ->select('barcode_match', 'laboratory')
            ->get()
            ->keyBy(fn($item) => trim((string) $item->barcode_match));

        // Caché local de laboratorios (name => id)
        $localLabsByName = Laboratory::pluck('id', 'name')->toArray();

        // 4. Obtener productos del tenant a evaluar
        $query = Product::query();
        if ($onlyGenerales && $generalesId) {
            $query->where(function ($q) use ($generalesId) {
                $q->where('laboratory_id', $generalesId)
                  ->orWhereNull('laboratory_id');
            });
        }

        $products = $query->get();
        $remapped = 0;

        foreach ($products as $product) {
            $cleanBarcode = trim((string) ($product->barcode ?? ''));
            $cleanName = strtoupper(trim((string) ($product->name ?? '')));
            $resolvedLabName = null;

            // Prioridad 1: Código de barras en products del Master
            if ($cleanBarcode !== '' && isset($masterProductsByBarcode[$cleanBarcode])) {
                $mLabId = $masterProductsByBarcode[$cleanBarcode]->laboratory_id;
                $candidate = $masterLabsById[$mLabId] ?? null;
                if ($candidate && strtoupper(trim($candidate)) !== 'GENERALES') {
                    $resolvedLabName = $candidate;
                }
            }

            // Prioridad 2: Nombre de producto en products del Master
            if (!$resolvedLabName && $cleanName !== '' && isset($masterProductsByName[$cleanName])) {
                $mLabId = $masterProductsByName[$cleanName]->laboratory_id;
                $candidate = $masterLabsById[$mLabId] ?? null;
                if ($candidate && strtoupper(trim($candidate)) !== 'GENERALES') {
                    $resolvedLabName = $candidate;
                }
            }

            // Prioridad 3: Código de barras en product_suppliers del Master
            if (!$resolvedLabName && $cleanBarcode !== '' && isset($masterSupplierOffersByBarcode[$cleanBarcode])) {
                $resolvedLabName = trim((string) $masterSupplierOffersByBarcode[$cleanBarcode]->laboratory);
            }

            // Si resolvimos un laboratorio válido y distinto a Generales
            if ($resolvedLabName && strtoupper($resolvedLabName) !== 'GENERALES') {
                // Obtener o crear en el tenant local
                if (!isset($localLabsByName[$resolvedLabName])) {
                    $newLab = Laboratory::firstOrCreate(['name' => $resolvedLabName]);
                    $localLabsByName[$resolvedLabName] = $newLab->id;
                }

                $targetLabId = $localLabsByName[$resolvedLabName];

                if ($product->laboratory_id !== $targetLabId) {
                    $product->laboratory_id = $targetLabId;
                    $product->save();
                    $remapped++;
                }
            }
        }

        $remainingGenerales = 0;
        if ($generalesId) {
            $remainingGenerales = Product::where('laboratory_id', $generalesId)->count();
        }

        return [
            'remapped' => $remapped,
            'remaining_generales' => $remainingGenerales,
            'total_evaluated' => $products->count(),
        ];
    }
}
