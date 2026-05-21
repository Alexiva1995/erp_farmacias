<?php

namespace App\Http\Controllers\Api\Bi;

use App\Http\Controllers\Controller;
use App\Services\Bi\DiscountReportService;
use Illuminate\Http\Request;
use App\Helpers\ApiResponse;

class DiscountReportController extends Controller
{
    public function __construct(
        protected DiscountReportService $service
    ) {}

    /**
     * Obtiene los datos del dashboard de descuentos
     */
    public function dashboard(Request $request)
    {
        try {
            $filters = $request->only(['start_date', 'end_date']);
            $data = $this->service->getDashboardData($filters);
            
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene los datos de auditoría de descuentos
     */
    public function audit(Request $request)
    {
        try {
            $filters = $request->only(['start_date', 'end_date', 'itemsPerPage', 'page', 'discount_type']);
            $data = $this->service->getAuditData($filters);
            
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
