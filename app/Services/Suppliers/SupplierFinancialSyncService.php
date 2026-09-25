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
use App\Helpers\FtpCrypt;
use App\Jobs\SyncSupplierFinancialScraperJob;
use App\Models\Supplier;
use Illuminate\Support\Facades\Log;

class SupplierFinancialSyncService implements SupplierFinancialSyncServiceInterface
{
    /**
     * Resuelve el modelo Supplier a partir de ID o instancia.
     */
    protected function resolveSupplier(Supplier|int $supplier): ?Supplier
    {
        if ($supplier instanceof Supplier) {
            return $supplier->relationLoaded('connections') ? $supplier : $supplier->load('connections');
        }

        return Supplier::with('connections')->find($supplier);
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
        $connTypes = $sup->connections->pluck('type')->map(fn($t) => strtolower((string)$t))->toArray();
        $connHosts = $sup->connections->pluck('host')->map(fn($h) => strtolower((string)$h))->toArray();

        // 1. Dronena / Droguería Nena
        if (str_contains($name, 'DRONENA') || str_contains($name, 'NENA') || in_array('dronena_bot', $connTypes) || in_array('dronena', $connTypes)) {
            return 'dronena';
        }

        // 2. Drocerca
        if (str_contains($name, 'DROCERCA') || in_array('drocerca_bot', $connTypes) || in_array('drocerca', $connTypes)) {
            return 'drocerca';
        }

        // 3. Cobeca / Mafarta
        if (str_contains($name, 'MAFARTA') || str_contains($name, 'COBECA') || in_array('mafarta_bot', $connTypes) || in_array('mafarta', $connTypes)) {
            return 'mafarta';
        }

        // 4. Cristmedicals
        if (str_contains($name, 'CRISTMEDICALS') || str_contains($name, 'CRIST') || in_array('cristmedicals_bot', $connTypes) || in_array('cristmedicals', $connTypes) || in_array($sup->id, [3, 21, 1002])) {
            return 'cristmedicals';
        }

        // 5. Droguería Mega / Dromega
        if (str_contains($name, 'DROMEGA') || str_contains($name, 'MEGA') || in_array('dromega_bot', $connTypes) || in_array('dromega', $connTypes) || in_array($sup->id, [9, 15, 38, 1005])) {
            return 'dromega';
        }

        // 6. Drosymca
        if (str_contains($name, 'DROSYMCA') || in_array('drosymca_bot', $connTypes) || in_array('drosymca', $connTypes) || (int)$sup->id === 1006) {
            return 'drosymca';
        }

        // Verificar por host en conexiones
        foreach ($connHosts as $host) {
            if (str_contains($host, 'dronena')) return 'dronena';
            if (str_contains($host, 'drocerca')) return 'drocerca';
            if (str_contains($host, 'cobeca') || str_contains($host, 'mafarta')) return 'mafarta';
            if (str_contains($host, 'cristmedicals')) return 'cristmedicals';
            if (str_contains($host, 'dromega')) return 'dromega';
            if (str_contains($host, 'drosymca')) return 'drosymca';
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

        // Obtener conexión configurada para extraer credenciales
        $conn = $sup->connections->first(fn($c) => in_array($c->type, ["{$type}_bot", $type, 'bot', 'api', 'http']))
            ?? $sup->connections->first();

        $user = $conn?->username;
        $pass = null;
        if (!empty($conn?->password)) {
            try {
                $pass = FtpCrypt::decrypt($conn->password);
            } catch (\Throwable $e) {
                $pass = (string)$conn->password;
            }
        }

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
                    $cookie = $conn?->auth_token ?? null;
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
