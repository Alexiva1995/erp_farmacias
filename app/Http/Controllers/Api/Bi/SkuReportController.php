<?php

namespace App\Http\Controllers\Api\Bi;

use App\Http\Controllers\Controller;
use App\Services\Bi\SkuReportService;
use App\Http\Resources\Bi\SkuReportResource;
use Illuminate\Http\Request;

class SkuReportController extends Controller
{
    protected $skuReportService;

    public function __construct(SkuReportService $skuReportService)
    {
        $this->skuReportService = $skuReportService;
    }

    /**
     * Genera el reporte de Margen por SKU basado en las ventas concretadas y mermas.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateReport(Request $request)
    {
        $filters = $request->only([
            'start_date',
            'end_date',
            'laboratory_id',
            'group_id',
            'semaphore', // verde, amarillo, rojo, negro
            'is_active', // estado del producto
            'sortBy',
            'orderBy'
        ]);

        $perPage = $request->input('itemsPerPage', 15);
        
        $paginatedReport = $this->skuReportService->generateReport($filters, $perPage);

        // Si hay filtrado por Semáforo después de calculado
        if (!empty($filters['semaphore'])) {
             $filteredItems = collect($paginatedReport->items())
                ->where('semaphore', $filters['semaphore'])
                ->values();
             $paginatedReport->setCollection($filteredItems);
        }

        return response()->json([
            'data' => SkuReportResource::collection($paginatedReport->getCollection())->resolve(),
            'total' => $paginatedReport->total(),
            'current_page' => $paginatedReport->currentPage(),
            'last_page' => $paginatedReport->lastPage(),
            // Delegamos el resumen global al servicio para mantener la consistencia
            'summary' => $this->skuReportService->getGlobalSummary($filters)
        ]);
    }

    /**
     * Exporta el reporte de Margen SKU
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function export(Request $request)
    {
        $filters = $request->only([
            'start_date', 'end_date', 'laboratory_id', 'group_id', 'semaphore', 'search', 'is_active'
        ]);

        // Obtenemos todos los registros sin paginar
        $allData = $this->skuReportService->generateReport($filters, 999999);
        
        $items = collect($allData->items());
        
        if (!empty($filters['semaphore'])) {
            $items = $items->where('semaphore', $filters['semaphore'])->values();
        }

        // Exportación básica a CSV (Puede ser sustituido por Laravel Excel si está instalado)
        $fileName = 'margen_sku_' . now()->format('Y_m_d_His') . '.csv';
        $headers = array(
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        $columns = ['ID/SKU', 'Producto', 'Vendidos', 'Costo Unit.', 'P. Lista', 'M. Bruto %', 'Descuento Prom %', 'M. Neto %', 'Mermas ($)', 'M. Real %', 'Semáforo'];

        $callback = function() use($items, $columns) {
            $file = fopen('php://output', 'w');
            
            // BOM para UTF-8 en Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($file, $columns, ';'); // Exportar con CSV europeo/excel

            foreach ($items as $item) {
                $row['ID/SKU']  = $item->barcode ?: $item->product_id;
                $row['Producto']    = $item->product_name;
                $row['Vendidos']    = $item->total_sold;
                $row['Costo Unit.']  = round($item->current_cost, 2);
                $row['P. Lista']  = round($item->list_price, 2);
                $row['M. Bruto %']  = round($item->gross_margin_percent, 2);
                $row['Descuento Prom %']  = round($item->discount_avg_percent, 2);
                $row['M. Neto %']  = round($item->net_margin_percent, 2);
                $row['Mermas ($)']  = round($item->loss_value, 2);
                $row['M. Real %']  = round($item->real_margin_percent, 2);
                $row['Semáforo']  = strtoupper($item->semaphore);

                fputcsv($file, array(
                    $row['ID/SKU'], $row['Producto'], $row['Vendidos'], $row['Costo Unit.'], 
                    $row['P. Lista'], $row['M. Bruto %'], $row['Descuento Prom %'], $row['M. Neto %'], 
                    $row['Mermas ($)'], $row['M. Real %'], $row['Semáforo']
                ), ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
