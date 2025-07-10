<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Services\Suppliers\SupplierQueryService;
use App\Services\Suppliers\SupplierActionService;
use App\Services\Suppliers\SupplierHealthService;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function __construct(
        private SupplierQueryService $supplierQueryService,
        private SupplierActionService $supplierActionService
    ) {
    }

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

    public function store(StoreSupplierRequest $request)
    {
        $supplier = $this->supplierActionService->createSupplier($request->validated());

        return response()->json([
            'message' => 'Proveedor creado con éxito.',
            'supplier' => $supplier
        ], 201);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier)
    {
        $updatedSupplier = $this->supplierActionService->updateSupplier($supplier, $request->validated());

        return response()->json([
            'message' => 'Proveedor actualizado con éxito.',
            'supplier' => $updatedSupplier
        ], 200);
    }

    public function destroy(Supplier $supplier)
    {
        $this->supplierActionService->deleteSupplier($supplier);
        return response()->noContent();
    }

    public function checkApiHealth(SupplierHealthService $healthService)
    {
        $results = $healthService->check();

        if (isset($results['error'])) {
            return response()->json(['status' => 'error', 'results' => $results], 500);
        }

        return response()->json(['status' => 'ok', 'results' => $results]);
    }
}
