<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Services\Suppliers\SupplierQueryService;
use App\Services\Suppliers\SupplierActionService;
use App\Services\Suppliers\SupplierHealthService;
use App\Http\Requests\StoreSupplierLaboratoryRequest;
use App\Http\Requests\UpdatePaymentRuleSupplierRequest;
use App\Models\Supplier;
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
        private SupplierActionService $supplierActionService
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
        $perPage = $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json(['data' => $items, 'total' => $items->count()]);
        }
        $paginatedResult = $query->paginate($perPage);
        return response()->json(['data' => $paginatedResult->items(), 'total' => $paginatedResult->total()]);
    }

    /**
     * Summary of store
     * @param \App\Http\Requests\StoreSupplierRequest $request
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function store(StoreSupplierRequest $request)
    {
        $supplier = $this->supplierActionService->createSupplier($request->validated());

        return response()->json([
            'message' => 'Proveedor creado con éxito.',
            'supplier' => $supplier
        ], 201);
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

        return response()->json([
            'message' => 'Proveedor actualizado con éxito.',
            'supplier' => $updatedSupplier
        ], 200);
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
     * Summary of checkApiHealth
     * @param \App\Services\Suppliers\SupplierHealthService $healthService
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function checkApiHealth(SupplierHealthService $healthService)
    {
        $results = $healthService->check();

        if (isset($results['error'])) {
            return response()->json(['status' => 'error', 'results' => $results], 500);
        }

        return response()->json(['status' => 'ok', 'results' => $results]);
    }

    /**
     * Update or create a payment rule for a supplier.
     *
     * @param UpdatePaymentRuleSupplierRequest $request
     * @param Supplier $supplier
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePaymentRule(UpdatePaymentRuleSupplierRequest $request, Supplier $supplier)
    {
        $rule = $this->supplierActionService->updatePaymentRule($supplier, $request->validated());

        return response()->json([
            'message' => 'Pronto pago actualizado con éxito.',
            'rule' => $rule,
        ]);
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
            'message' => 'Laboratorio vinculado con éxito.',
            'laboratory_link' => $link->load('laboratory'),
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

        return response()->json(['laboratory_links' => $links]);
    }
}
