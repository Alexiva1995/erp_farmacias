<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Jobs\UpdateAllSuppliersJob;
use App\Models\ProductSupplier;
use App\Services\Suppliers\SupplierQueryService;
use App\Services\Suppliers\SupplierActionService;
use App\Http\Requests\GetDataFromSupplierFileRequest;
use App\Http\Requests\StoreSupplierLaboratoryRequest;
use App\Http\Requests\UpdatePaymentRuleSupplierRequest;
use App\Http\Requests\StoreDiscountsRequest;
use App\Http\Requests\StoreProductIntoAutoOrderRequest;
use App\Jobs\ProcessSupplierConnectionJob;
use App\Models\Supplier;
use App\Http\Resources\SupplierResource;
use App\Http\Resources\SupplierConnectionResource;
use App\Http\Resources\SupplierProductResource;
use App\Http\Resources\LaboratoryResource;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Arr;
use App\Models\Product;

class SupplierController extends Controller
{
    /**
     * Constructor to inject the services
     *
     * @param SupplierQueryService $supplierQueryService
     * @param SupplierActionService $supplierActionService
     */
    public function __construct(
        private SupplierQueryService $supplierQueryService,
        private SupplierActionService $supplierActionService,
    ) {
    }

    /**
     * Display a listing of the suppliers.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = $this->supplierQueryService->getFilteredQuery($request);
        $perPage = $request->input("itemsPerPage", 10);

        if ($perPage < 1) {
            $suppliers = $query->get();
            return response()->json([
                "data" => SupplierResource::collection($suppliers)->resolve(),
                "total" => $suppliers->count(),
            ]);
        }

        $paginatedResult = $query->paginate($perPage);
        return response()->json([
            "data" => SupplierResource::collection($paginatedResult->getCollection())->resolve(),
            "total" => $paginatedResult->total(),
        ]);
    }

    /**
     * Summary of store
     * @param \App\Http\Requests\StoreSupplierRequest $request
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function store(StoreSupplierRequest $request)
    {
        $supplier = $this->supplierActionService->createSupplier($request->validated());

        return (new SupplierResource($supplier))
            ->additional(['message' => 'Proveedor creado con éxito.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Summary of update
     * @param \App\Http\Requests\UpdateSupplierRequest $request
     * @param \App\Models\Supplier $supplier
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function update(UpdateSupplierRequest $request, Supplier $supplier)
    {
        $updatedSupplier = $this->supplierActionService->updateSupplier($supplier, $request->validated());
        
        return (new SupplierResource($updatedSupplier))
            ->additional(['message' => 'Proveedor actualizado con éxito.']);
    }

    /**
     * Remove the specified supplier from storage.
     *
     * @param Supplier $supplier
     * @return \Illuminate\Http\Response
     */
    public function destroy(Supplier $supplier)
    {
        $this->supplierActionService->deleteSupplier($supplier);
        return response()->noContent();
    }

