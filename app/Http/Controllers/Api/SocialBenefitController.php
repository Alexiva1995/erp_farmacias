<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\FireEmployeeRequest;
use App\Http\Requests\SocialBenefit\UploadSignedSettlementRequest;
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
            'perPage' => $request->perPage ?? 10,
        ];
        $paginator = $this->socialBenefitServices->index($data);

        return ApiResponse::success([
            'data' => \App\Http\Resources\SocialBenefitEmployeeResource::collection($paginator->items()),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ]);
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
            'resignation_date',
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

    public function downloadSettlement(Employee $employee)
    {
        try {
            $pdf = $this->socialBenefitServices->generatePdf($employee);
            $filename = 'liquidacion-' . $employee->identification . '.pdf';
            return $pdf->download($filename);
        } catch (\Exception $e) {
            \Log::error('Error en downloadSettlement: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            return ApiResponse::error('No se pudo generar el documento de liquidación: ' . $e->getMessage(), 500);
        }
    }

    public function uploadSignedSettlement(Employee $employee, UploadSignedSettlementRequest $request)
    {
        try {
            $settlement = $employee->settlement;
            if (!$settlement) {
                return ApiResponse::error('No se encontró un registro de liquidación para este empleado.', 404);
            }

            if ($request->hasFile('file')) {
                // Eliminar archivo anterior si existe
                if ($settlement->signed_document_path && \Storage::disk('public')->exists($settlement->signed_document_path)) {
                    \Storage::disk('public')->delete($settlement->signed_document_path);
                }

                $file = $request->file('file');
                $filename = 'signed_settlement_' . $employee->id . '_' . time() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('settlements/signed', $filename, 'public');

                $settlement->update(['signed_document_path' => $path]);

                return ApiResponse::success(['path' => $path], 'Documento firmado subido correctamente');
            }

            return ApiResponse::error('No se recibió ningún archivo', 400);
        } catch (\Exception $e) {
            return ApiResponse::error('Error al subir documento: ' . $e->getMessage(), 500);
        }
    }

    public function downloadSignedSettlement(Employee $employee)
    {
        try {
            $settlement = $employee->settlement;
            if (!$settlement || !$settlement->signed_document_path) {
                return ApiResponse::error('No hay un documento firmado registrado.', 404);
            }

            if (!\Storage::disk('public')->exists($settlement->signed_document_path)) {
                return ApiResponse::error('El archivo físico no existe en el servidor.', 404);
            }

            return \Storage::disk('public')->download($settlement->signed_document_path);
        } catch (\Exception $e) {
            return ApiResponse::error('Error al descargar documento: ' . $e->getMessage(), 500);
        }
    }
}
