<?php

namespace App\Http\Controllers\Api;

use App\Exports\HistoriesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fiscal\FiscalHistoryIndexRequest;
use App\Http\Resources\Fiscal\FiscalHistoryResource;
use App\Services\History\HistoryActionService;
use App\Services\History\HistoryQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FiscalController extends Controller
{
    public function __construct(
        private HistoryActionService $historyActionService,
        private HistoryQueryService $historyQueryService
    ) {}

    /**
     * Display a paginated listing of the fiscal history resource.
     */
    public function index(FiscalHistoryIndexRequest $request): JsonResponse
    {
        $query = $this->historyQueryService->getFilteredQuery($request);
        $perPage = (int) $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json([
                'data'  => FiscalHistoryResource::collection($items),
                'total' => $items->count(),
                'stats' => $this->historyQueryService->getSummaryStats($request),
            ]);
        }

        $paginatedResult = $query->paginate($perPage);

        return response()->json([
            'data'  => FiscalHistoryResource::collection($paginatedResult->getCollection()),
            'total' => $paginatedResult->total(),
            'stats' => $this->historyQueryService->getSummaryStats($request),
        ]);
    }

    /**
     * Export fiscal history report to Excel/CSV.
     */
    public function export(Request $request): BinaryFileResponse
    {
        $query = $this->historyQueryService->getFilteredQuery($request);
        $format = $request->input('format', 'xlsx');
        $fileName = 'HistoriaFiscal-' . now()->format('Y-m-d') . '.' . $format;
        
        return Excel::download(new HistoriesExport($query), $fileName);
    }
}
