<?php

namespace App\Http\Controllers\Api;

use App\Exports\TraceabilityExport;
use App\Http\Controllers\Controller;
use App\Services\Traceability\TraceabilityQueryService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class TraceabilityController extends Controller
{
    public function __construct(
        private TraceabilityQueryService $salesReportQueryService
    ) {
    }

    public function index(Request $request)
    {
        $query = $this->salesReportQueryService->getFilteredQuery($request);

        $perPage = $request->input('itemsPerPage', 10);
        $paginatedResult = $query->paginate($perPage);

        return response()->json([
            'data' => $paginatedResult->items(),
            'total' => $paginatedResult->total(),
        ]);
    }

    public function export(Request $request)
    {
        $query = $this->salesReportQueryService->getFilteredQuery($request);

        $format = $request->input('format', 'xlsx');
        $fileName = 'reporte-ventas-' . now()->format('Y-m-d') . '.' . $format;

        return Excel::download(new TraceabilityExport($query), $fileName);
    }
}
