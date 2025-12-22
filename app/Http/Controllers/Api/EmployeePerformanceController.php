<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EmployeePerformance\EmployeePerformanceQueryService;
use Illuminate\Http\Request;

class EmployeePerformanceController extends Controller
{
    public function __construct(
        protected EmployeePerformanceQueryService $performanceQueryService
    ) {
    }

    /**
     * Get active employees for monthly performance evaluation.
     */
    public function index(Request $request)
    {
        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);

        try {
            $employees = $this->performanceQueryService->getEmployeesWithPerformance($month, $year);

            return response()->json([
                'status' => true,
                'message' => 'Empleados recuperados exitosamente',
                'data' => $employees,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error al obtener métricas de empleados: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }
}
