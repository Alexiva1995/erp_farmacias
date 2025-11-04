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
            'data' => $cashClosings
        ]);
     }

    public function downloadReport(Request $request){
        $filename = $request->input('filename') . now()->format('Y_m_d_His') . '.pdf';
        $pdf = $this->pdf($request->input('html_content'));
        return $pdf->download($filename );
    }

    public function printdReport(Request $request){
        $filename = $request->input('filename') . now()->format('Y_m_d_His') . '.pdf';
        $pdf = $this->pdf($request->input('html_content'));
        return $pdf->stream($filename );
    }

    public function getmonthlyCashclosingAllSellers(Request $request){
        $dailyClosureIds = $request->input('closingMonthlyIds', []);
        $cashClosings = $this->cashClosureActionService->getCashClosingsAllSellers($dailyClosureIds);
        return response()->json([
            'data' => $cashClosings
        ]);
    }
}