    /**
     * Summary of connectionServiceSupplier
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function connectionServiceSupplier(Supplier $supplier, Request $request)
    {
        // Log INMEDIATAMENTE al recibir la solicitud FTP
        $logFile = storage_path('logs/supplier_debug_' . date('Y-m-d') . '.log');
        $logMessage = "[" . date('Y-m-d H:i:s') . "] ========== 🚀 INICIO CONEXIÓN FTP ==========\n";
        $logMessage .= "[" . date('Y-m-d H:i:s') . "] 📡 [CONTROLLER] connectionServiceSupplier() llamado - Supplier ID: {$supplier->id}, Name: {$supplier->name}\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        error_log($logMessage);
        \Log::error("🚀 [INICIO] Conexión FTP iniciada", ['supplier_id' => $supplier->id, 'supplier_name' => $supplier->name]);
        
        $userId = auth()->id() ?? 1;
        $logMessage = "[" . date('Y-m-d H:i:s') . "] 👤 User ID: {$userId}\n";
        $logMessage .= "[" . date('Y-m-d H:i:s') . "] 🔄 [CONTROLLER] Despachando Job FTP (SÍNCRONO para ver logs inmediatos)\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        
        // Ejecutar SÍNCRONO para ver todos los logs de inmediato
        ProcessSupplierConnectionJob::dispatchSync($supplier, $userId);
        
        // Para producción, cambiar de vuelta a asíncrono:
        // ProcessSupplierConnectionJob::dispatch($supplier, $userId);

        $logMessage = "[" . date('Y-m-d H:i:s') . "] ✅ [CONTROLLER] Job FTP completado\n";
        $logMessage .= "[" . date('Y-m-d H:i:s') . "] ========== 🏁 FIN CONEXIÓN FTP ==========\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);

        return response()->json(["status" => "completed"]);
    }
    public function dispatchUpdateAllJob()
    {
        $userId = auth()->id() ?? 1;

        UpdateAllSuppliersJob::dispatch($userId);

        return response()->json([
            'message' => 'Se ha iniciado la actualización de todos los proveedores en segundo plano.'
        ]);
    }

    /**
     * Get the connection statuses for the authenticated user.
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function getConnectionStatus()
    {
        $userId = auth()->id() ?? 1;
        $statuses = $this->supplierQueryService->getRecentConnectionStatusesForUser($userId);

        return response()->json(["statuses" => $statuses]);
    }

    /**
     * Update or create  payment rules for a supplier.
     *
     * @param UpdatePaymentRuleSupplierRequest $request
     * @param Supplier $supplier
     * @return \Illuminate\Http\JsonResponse
     */
    public function storePaymentRules(UpdatePaymentRuleSupplierRequest $request, Supplier $supplier)
    {
        $validated = $request->validated();

        $createdRules = [];

      
        $sentIds = Arr::pluck($validated['rules'], 'id');
        
        $sentIds = array_filter($sentIds, function($id) {
            return is_numeric($id) && $id > 0;
        });
        
        if (!empty($sentIds)) {
            $supplier->paymentRules()
            ->whereNotIn('id', $sentIds)
            ->delete();
        } else {
            $supplier->paymentRules()->delete();
        }
           
        foreach ($validated['rules'] as $rule) {
            $ruleData = [
                'days' => $rule['days'],
                'discount_percentage' => $rule['discount_percentage'],
            ];

            if (isset($rule['id']) && $rule['id'] > 0) {
                $ruleData['id'] = $rule['id'];
            }

            $createdRules[] = $this->supplierActionService->createPaymentRule($supplier, $ruleData);
        }

        return response()->json([
            'message' => 'Reglas registradas correctamente.',
            'rules' => $createdRules,
        ]);
    }

    public function getPaymentRules(Supplier $supplier)
    {
        $rules = $this->supplierQueryService->getPaymentRules($supplier);

        return response()->json(['payment_rules' => $rules]);
    }

    /**
     * Store a new laboratory link for a supplier.
     *
     * @param StoreSupplierLaboratoryRequest $request
     * @param Supplier $supplier
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeLaboratory(StoreSupplierLaboratoryRequest $request, Supplier $supplier)
    {
       /* dd($request->validated());
        $link = $this->supplierActionService->attachLaboratory($supplier, $request->validated());

        return response()->json([
            "message" => "Laboratorio vinculado con éxito.",
            "laboratory_link" => $link->load("laboratory"),
        ]);*/

        $validated = $request->validated();
        $createdRules = [];
        $sentIds = Arr::pluck($validated['rulesLaboratory'], 'id');
        $sentIds = array_filter($sentIds, function($id) {
            return is_numeric($id) && $id > 0;
        });

        if (!empty($sentIds)) {
            $supplier->laboratoryLinks()
            ->whereNotIn('id', $sentIds)
            ->delete();
        } else {
            $supplier->laboratoryLinks()->delete();
        }

       
        foreach ($validated['rulesLaboratory'] as $rulesLaboratory) {
            $ruleData = [
                'phone' => $rulesLaboratory['phone'],
                'laboratory_id' => $rulesLaboratory['laboratory']['id'],
            ];

            if (isset($rulesLaboratory['id']) && $rulesLaboratory['id'] > 0) {
                $ruleData['id'] = $rulesLaboratory['id'];
            }

            $createdRules[] = $this->supplierActionService->attachLaboratory($supplier, $ruleData);
        }

