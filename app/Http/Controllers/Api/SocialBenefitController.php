<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\FireEmployeeRequest;
use App\Models\Employee;
use App\Services\SocialBenefitServices;
use Illuminate\Http\Request;

class SocialBenefitController extends Controller
{
    public function __construct(protected SocialBenefitServices $socialBenefitServices) {}

    public function index(Request $request)
    {
        $data = [
            'search' => $request->search,
            'perPage' => $request->perPage,
        ];
        $result = $this->socialBenefitServices->index($data);

        return ApiResponse::success($result);
    }

    public function payment(Employee $employee, Request $request)
    {
        try {
            $data = $request->all();
            $result = $this->socialBenefitServices->payment($employee, $data);

            return ApiResponse::success(['status' => $result]);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    public function getSettlementData(Employee $employee, Request $request)
    {
        $overrides = $request->only([
            'hire_date', 
            'base_salary_usd', 
            'additional_deductions_usd',
            'vacation_deduction_bs',
            'vacation_bonus_deduction_bs',
            'earnings_deduction_bs'
        ]);
        $result = $this->socialBenefitServices->getSettlementData($employee, $overrides);

        return ApiResponse::success($result);
    }

    public function fire(Employee $employee, FireEmployeeRequest $request)
    {
        try {
            $data = $request->validated();
            $overrides = $request->get('overrides', []);
            
            $result = $this->socialBenefitServices->fire($employee, $data);

            if ($result) {
                $pdf = $this->socialBenefitServices->generatePdf($employee, $overrides);
                $filename = 'liquidacion-' . $employee->identification . '.pdf';
                return $pdf->download($filename);
            }

            return ApiResponse::error('No se pudo procesar la liquidación del empleado', 422);
        } catch (\Exception $e) {
            \Log::error('Erro en liquidación: ' . $e->getMessage());
            return ApiResponse::error('Error interno: ' . $e->getMessage(), 500);
        }
    }
}
