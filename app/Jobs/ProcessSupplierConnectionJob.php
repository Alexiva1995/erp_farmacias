<?php

namespace App\Jobs;

use App\Models\Supplier;
use App\Models\SupplierConnectionStatus;
use App\Services\Suppliers\SupplierConnectionService;
use App\Services\Suppliers\SupplierQueryService;
use App\Exports\SupplierImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProcessSupplierConnectionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Supplier $supplier,
        public ?int $userId,
        public ?string $filePath = null,
        public array $columnMap = [],
        public ?float $exchangeRate = null,
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(SupplierConnectionService $connectionService, SupplierQueryService $queryService): void
    {
        // Log INMEDIATO al inicio del Job
        $logFile = storage_path('logs/supplier_debug_' . date('Y-m-d') . '.log');
        $tipo = $this->filePath ? 'EXCEL' : 'FTP/API';
        $logMessage = "[" . date('Y-m-d H:i:s') . "] 🔧 [JOB] INICIO handle() - Tipo: {$tipo}\n";
        $logMessage .= "[" . date('Y-m-d H:i:s') . "] 📦 Supplier ID: {$this->supplier->id}, Name: {$this->supplier->name}\n";
        $logMessage .= "[" . date('Y-m-d H:i:s') . "] 👤 User ID: " . ($this->userId ?? 'NULL') . "\n";
        $logMessage .= "[" . date('Y-m-d H:i:s') . "] 📁 File Path: " . ($this->filePath ?? 'NULL (FTP)') . "\n";
        $logMessage .= "[" . date('Y-m-d H:i:s') . "] 🗺️ Column Map: " . json_encode($this->columnMap) . "\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        error_log($logMessage);
        Log::error("🔧 [JOB] INICIO handle()", [
            'supplier_id' => $this->supplier->id,
            'supplier_name' => $this->supplier->name,
            'tipo' => $tipo,
            'file_path' => $this->filePath
        ]);
        
        $status = SupplierConnectionStatus::create([
            "supplier_id" => $this->supplier->id,
            "user_id" => $this->userId,
            "status" => "processing",
        ]);
        
        $logMessage = "[" . date('Y-m-d H:i:s') . "] ✅ [JOB] Status creado - ID: {$status->id}, Status: processing\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);

        $supplierConnection = \App\Models\SupplierConnection::where('supplier_id', $this->supplier->id)->first();
        if (is_null($supplierConnection) && !$this->filePath) {
            $status->update([
                "status" => "failed",
                "message" => "Este proveedor no posee una conexión registrada",
            ]);
            return;
        }

        $structure = $supplierConnection->structure ?? null;

        try {
            $results = [];

            if ($this->filePath) {
                if (!$supplierConnection || $structure !== $this->columnMap) {
                    $data = [
                        'supplier_id' => $this->supplier->id,
                        'type' => $supplierConnection->type ?? 'file',
                        'host' => $supplierConnection->host ?? "{$this->supplier->name} (Excel)",
                        'pasv' => $supplierConnection->pasv ?? 0,
                        'has_header' => $supplierConnection->has_header ?? 0,
                        'last_connection' => now()->format('Y-m-d'),
                        'structure' => $this->columnMap
                    ];
                    $queryService->storeConnection($data);
                    $supplierConnection = \App\Models\SupplierConnection::where('supplier_id', $this->supplier->id)->first();
                }

                if (!Storage::disk('local')->exists($this->filePath)) {
                    throw new \Exception("Archivo subido no encontrado: {$this->filePath}");
                }

                $absolutePath = Storage::disk('local')->path($this->filePath);

                $import = new SupplierImport(
                    supplierId: (int) $this->supplier->id,
                    startRow: (int) ($this->columnMap["start_row"] ?? 1),
                    codSupplierCol: $this->columnMap["cod_supplier"] ?? null,
                    nameCol: $this->columnMap["name"],
                    barcodeCol: $this->columnMap["barcode_match"] ?? null,
                    qtyCol: $this->columnMap["quantity"] ?? null,
                    costBsCol: $this->columnMap["unit_cost"] ?? null,
                    costUsdCol: $this->columnMap["unit_cost_usd"] ?? null,
                    activeIngredientCol: $this->columnMap["active_ingredient"] ?? null,
                    expirationCol: $this->columnMap["expiration"] ?? null,
                    currencyCol: $this->exchangeRate ?? ($this->columnMap["currency"] ?? null),
                );

                $logFile = storage_path('logs/supplier_debug_' . date('Y-m-d') . '.log');
                $logMessage = "[" . date('Y-m-d H:i:s') . "] 🚨 [JOB] ANTES Excel::import - Path: {$absolutePath}\n";
                file_put_contents($logFile, $logMessage, FILE_APPEND);
                
                Excel::import($import, $absolutePath);

                $products = $import->getRows();
                $productsArray = $products->isNotEmpty() ? $products->toArray() : [];
                $productsCount = count($productsArray);
                
                $logMessage = "[" . date('Y-m-d H:i:s') . "] 🚨 [JOB] DESPUÉS Excel::import - Productos obtenidos: {$productsCount}\n";
                file_put_contents($logFile, $logMessage, FILE_APPEND);
                Log::error("🚨 [JOB] Productos importados", ['count' => $productsCount]);
                
                $results = [
                    "products" => $productsArray,
                    "invoices" => []
                ];

                Storage::disk('local')->delete($this->filePath);
            } else {
                // Procesando conexión FTP/API
                $logFile = storage_path('logs/supplier_debug_' . date('Y-m-d') . '.log');
                $logMessage = "[" . date('Y-m-d H:i:s') . "] 🌐 [JOB] Procesando conexión FTP/API\n";
                file_put_contents($logFile, $logMessage, FILE_APPEND);
                error_log($logMessage);
                
                $supplierConnection = \App\Models\SupplierConnection::where('supplier_id', $this->supplier->id)->first();
                if ($supplierConnection) {
                    $logMessage = "[" . date('Y-m-d H:i:s') . "] 🔗 [JOB] Conexión encontrada - Host: {$supplierConnection->host}\n";
                    file_put_contents($logFile, $logMessage, FILE_APPEND);
                } else {
                    $logMessage = "[" . date('Y-m-d H:i:s') . "] ⚠️ [JOB] No se encontró conexión configurada\n";
                    file_put_contents($logFile, $logMessage, FILE_APPEND);
                }
                
                $logMessage = "[" . date('Y-m-d H:i:s') . "] 📡 [JOB] Ejecutando fetchData()...\n";
                file_put_contents($logFile, $logMessage, FILE_APPEND);
                
                $results = $connectionService->fetchData($supplierConnection);

                $invoiceCount = isset($results['invoices']) ? count($results['invoices']) : 0;
                $productCount = isset($results['products']) ? count($results['products']) : 0;

                $logMessage = "[" . date('Y-m-d H:i:s') . "] ✅ [JOB] fetchData() completado\n";
                $logMessage .= "[" . date('Y-m-d H:i:s') . "] 📊 Resultados: {$invoiceCount} facturas, {$productCount} productos\n";
                file_put_contents($logFile, $logMessage, FILE_APPEND);
                error_log($logMessage);
                

                if ($invoiceCount === 0) {
                    $logMessage = "[" . date('Y-m-d H:i:s') . "] ⚠️ [JOB] ADVERTENCIA: API retornó 0 facturas\n";
                    file_put_contents($logFile, $logMessage, FILE_APPEND);
                    Log::warning("⚠️ API retornó 0 facturas para proveedor {$this->supplier->id}. Posible problema con la respuesta de la API o no hay facturas pendientes.");
                }
            }

            if (isset($results['invoices']) && is_array($results['invoices'])) {
                foreach ($results['invoices'] as &$invoice) {
                    $invoice['status'] = 'pending';
                }
                unset($invoice);
            }

            // Log ANTES de llamar a storeSupplierConnectionData
            Log::error("🚨 [JOB] ANTES de llamar storeSupplierConnectionData", [
                'supplier_id' => $this->supplier->id,
                'products_count' => count($results["products"] ?? []),
                'invoices_count' => count($results["invoices"] ?? []),
            ]);
            
            $logFile = storage_path('logs/supplier_debug_' . date('Y-m-d') . '.log');
            $logMessage = "[" . date('Y-m-d H:i:s') . "] 🚨 [JOB] ANTES de llamar storeSupplierConnectionData - Supplier ID: {$this->supplier->id}, Products: " . count($results["products"] ?? []) . "\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
            
            $queryService->storeSupplierConnectionData($this->supplier, $results);
            
            // Log DESPUÉS de llamar a storeSupplierConnectionData
            Log::error("🚨 [JOB] DESPUÉS de llamar storeSupplierConnectionData", [
                'supplier_id' => $this->supplier->id,
            ]);
            
            $logMessage = "[" . date('Y-m-d H:i:s') . "] 🚨 [JOB] DESPUÉS de llamar storeSupplierConnectionData - Supplier ID: {$this->supplier->id}\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);

            $queryService->addDiscountsToProducts($this->supplier);

            
            \Illuminate\Support\Facades\DB::table('supplier_connections')
                ->where('supplier_id', $this->supplier->id)
                ->update(['last_connection' => now()->toDateString()]);
            

            $status->update([
                "status" => "completed",
                "message" => "Conexión procesada correctamente",
                "count_product" => count($results["products"]),
                "count_invoice" => count($results["invoices"]),
            ]);

        } catch (\Throwable $e) {
            // ... (Manejo de errores existente) ...
            Log::error('Supplier import failed', [
                'supplier_id' => $this->supplier->id,
                'file' => $this->filePath ?? 'none',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $status->update([
                "status" => "failed",
                "message" => $e->getMessage(),
            ]);

            //throw $e;
        }
    }
}
