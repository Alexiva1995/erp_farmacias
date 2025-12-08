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
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
            $items = $query->get();
            return response()->json([
                "data" => $items,
                "total" => $items->count(),
            ]);
        }
        $paginatedResult = $query->paginate($perPage);
        return response()->json([
            "data" => $paginatedResult->items(),
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

        return response()->json(
            [
                "message" => "Proveedor creado con éxito.",
                "supplier" => $supplier,
            ],
            201,
        );
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

        return response()->json(
            [
                "message" => "Proveedor actualizado con éxito.",
                "supplier" => $updatedSupplier,
            ],
            200,
        );
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
        ProcessSupplierConnectionJob::dispatch($supplier, $userId);

        return response()->json(["status" => "queued"]);
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

        foreach ($validated['rules'] as $rule) {
            $ruleData = [
                'days' => $rule['days'],
                'discount_percentage' => $rule['discount_percentage'],
            ];

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
        $link = $this->supplierActionService->attachLaboratory($supplier, $request->validated());

        return response()->json([
            "message" => "Laboratorio vinculado con éxito.",
            "laboratory_link" => $link->load("laboratory"),
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

            $createdDiscounts[] = $this->supplierActionService->createDiscount($supplier, $discountData);
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
            "data" => $results->items(),
            "total" => $results->total(),
        ]);
    }

    public function getSupplierProducts(Supplier $supplier, Request $request)
    {
        $results = $this->supplierQueryService->getSupplierProducts($supplier, $request);

        return response()->json([
            "data" => $results->items(),
            "total" => $results->total(),
        ]);
    }

    public function getProducts(Request $request)
    {
        $results = $this->supplierQueryService->getProducts($request);

        return response()->json([
            "data" => $results->items(),
            "total" => $results->total(),
        ]);
    }

    public function getLaboratories()
    {
        $results = $this->supplierQueryService->getAvailableLaboratories();

        return response()->json($results);
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

        return $results
            ? ApiResponse::success()
            : ApiResponse::error();
    }

    public function importData(Supplier $supplier, GetDataFromSupplierFileRequest $request)
    {
        $userId = auth()->id() ?? 1;
        $validated = $request->validated();

        unset($validated["file"]);

        try {
            $path = $request->file("file")->store("temp", ["disk" => "local"]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to store file'], 500);
        }

        ProcessSupplierConnectionJob::dispatch($supplier, $userId, $path, $validated);

        return response()->json(["status" => "queued"]);
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
}
