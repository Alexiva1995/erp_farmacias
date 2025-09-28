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

        // 2. Definir parámetros de paginación
        $perPage = $request->input('itemsPerPage', 10);
        $page = $request->input('page', 1);

        // Calcular el desplazamiento (offset)
        $offset = ($page - 1) * $perPage;

        // Obtener los ítems para la página actual
        $itemsForCurrentPage = $query->slice($offset, $perPage)->values();

        // 3. Crear el LengthAwarePaginator
        $paginatedResult = new LengthAwarePaginator(
            $itemsForCurrentPage, // ítems de la página
            $query->count(),  // total de ítems
            $perPage,             // ítems por página
            $page,                // número de página actual
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // 4. Devolver la respuesta en el formato esperado
        return response()->json([
            'data' => $paginatedResult->items(),
            'total' => $paginatedResult->total()
        ]);
    }
}
