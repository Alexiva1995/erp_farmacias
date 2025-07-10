<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Exports\HistoriesExport;
use Illuminate\Http\Request;
use App\Models\FiscalHistory;
use App\Services\History\HistoryActionService;
use App\Services\History\HistoryQueryService;
use Maatwebsite\Excel\Facades\Excel;

class FiscalController extends Controller
{

    public function __construct(
        private HistoryActionService $HistoryActionService,
        private HistoryQueryService $HistoryQueryService
    ) {}

    public function index(Request $request)
    {
        $query = $this->HistoryQueryService->getFilteredQuery($request);
        $perPage = $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json(['data' => $items, 'total' => $items->count()]);
        }
        $paginatedResult = $query->paginate($perPage);
        return response()->json(['data' => $paginatedResult->items(), 'total' => $paginatedResult->total()]);
    }

    public function export(Request $request)
    {
        $query = $this->HistoryQueryService->getFilteredQuery($request);
        $format = $request->input('format', 'xlsx');
        $fileName = 'HistoriaFiscal-' . now()->format('Y-m-d') . '.' . $format;
        return Excel::download(new HistoriesExport($query), $fileName);
    }
}