        return response()->json([
            'message' => 'Laboratorios vinculado con éxito.',
            'rules' => $createdRules,
        ]);
    }

    /**
     * Get the laboratory links for a supplier.
     *
     * @param Supplier $supplier
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLaboratoryLinks(Supplier $supplier)
    {
        $links = $this->supplierQueryService->getLaboratories($supplier);

        return response()->json(["laboratory_links" => $links]);
    }

    /**
     * Get pending invoices for a supplier, grouped by payment date.
     *
     * @param Supplier $supplier
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPendingInvoices(Supplier $supplier)
    {
        $grouped = $this->supplierQueryService->getUnpaidInvoicesByDate($supplier);
        return response()->json(["pending_invoices" => $grouped]);
    }

    public function getDiscounts(Supplier $supplier)
    {
        $discounts = $this->supplierQueryService->getDiscounts($supplier);

        return response()->json(["supplier_discount" => $discounts]);
    }

    public function storeDiscounts(StoreDiscountsRequest $request, Supplier $supplier)
    {
        $validated = $request->validated();

        $createdDiscounts = [];

        foreach ($validated["discounts"] as $rule) {
            $discountData = [
                "name" => $rule["name"],
                "discount_percentage" => $rule["discount_percentage"],
            ];
            $isCreated = !isset($rule["id"]);

            if ($isCreated){
                $createdDiscounts[] = $this->supplierActionService->createDiscount($supplier, $discountData);
            }
        }

        return response()->json([
            "message" => "Descuentos registrados correctamente.",
            "discounts" => $createdDiscounts,
        ]);
    }

    public function getSupplierConnections(Request $request)
    {
        $results = $this->supplierQueryService->getSupplierConnections($request);

        return response()->json([
            "data" => SupplierConnectionResource::collection($results->getCollection())->resolve(),
            "total" => $results->total(),
        ]);
    }

    public function getSupplierProducts(Supplier $supplier, Request $request)
    {
        $results = $this->supplierQueryService->getSupplierProducts($supplier, $request);

        return response()->json([
            "data" => SupplierProductResource::collection($results->getCollection())->resolve(),
            "total" => $results->total(),
        ]);
    }

    public function getProducts(Request $request)
    {
        $results = $this->supplierQueryService->getProducts($request);

        return response()->json([
            "data" => SupplierProductResource::collection($results->getCollection())->resolve(),
            "total" => $results->total(),
        ]);
    }

    public function getLaboratories()
    {
        $results = $this->supplierQueryService->getAvailableLaboratories();

        return LaboratoryResource::collection($results);
    }

    public function addProductToOrder(StoreProductIntoAutoOrderRequest $request)
    {
        $productId = $request->productId;
        $discount = $request->boolean("discount");
        $product = ProductSupplier::find($productId);

        if ($discount && empty($product->unit_cost_usd_with_discount)) {
            return ApiResponse::error('Este producto no posee descuentos');
        }

        $results = $this->supplierQueryService->addProductToOrder($request);

        /*return $results
            ? ApiResponse::success()
            : ApiResponse::error();*/
      if ($results['success']) {
        return ApiResponse::success(
            'Producto añadido al pedido correctamente', 
            ['warning' => $results['warning']]
        );
    }

    return ApiResponse::error('No se pudo procesar la solicitud');
    }

    public function importData(Supplier $supplier, GetDataFromSupplierFileRequest $request)
    {
        \Log::error("🔴 [CRITICAL DEBUG] importData() llamado", [
            'supplier_id' => $supplier->id,
            'request_all' => $request->except(['file']),
            'has_file'    => $request->hasFile('file')
        ]);
        error_log("🔴 [CRITICAL DEBUG] importData() llamado para Supplier ID: {$supplier->id}");

        // Log INMEDIATAMENTE al recibir la solicitud
        $logFile = storage_path('logs/supplier_debug_' . date('Y-m-d') . '.log');
        $logMessage = "[" . date('Y-m-d H:i:s') . "] ========== 🚀 INICIO IMPORTACIÓN ==========\n";
        $logMessage .= "[" . date('Y-m-d H:i:s') . "] 📋 [CONTROLLER] importData() llamado - Supplier ID: {$supplier->id}, Name: {$supplier->name}\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        error_log($logMessage);
        \Log::error("🚀 [INICIO] Importación iniciada", ['supplier_id' => $supplier->id, 'supplier_name' => $supplier->name]);
        
        $userId = auth()->id() ?? 1;
        $logMessage = "[" . date('Y-m-d H:i:s') . "] 👤 User ID: {$userId}\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        
        $validated = $request->validated();
        $logMessage = "[" . date('Y-m-d H:i:s') . "] ✅ Validación pasada - Columnas mapeadas: " . json_encode($validated) . "\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);

        unset($validated["file"]);

        try {
            $path = $request->file("file")->store("temp", ["disk" => "local"]);
            $logMessage = "[" . date('Y-m-d H:i:s') . "] 📁 Archivo guardado en: {$path}\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
        } catch (\Exception $e) {
            $logMessage = "[" . date('Y-m-d H:i:s') . "] ❌ Error guardando archivo: " . $e->getMessage() . "\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
            error_log($logMessage);
            return response()->json(['error' => 'Failed to store file'], 500);
        }

        // Log ANTES de dispatch
        $logMessage = "[" . date('Y-m-d H:i:s') . "] 🔄 [CONTROLLER] Despachando Job (SÍNCRONO) - Path: {$path}\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        \Log::error("🔄 [CONTROLLER] Despachando Job", ['supplier_id' => $supplier->id, 'path' => $path]);

        // Ejecutar de forma SÍNCRONA para ver errores inmediatos
        ProcessSupplierConnectionJob::dispatchSync($supplier, $userId, $path, $validated);

        $logMessage = "[" . date('Y-m-d H:i:s') . "] ✅ [CONTROLLER] Job completado\n";
        $logMessage .= "[" . date('Y-m-d H:i:s') . "] ========== 🏁 FIN IMPORTACIÓN ==========\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);

        return response()->json(["status" => "completed"]);
    }

    public function deleteProducts(Supplier $supplier)
    {
        return $this->supplierQueryService->deleteProducts($supplier);
    }

    public function getSupplierFirstConnection(Supplier $supplier)
    {
        $result = $this->supplierQueryService->getSupplierFirstConnection($supplier);

        return response()->json([
            'data' => $result
        ]);
    }
    public function applyGlobalDiscount(Request $request, Supplier $supplier)
    {
        $request->validate([
            'percentage' => 'required|numeric|min:0.01|max:100',
        ]);

        $affectedRows = $this->supplierActionService->applyGlobalDiscount(
            $supplier,
            $request->percentage
        );

        return response()->json([
            'message' => "Descuento aplicado correctamente a {$affectedRows} productos.",
            'affected_rows' => $affectedRows
        ]);
    }
    public function deleteOldProducts(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date|before_or_equal:today',
        ]);

        try {
            $deletedCount = $this->supplierActionService->deleteProductsOlderThan($validated['date']);

            return response()->json([
                "status" => "ok",
                "message" => "Se eliminaron {$deletedCount} productos correctamente.",
                "count" => $deletedCount
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error("Error al eliminar productos antiguos: " . $e->getMessage(), 500);
        }
    }

    public function toggleOrder(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $product->update([
            'is_ordered' => false,
            'ignore_until' => now()->addHours(48),
        ]);
        return ApiResponse::success("Producto eliminado de la lista por 48 horas");
    }

    public function generatePublicToken(Supplier $supplier)
    {
        $supplier->update([
            'public_token' => \Illuminate\Support\Str::random(40),
        ]);

        return ApiResponse::success($supplier->public_token, "Token generado correctamente");
    }

    public function stats()
    {
        $stats = $this->supplierQueryService->getSupplierSummaryStats();
        return response()->json($stats);
    }

    /**
     * Obtiene la configuración de conexión FTP/API de un proveedor (sin exponer la contraseña).
     */
    public function getConnectionConfig(Supplier $supplier)
    {
        $connection = $supplier->connections()->first();

        if (!$connection) {
            return response()->json(null);
        }

        return response()->json([
            'id'                => $connection->id,
            'type'              => $connection->type,
            'host'              => $connection->host,
            'port'              => $connection->port,
            'username'          => $connection->username,
            'has_password'      => !empty($connection->password),
            'path'              => $connection->path,
            'pasv'              => (bool) $connection->pasv,
            'has_header'        => (bool) $connection->has_header,
            'invoice_path'      => $connection->invoice_path,
            'last_connection'   => $connection->last_connection,
        ]);
    }

    /**
     * Guarda o actualiza la configuración de conexión FTP/API.
     * La contraseña se cifra con AES-256 antes de persistirse.
     */
    public function saveConnectionConfig(\Illuminate\Http\Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'type'          => 'required|in:ftp,sftp,http,api',
            'host'          => 'required|string|max:500',
            'port'          => 'nullable|numeric|min:1|max:65535',
            'username'      => 'nullable|string|max:255',
            'password'      => 'nullable|string',
            'path'          => 'nullable|string|max:500',
            'pasv'          => 'boolean',
            'has_header'    => 'boolean',
            'invoice_path'  => 'nullable|string|max:500',
        ]);

        // Construir el payload que se persiste
        $data = [
            'supplier_id' => $supplier->id,
            'type'        => $validated['type'],
            'host'        => $validated['host'],
            'port'        => $validated['port'] ?? null,
            'username'    => $validated['username'] ?? null,
            'path'        => $validated['path'] ?? null,
            'pasv'        => $validated['pasv'] ?? false,
            'has_header'  => $validated['has_header'] ?? false,
            'invoice_path' => $validated['invoice_path'] ?? null,
        ];

        // Solo actualizar la contraseña si el usuario envió una nueva
        if (!empty($validated['password'])) {
            $data['password'] = \App\Helpers\FtpCrypt::encrypt($validated['password']);
        }

        $connection = $supplier->connections()->updateOrCreate(
            ['supplier_id' => $supplier->id],
            $data
        );

        return response()->json([
            'message'    => 'Configuración guardada correctamente.',
            'connection' => [
                'id'           => $connection->id,
                'type'         => $connection->type,
                'host'         => $connection->host,
                'has_password' => !empty($connection->password),
            ],
        ]);
    }
}
