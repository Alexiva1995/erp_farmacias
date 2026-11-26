<?php

declare(strict_types=1);

namespace App\Services\Suppliers;

use App\Contracts\Suppliers\CristmedicalsScraperServiceInterface;
use App\Contracts\Suppliers\DrocercaScraperServiceInterface;
use App\Contracts\Suppliers\DromegaScraperServiceInterface;
use App\Contracts\Suppliers\DronenaScraperServiceInterface;
use App\Contracts\Suppliers\DrosymcaScraperServiceInterface;
use App\Contracts\Suppliers\MafartaScraperServiceInterface;
use App\Contracts\Suppliers\SupplierFinancialSyncServiceInterface;
use App\Enums\ScraperKey;
use App\Jobs\SyncSupplierFinancialScraperJob;
use App\Models\Supplier;
use App\Models\SupplierScraper;
use Illuminate\Support\Facades\Log;

class SupplierFinancialSyncService implements SupplierFinancialSyncServiceInterface
{
    /**
     * Resuelve el modelo Supplier a partir de ID o instancia.
     */
    protected function resolveSupplier(Supplier|int $supplier): ?Supplier
    {
        if ($supplier instanceof Supplier) {
            return $supplier->relationLoaded('scrapers') ? $supplier : $supplier->load('scrapers');
        }

        return Supplier::with('scrapers')->find($supplier);
    }

    /**
     * Determina el tipo de scraper aplicable al proveedor.
     */
    public function detectScraperType(Supplier|int $supplier): ?string
    {
        $sup = $this->resolveSupplier($supplier);
        if (!$sup) {
            return null;
        }

        $name = strtoupper((string) $sup->name);
        $scraperKeys = $sup->scrapers->map(
            fn ($s) => $s->scraper_key instanceof ScraperKey ? $s->scraper_key->value : strtolower((string) $s->scraper_key)
        )->toArray();

        if (str_contains($name, 'DRONENA') || str_contains($name, 'NENA') || in_array('dronena', $scraperKeys, true)) {
            return 'dronena';
        }
        if (str_contains($name, 'DROCERCA') || in_array('drocerca', $scraperKeys, true)) {
            return 'drocerca';
        }
        if (str_contains($name, 'MAFARTA') || str_contains($name, 'COBECA') || in_array('mafarta', $scraperKeys, true)) {
            return 'mafarta';
        }
        if (str_contains($name, 'CRISTMEDICALS') || str_contains($name, 'CRIST') || in_array('cristmedicals', $scraperKeys, true) || in_array($sup->id, [3, 21, 1002])) {
            return 'cristmedicals';
        }
        if (str_contains($name, 'DROMEGA') || str_contains($name, 'MEGA') || in_array('dromega', $scraperKeys, true) || in_array($sup->id, [9, 15, 38, 1005])) {
            return 'dromega';
        }
        if (str_contains($name, 'DROSYMCA') || in_array('drosymca', $scraperKeys, true) || (int) $sup->id === 1006) {
            return 'drosymca';
        }

        return null;
    }

    /**
     * Determina si el proveedor cuenta con un scraper financiero soportado.
     */
    public function hasFinancialScraper(Supplier|int $supplier): bool
    {
        return $this->detectScraperType($supplier) !== null;
    }

    /**
     * Despacha la sincronización financiera en segundo plano para no bloquear la ingesta operativa.
     */
    public function dispatchFinancialSync(Supplier|int $supplier, ?string $onlyInvoice = null): void
    {
        $sup = $this->resolveSupplier($supplier);
        if (!$sup) {
            return;
        }

        if ($this->hasFinancialScraper($sup)) {
            Log::info("[FinancialSync] Despachando Job de sincronización financiera para proveedor: {$sup->name} (ID: {$sup->id})");
            SyncSupplierFinancialScraperJob::dispatch($sup->id, $onlyInvoice);
        }
    }

    /**
     * Ejecuta la sincronización financiera invocando al servicio de scraper correspondiente.
     */
    public function syncSupplierFinancials(Supplier|int $supplier, ?string $onlyInvoice = null): array
    {
        $sup = $this->resolveSupplier($supplier);
        if (!$sup) {
            return [
                'success' => false,
                'message' => 'Proveedor no encontrado.',
            ];
        }

        $type = $this->detectScraperType($sup);
        if (!$type) {
            return [
                'success' => false,
                'message' => "El proveedor {$sup->name} no tiene un scraper financiero asociado.",
            ];
        }

        // Obtener credenciales exclusivamente desde supplier_scrapers (nunca desde supplier_connections)
        $scraperRecord = SupplierScraper::where('supplier_id', $sup->id)
            ->where('scraper_key', $type)
            ->first();

        $user = $scraperRecord?->username;
        $pass = $scraperRecord?->getDecryptedPassword();

        Log::info("[FinancialSync] Iniciando sincronización de finanzas ({$type}) para {$sup->name} (ID: {$sup->id})");

        try {
            switch ($type) {
                case 'dronena':
                    $scraper = app(DronenaScraperServiceInterface::class);
                    $res = $scraper->syncInvoices($user, $pass, $sup->id, $onlyInvoice);
                    break;

                case 'drocerca':
                    $scraper = app(DrocercaScraperServiceInterface::class);
                    $res = $scraper->syncInvoices($user, $pass, $sup->id, $onlyInvoice);
                    break;

                case 'mafarta':
                    $scraper = app(MafartaScraperServiceInterface::class);
                    $res = $scraper->syncInvoices($user, $pass, $sup->id, $onlyInvoice);
                    break;

                case 'cristmedicals':
                    $scraper = app(CristmedicalsScraperServiceInterface::class);
                    $res = $scraper->syncInvoices($user, $pass, $sup->id, $onlyInvoice);
                    break;

                case 'dromega':
                    $scraper = app(DromegaScraperServiceInterface::class);
                    $cookie = $scraperRecord?->auth_payload['cookie'] ?? null;
                    $res = $scraper->syncInvoices($cookie, $user, $pass, $sup->id);
                    break;

                case 'drosymca':
                    $scraper = app(DrosymcaScraperServiceInterface::class);
                    $res = $scraper->syncInvoices($user, $pass, $sup->id, $onlyInvoice);
                    break;

                default:
                    return [
                        'success' => false,
                        'message' => "Tipo de scraper {$type} no reconocido.",
                    ];
            }

            Log::info("[FinancialSync] Sincronización financiera ({$type}) completada para {$sup->name}", ['result' => $res]);

            return [
                'success' => true,
                'supplier_id' => $sup->id,
                'supplier_name' => $sup->name,
                'type' => $type,
                'details' => $res,
            ];
        } catch (\Throwable $e) {
            Log::error("[FinancialSync] Error ejecutando scraper {$type} para {$sup->name}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'supplier_id' => $sup->id,
                'supplier_name' => $sup->name,
                'type' => $type,
                'error' => $e->getMessage(),
            ];
        }
    }
}
