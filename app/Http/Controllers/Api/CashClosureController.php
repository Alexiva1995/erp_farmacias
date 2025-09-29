<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\CashClosure\CashClosureActionService;
use App\Services\CashClosure\CashClosureQueryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\CashClosureExport;
use App\Http\Requests\CashClosure\CloseCashClosureRequest;
use Illuminate\Pagination\LengthAwarePaginator;

class CashClosureController extends Controller
{

    public function __construct(
        private CashClosureActionService $cashClosureActionService,
        private CashClosureQueryService $cashClosureQueryService,
    ) {}

    public function  getCashClosure(Request $request)
    {
        $query = $this->cashClosureActionService->allCashClosing($request);
        return $query;
    }

    public function  getClosingHistory(Request $request)
    {
        $query = $this->cashClosureQueryService->getFilteredQuery($request);
        $perPage = $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json(['data' => $items, 'total' => $items->count()]);
        }
        $paginatedResult = $query->paginate($perPage);
        return response()->json(['data' => $paginatedResult->items(), 'total' => $paginatedResult->total()]);
    }


    public function pdf($html)
    {
        $pdf = Pdf::loadHtml($html);
        return $pdf;
    }

    public function generate(Request $request)
    {
        $request->validate([
            'html' => 'required|string',
            'filename' => 'required|string'
        ]);
        $pdf = $this->pdf($request->input('html'));
        return $pdf->download($request->input('filename'));
    }

    public function closeCash(CloseCashClosureRequest $request)
    {

        return $this->cashClosureActionService->closeCashClosing($request);
    }

    public function  getCashClosureOrders(Request $request)
    {
        $query = $this->cashClosureQueryService->getFilteredQueryOrder($request);
        $perPage = $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json(['data' => $items, 'total' => $items->count()]);
        }
        $paginatedResult = $query->paginate($perPage);
        return response()->json(['data' => $paginatedResult->items(), 'total' => $paginatedResult->total()]);
    }

    public function  getSummarysales()
    {
        $summaryData = $this->cashClosureActionService->getMonthlySalesSummaryData();
        return response()->json($summaryData);
    }

    public function  getDailyCashTable(Request $request)
    {
        $query = $this->cashClosureQueryService->getFilteredQueryDaily($request);
        $perPage = $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json(['data' => $items, 'total' => $items->count()]);
        }
        $paginatedResult = $query->paginate($perPage);
        return response()->json(['data' => $paginatedResult->items(), 'total' => $paginatedResult->total()]);
    }

    public function  getMonthlyCashTable(Request $request)
    {
        $query = $this->cashClosureQueryService->getFilteredQueryMonthly($request);
        $perPage = $request->input('itemsPerPage', 10);
        $perPage = $request->input('itemsPerPage', 10);
        $page = $request->input('page', 1);
        $offset = ($page - 1) * $perPage;
        $itemsForCurrentPage = $query->slice($offset, $perPage)->values();
        $paginatedResult = new LengthAwarePaginator(
            $itemsForCurrentPage, 
            $query->count(),
            $perPage,           
            $page,        
            ['path' => $request->url(), 'query' => $request->query()]
        );
        return response()->json([
            'data' => $paginatedResult->items(),
            'total' => $paginatedResult->total()
        ]);
    }
    public function getSellerCashTable(Request $request)
    {   
        $query = $this->cashClosureQueryService->getFilteredQuerySellerCash($request);
        $perPage = $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json(['data' => $items, 'total' => $items->count()]);
        }
        $paginatedResult = $query->paginate($perPage);
        return response()->json(['data' => $paginatedResult->items(), 'total' => $paginatedResult->total()]);
    }

     public function getmonthlyCashclosing(Request $request){
        $dailyClosureIds = $request->input('closingMonthlyIds', []); 
        $cashClosings = $this->cashClosureActionService->getCashClosingsForMonthlySummary($dailyClosureIds);

        return response()->json([
            'data' => $cashClosings,
            'total' => $cashClosings->count(),
        ]);
     }

    public function downloadMonthlyReport(Request $request){

      /*  $dailyClosureIds = $request->input('closingMonthlyIds', []); 
        if (empty($dailyClosureIds)) {
            return response()->json(['error' => 'No se proporcionaron identificadores para el reporte.'], 400);
        }
        $cashesList = $this->cashClosureActionService->getCashClosingsForMonthlySummary($dailyClosureIds);
        $totalAmount = $cashesList->sum('total_amount_raw'); 

        // 3. Cargar la vista Blade con los datos
        // NOTA: Asegúrate de que esta vista exista en resources/views/reports/monthly_cash_summary_pdf.blade.php
        $pdf = Pdf::loadView('reports.monthly_cash_summary_pdf', [
            'cashesList' => $cashesList,
            'totalAmount' => number_format($totalAmount, 2, ',', '.') . '$',
            // ... (pasar otros totales globales)
        ]);
        
        // 4. Devolver la descarga forzada del PDF
        $filename = 'Resumen_Cierres_Mensual_' . now()->format('Y_m_d_His') . '.pdf';
        
        return $pdf->download($filename);*/

        $filename = 'Resumen_Cierres_Mensual_' . now()->format('Y_m_d_His') . '.pdf';
        $pdf = $this->pdf($request->input('html_content'));
        return $pdf->download($filename );

    }
}
