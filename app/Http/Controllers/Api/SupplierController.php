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
use App\Http\Requests\Supplier\ApplyGlobalDiscountRequest;
use App\Http\Requests\Supplier\DeleteOldProductsRequest;
use App\Http\Requests\Supplier\SaveConnectionConfigRequest;
use App\Http\Requests\Supplier\ToggleProductSupplierStatusRequest;
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
        $userId = auth()->id() ?? 1;

        $status = \App\Models\SupplierConnectionStatus::create([
            "supplier_id" => $supplier->id,
            "user_id" => $userId,
            "status" => "processing",
        ]);

        // Despachar de forma asíncrona pasando el status_id creado
        ProcessSupplierConnectionJob::dispatch($supplier, $userId, null, [], null, $status->id);

        return response()->json([
            "status" => "processing",
            "status_id" => $status->id,
        ]);
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

    public function getSuppliers()
    {
        $results = $this->supplierQueryService->getAvailableSuppliers();

        return SupplierResource::collection($results);
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

    return ApiResponse::error($results['message'] ?? 'No se pudo procesar la solicitud');
    }

    public function importData(Supplier $supplier, GetDataFromSupplierFileRequest $request)
    {
        $userId = auth()->id() ?? 1;
        $validated = $request->validated();

        $formatType = $request->input('format_type', 'primary');
        $saveAsSecondary = filter_var($request->input('save_as_secondary', false), FILTER_VALIDATE_BOOLEAN) || $formatType === 'secondary';
        $saveAsTertiary = filter_var($request->input('save_as_tertiary', false), FILTER_VALIDATE_BOOLEAN) || $formatType === 'tertiary';

        unset($validated["file"], $validated["format_type"], $validated["save_as_secondary"], $validated["save_as_tertiary"]);

        try {
            $path = $request->file("file")->store("temp", ["disk" => "local"]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to store file'], 500);
        }

        // Actualizar la estructura correspondiente en la conexión del proveedor
        $connection = $supplier->connections()->first();
        if ($connection) {
            if ($saveAsTertiary) {
                $connection->update(['tertiary_structure' => $validated]);
            } elseif ($saveAsSecondary) {
                $connection->update(['secondary_structure' => $validated]);
            } else {
                $connection->update(['structure' => $validated]);
            }
        }

        ProcessSupplierConnectionJob::dispatchSync($supplier, $userId, $path, $validated);

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
    public function applyGlobalDiscount(ApplyGlobalDiscountRequest $request, Supplier $supplier)
    {
        $affectedRows = $this->supplierActionService->applyGlobalDiscount(
            $supplier,
            $request->percentage
        );

        return response()->json([
            'message' => "Descuento aplicado correctamente a {$affectedRows} productos.",
            'affected_rows' => $affectedRows
        ]);
    }
    public function deleteOldProducts(DeleteOldProductsRequest $request)
    {
        $validated = $request->validated();

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
            'ignore_until' => now()->addDays(7),
        ]);
        return ApiResponse::success("Producto ignorado por 7 días");
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

        // Buscar si existe una conexión FTP secundaria para pedidos (ej. Mafarta API + FTP)
        $ftpOrdersConn = $supplier->connections()
            ->whereIn('type', ['ftp', 'sftp'])
            ->where('id', '!=', $connection->id)
            ->first();

        return response()->json([
            'id'                  => $connection->id,
            'type'                => $connection->type,
            'host'                => $connection->host,
            'port'                => $connection->port,
            'username'            => $connection->username,
            'has_password'        => !empty($connection->password),
            'path'                => $connection->path,
            'pasv'                => (bool) $connection->pasv,
            'has_header'          => (bool) $connection->has_header,
            'invoice_path'        => $connection->invoice_path,
            'last_connection'     => $connection->last_connection,
            'ftp_orders_enabled'  => $ftpOrdersConn ? true : false,
            'ftp_orders_host'     => $ftpOrdersConn?->host,
            'ftp_orders_port'     => $ftpOrdersConn?->port,
            'ftp_orders_username' => $ftpOrdersConn?->username,
            'ftp_orders_has_pass' => !empty($ftpOrdersConn?->password),
            'ftp_orders_path'     => $ftpOrdersConn?->path,
        ]);
    }

    /**
     * Guarda o actualiza la configuración de conexión FTP/API.
     * La contraseña se cifra con AES-256 antes de persistirse.
     */
    public function saveConnectionConfig(SaveConnectionConfigRequest $request, Supplier $supplier)
    {
        try {
            $validated = $request->validated();

            // Construir el payload que se persiste
            $data = [
                'supplier_id'  => $supplier->id,
                'type'         => $validated['type'],
                'host'         => $validated['host'] ?? null,
                'port'         => !empty($validated['port']) ? (int) $validated['port'] : null,
                'username'     => $validated['username'] ?? null,
                'path'         => $validated['path'] ?? null,
                'pasv'         => (bool) ($validated['pasv'] ?? false),
                'has_header'   => (bool) ($validated['has_header'] ?? false),
                'invoice_path' => $validated['invoice_path'] ?? null,
            ];

            // Solo actualizar la contraseña si el usuario envió una nueva
            if (!empty($validated['password'])) {
                $data['password'] = \App\Helpers\FtpCrypt::encrypt($validated['password']);
            }

            $existingConn = $supplier->connections()->first();

            // Preservar o asignar estructura por defecto para evitar errores de BD
            if ($existingConn && !empty($existingConn->structure)) {
                $data['structure'] = $existingConn->structure;
                $data['invoice_structure'] = $existingConn->invoice_structure;
                $data['parse_using'] = $existingConn->parse_using;
            } elseif (stripos($supplier->name, 'CRIST') !== false || $supplier->id === 1002 || $supplier->id === 3) {
                $data['structure'] = [
                    [ "target" => "name", "file_field" => "des_art", "type" => "string" ],
                    [ "target" => "barcode_match", "file_field" => "codigo_barra", "type" => "string" ],
                    [ "target" => "unit_cost_usd", "file_field" => "precio_con_descuento", "type" => "decimal" ],
                    [ "target" => "cod_supplier", "file_field" => "co_art", "type" => "string" ]
                ];
                $data['invoice_structure'] = [
                    "mode" => "grouped",
                    "header" => [
                        [ "field" => "invoice_number", "original_field" => "fact_num", "type" => "string" ],
                        [ "field" => "date", "original_field" => "fec_emis", "type" => "date", "format" => "Y-m-d" ],
                        [ "field" => "total_amount", "original_field" => "sub_total", "type" => "decimal" ],
                        [ "field" => "tax_amount", "original_field" => "iva16", "type" => "decimal" ],
                        [ "field" => "exempt_amount", "original_field" => "exento", "type" => "decimal" ],
                        [ "field" => "exchange_rate", "original_field" => "tasa", "type" => "decimal" ]
                    ],
                    "lines" => [
                        [ "field" => "barcode", "original_field" => "sku", "type" => "string" ],
                        [ "field" => "name", "original_field" => "descrip", "type" => "string" ],
                        [ "field" => "quantity", "original_field" => "cant", "type" => "integer" ],
                        [ "field" => "unit_cost", "original_field" => "neto_und", "type" => "decimal" ],
                        [ "field" => "total_cost", "original_field" => "total", "type" => "decimal" ]
                    ]
                ];
            } elseif (stripos($supplier->name, 'DRONENA') !== false || $supplier->id === 1014) {
                $data['structure'] = [
                    [ "type" => "string", "target" => "cod_supplier", "file_field" => "A" ],
                    [ "type" => "string", "target" => "name", "file_field" => "B" ],
                    [ "type" => "decimal", "target" => "unit_cost", "file_field" => "C" ],
                    [ "type" => "decimal", "target" => "quantity", "file_field" => "D" ],
                    [ "type" => "decimal", "target" => "discount_percentage", "file_field" => "G" ],
                    [ "type" => "string", "target" => "barcode_match", "file_field" => "J" ],
                    [ "type" => "date", "target" => "expiration", "file_field" => "N" ]
                ];
                $data['invoice_structure'] = [
                    "mode" => "grouped",
                    "separator" => "\t",
                    "filter" => [ "starts_with" => "", "ends_with" => ".txt" ],
                    "header" => [
                        [ "type" => "string", "field" => "tipo" ],
                        [ "type" => "string", "field" => "invoice_number" ],
                        [ "type" => "decimal", "field" => "total_amount", "decimals" => 2 ],
                        [ "type" => "datetime", "field" => "created_invoice_date", "format" => "d/m/Y g:i:s A" ],
                        [ "type" => "decimal", "field" => "exchange_rate", "decimals" => 2 ],
                        [ "type" => "decimal", "field" => "total_usd" ]
                    ],
                    "lines" => [
                        [ "type" => "string", "field" => "tipo" ],
                        [ "type" => "string", "field" => "invoice_number" ],
                        [ "type" => "string", "field" => "cod_supplier" ],
                        [ "type" => "string", "field" => "name" ],
                        [ "type" => "integer", "field" => "quantity" ],
                        [ "type" => "decimal", "field" => "unit_cost", "decimals" => 2 ],
                        [ "type" => "decimal", "field" => "total_cost", "decimals" => 2 ],
                        [ "type" => "string", "field" => "barcode" ],
                        [ "type" => "date", "field" => "expiration_date", "format" => "d/m/Y" ]
                    ]
                ];
            } elseif (stripos($supplier->name, 'CONTIN') !== false || $supplier->id === 1032) {
                $data['structure'] = [
                    [ "target" => "cod_supplier", "file_field" => "A", "type" => "string" ],
                    [ "target" => "barcode_match", "file_field" => "B", "type" => "string" ],
                    [ "target" => "name", "file_field" => "C", "type" => "string" ],
                    [ "target" => "expiration", "file_field" => "D", "type" => "date", "format" => "d/m/Y" ],
                    [ "target" => "unit_cost_usd", "file_field" => "G", "type" => "decimal" ],
                    [ "target" => "quantity", "file_field" => "H", "type" => "integer" ],
                    [ "target" => "laboratory", "file_field" => "I", "type" => "string" ]
                ];
            } elseif (stripos($supplier->name, 'DROMEGA') !== false || $supplier->id === 1005 || $supplier->id === 9) {
                $data['structure'] = [
                    "0" => [ "file_field" => "codigo_producto", "type" => "string", "target" => "cod_supplier" ],
                    "1" => [ "file_field" => "codigo_barras", "type" => "string", "target" => "barcode_match" ],
                    "2" => [ "file_field" => "descripcion_producto", "type" => "string", "target" => "name" ],
                    "3" => [ "file_field" => "fecha_lote", "type" => "date", "target" => "expiration" ],
                    "4" => [ "file_field" => "precio_unitario", "type" => "decimal" ],
                    "5" => [ "file_field" => "porcentaje_oferta_vigente", "type" => "decimal" ],
                    "6" => [ "file_field" => "precio_unitario_final", "type" => "decimal", "target" => "unit_cost" ],
                    "7" => [ "file_field" => "stock_disponible", "type" => "integer", "target" => "quantity" ]
                ];
                $data['invoice_structure'] = [
                    "separator" => ";",
                    "decimal_separator" => ".",
                    "decimals" => 2,
                    "header" => [
                        "0" => [ "field" => "tipo", "type" => "string" ],
                        "1" => [ "field" => "invoice_number", "type" => "integer" ],
                        "2" => [ "field" => "control_number", "type" => "string" ],
                        "3" => [ "field" => "created_invoice_date", "type" => "date", "format" => "d/m/Y" ],
                        "8" => [ "field" => "total_amount", "type" => "decimal" ],
                        "13" => [ "field" => "tax_amount", "type" => "decimal" ]
                    ],
                    "lines" => [
                        "0" => [ "field" => "tipo", "type" => "string" ],
                        "1" => [ "field" => "fact_num", "type" => "integer" ],
                        "2" => [ "field" => "numcon", "type" => "string" ],
                        "3" => [ "field" => "codigo_producto", "type" => "string" ],
                        "4" => [ "field" => "barcode", "type" => "string" ],
                        "5" => [ "field" => "descripcion_producto", "type" => "string" ],
                        "6" => [ "field" => "quantity", "type" => "integer" ],
                        "8" => [ "field" => "total_amount", "type" => "decimal" ],
                        "10" => [ "field" => "unit_cost", "type" => "decimal" ],
                        "11" => [ "field" => "lot_number", "type" => "string" ],
                        "12" => [ "field" => "expiration_date", "type" => "date", "format" => "d/m/Y" ],
                        "13" => [ "field" => "porcentaje_iva", "type" => "decimal" ]
                    ]
                ];
            } else {
                $data['structure'] = [];
            }

            $connection = $supplier->connections()->updateOrCreate(
                ['supplier_id' => $supplier->id],
                $data
            );

            // Gestionar conexión FTP secundaria para órdenes si fue enviada (ej. Mafarta API + Pedidos FTP)
            if ($request->has('ftp_orders_enabled')) {
                if ($request->boolean('ftp_orders_enabled') && !empty($validated['ftp_orders_host'])) {
                    $ftpData = [
                        'supplier_id' => $supplier->id,
                        'type'        => 'ftp',
                        'host'        => $validated['ftp_orders_host'],
                        'port'        => !empty($validated['ftp_orders_port']) ? (int) $validated['ftp_orders_port'] : 21,
                        'username'    => $validated['ftp_orders_username'] ?? null,
                        'path'        => $validated['ftp_orders_path'] ?? null,
                        'pasv'        => true,
                        'has_header'  => false,
                    ];
                    if (!empty($validated['ftp_orders_password'])) {
                        $ftpData['password'] = \App\Helpers\FtpCrypt::encrypt($validated['ftp_orders_password']);
                    }
                    $supplier->connections()->updateOrCreate(
                        ['supplier_id' => $supplier->id, 'type' => 'ftp'],
                        $ftpData
                    );
                } elseif (!$request->boolean('ftp_orders_enabled') && $validated['type'] !== 'ftp' && $validated['type'] !== 'sftp') {
                    // Si se desmarca y la principal no es FTP, eliminar la conexión FTP secundaria
                    $supplier->connections()->where('type', 'ftp')->delete();
                }
            }

            return response()->json([
                'message'    => 'Configuración guardada correctamente.',
                'connection' => [
                    'id'           => $connection->id,
                    'type'         => $connection->type,
                    'host'         => $connection->host,
                    'has_password' => !empty($connection->password),
                ],
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error en saveConnectionConfig: ' . $e->getMessage(), [
                'supplier_id' => $supplier->id,
                'trace'       => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al guardar la configuración: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Alterna o actualiza el estado activo/desactivado de un producto de proveedor.
     */
    public function toggleProductSupplierStatus(ToggleProductSupplierStatusRequest $request, ProductSupplier $productSupplier)
    {
        $validated = $request->validated();
        $status = isset($validated['is_active']) ? (bool) $validated['is_active'] : null;

        $updated = $this->supplierActionService->toggleProductSupplierStatus($productSupplier, $status);

        return response()->json([
            'message' => $updated->is_active
                ? 'Oferta habilitada correctamente.'
                : 'Oferta desactivada correctamente.',
            'product_supplier' => new SupplierProductResource($updated),
        ]);
    }

    /**
     * Alterna o actualiza el estado activo/desactivado de un proveedor.
     */
    public function toggleSupplierStatus(Request $request, Supplier $supplier)
    {
        $status = $request->has('is_active') ? $request->boolean('is_active') : null;

        $updated = $this->supplierActionService->toggleSupplierStatus($supplier, $status);

        return response()->json([
            'message' => $updated->is_active
                ? 'Proveedor habilitado correctamente.'
                : 'Proveedor desactivado correctamente.',
            'supplier' => new SupplierResource($updated),
        ]);
    }

    /**
     * Obtiene los proveedores desactivados.
     */
    public function getDisabledSuppliers(Request $request)
    {
        $suppliers = $this->supplierQueryService->getDisabledSuppliers($request);

        return response()->json([
            'data' => SupplierResource::collection($suppliers)->resolve(),
        ]);
    }
}
