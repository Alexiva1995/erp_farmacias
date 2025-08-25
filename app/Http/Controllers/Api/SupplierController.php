<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Services\Suppliers\SupplierQueryService;
use App\Services\Suppliers\SupplierActionService;
use App\Services\Suppliers\SupplierConnectionService;
use App\Http\Requests\StoreSupplierLaboratoryRequest;
use App\Http\Requests\UpdatePaymentRuleSupplierRequest;
use App\Http\Requests\StoreDiscountsRequest;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

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
    ) {}

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
        $supplier = $this->supplierActionService->createSupplier(
            $request->validated(),
        );

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
        $updatedSupplier = $this->supplierActionService->updateSupplier(
            $supplier,
            $request->validated(),
        );

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
     * @param \App\Services\Suppliers\SupplierConnectionService $connectionService
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function connectionServiceSupplier(
        SupplierConnectionService $connectionService,
        Supplier $supplier,
    ) {
        $results = $connectionService->fetchData(
            $supplier->connections->first(),
        );

        $result = $this->supplierQueryService->storeSupplierConnectionData(
            $supplier,
            $results,
        );

        $status = $result ? "ok" : "error";

        return response()->json(
            [
                "status" => $status, 
                "count_product" => count($results['products']),
                "count_invoice" => count($results['invoices']),
            ],
            $status === "error" ? 500 : 200,
        );
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
                'days' =>  $rule['days'],
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
    public function storeLaboratory(
        StoreSupplierLaboratoryRequest $request,
        Supplier $supplier,
    ) {
        $link = $this->supplierActionService->attachLaboratory(
            $supplier,
            $request->validated(),
        );

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
        $grouped = $this->supplierQueryService->getUnpaidInvoicesByDate(
            $supplier,
        );
        return response()->json(["pending_invoices" => $grouped]);
    }

    public function getDiscounts(Supplier $supplier)
    {
        $discounts = $this->supplierQueryService->getDiscounts($supplier);

        return response()->json(["supplier_discount" => $discounts]);
    }

    public function storeDiscounts(
        StoreDiscountsRequest $request,
        Supplier $supplier,
    ) {
        $validated = $request->validated();

        $createdDiscounts = [];

        foreach ($validated["discounts"] as $rule) {
            $discountData = [
                "name" => $rule["name"],
                "discount_percentage" => $rule["discount_percentage"],
            ];

            $createdDiscounts[] = $this->supplierActionService->createDiscount(
                $supplier,
                $discountData,
            );
        }

        return response()->json([
            "message" => "Descuentos registrados correctamente.",
            "discounts" => $createdDiscounts,
        ]);
    }
}
