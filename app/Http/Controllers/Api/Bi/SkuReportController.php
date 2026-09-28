<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Bi;

use App\Http\Controllers\Controller;
use App\Services\Bi\SkuReportService;
use App\Http\Resources\Bi\SkuReportResource;
use App\Http\Requests\Bi\SkuReportRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SkuReportController extends Controller
{
    protected SkuReportService $skuReportService;

    public function __construct(SkuReportService $skuReportService)
    {
        $this->skuReportService = $skuReportService;
    }

    /**
     * Genera el reporte de Margen por SKU basado en las ventas concretadas y mermas.
     */
    public function generateReport(SkuReportRequest $request)
    {
        $filters = $request->validated();
        $perPage = (int) $request->input('itemsPerPage', 15);
        
        $paginatedReport = $this->skuReportService->generateReport($filters, $perPage);
        $summary = $this->skuReportService->getGlobalSummary($filters);
        $charts = $this->skuReportService->getChartsData($filters);

        return response()->json([
            'data' => SkuReportResource::collection($paginatedReport->getCollection())->resolve(),
            'total' => $paginatedReport->total(),
            'current_page' => $paginatedReport->currentPage(),
            'last_page' => $paginatedReport->lastPage(),
            'summary' => $summary,
            'charts' => $charts,
        ]);
    }

    /**
     * Exporta el reporte de Margen SKU en formato CSV con streaming eficiente.
     */
    public function export(SkuReportRequest $request): StreamedResponse
    {
        $filters = $request->validated();
        $allData = $this->skuReportService->generateReport($filters, 50000);
        $items = collect($allData->items());
        
        if (!empty($filters['semaphore'])) {
            $items = $items->where('semaphore', $filters['semaphore'])->values();
        }

        $fileName = 'margen_sku_' . now()->format('Y_m_d_His') . '.csv';
        $headers = [
            'Content-type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $columns = [
            'ID/SKU',
            'Producto',
            'Principio Activo',
            'Laboratorio',
            'Stock Actual',
            'Vendidos',
            'Costo Unit.',
            'P. Lista',
            'M. Bruto %',
            'Descuento Prom %',
            'M. Neto %',
            'Mermas ($)',
            'M. Real %',
            'Semáforo'
        ];

        $callback = function () use ($items, $columns) {
            $file = fopen('php://output', 'w');
            
            // BOM UTF-8 para Excel
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            
            fputcsv($file, $columns, ';', '"', '\\');

            foreach ($items as $item) {
                $row = [
                    $item->barcode ?: $item->product_id,
                    $item->product_name,
                    $item->active_ingredient ?: 'N/A',
                    $item->laboratory_name ?: 'S/L',
                    (float) ($item->current_stock ?? 0),
                    (int) $item->total_sold,
                    round((float) $item->current_cost, 2),
                    round((float) $item->list_price, 2),
                    round((float) $item->gross_margin_percent, 2),
                    round((float) $item->discount_avg_percent, 2),
                    round((float) $item->net_margin_percent, 2),
                    round((float) $item->loss_value, 2),
                    round((float) $item->real_margin_percent, 2),
                    strtoupper((string) $item->semaphore),
                ];

                fputcsv($file, $row, ';', '"', '\\');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
