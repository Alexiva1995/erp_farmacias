<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\InventoryStockFilterRequest;
use App\Services\InventoryStockService;

class InventoryStockController extends Controller
{
    public function __construct(
        protected InventoryStockService $stockService
    ) {
    }

    public function filter(InventoryStockFilterRequest $request)
    {
        $filtros = $this->stockService->extractFilters($request);
        $respuestaConsulta = $this->stockService->getFilteredStock($filtros);

        return ApiResponse::success($respuestaConsulta, 'ok', 200);
    }

    public function filterWithoutPaginate(InventoryStockFilterRequest $request)
    {
        $filtros = $this->stockService->extractFilters($request);
        $respuestaConsulta = $this->stockService->getFilteredStockWithoutPaginate($filtros);

        return ApiResponse::success($respuestaConsulta, 'ok', 200);
    }

    public function exportarExcel(InventoryStockFilterRequest $request)
    {
        $filtros = $this->stockService->extractFilters($request);
        
        // Mantener delegación a exportExcel
        /** @var \App\Services\ProductServices $productService */
        $productService = app(\App\Services\ProductServices::class);
        $export = $productService->exportExcel($filtros);

        $formato = $request->input('formato', 'xlsx');
        $fileName = 'stock-products.' . $formato;

        return \Maatwebsite\Excel\Facades\Excel::download($export, $fileName);
    }
}
