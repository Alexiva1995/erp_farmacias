<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Requests\FinalizePayslipRequest;
use App\Http\Requests\UpdateEmployeeVouchersRequest;
use App\Models\Employee;
use App\Models\Payslip;
use App\Services\PayslipServices;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Maatwebsite\Excel\Facades\Excel;

class PayslipController extends Controller
{
    public function __construct(private PayslipServices $payslipServices)
    {
    }
    public function index(Request $request)
    {
        $data = [
            'perPage' => $request->itemsPerPage ?? 10,
            'startDate' => $request->startDate,
            'endDate' => $request->endDate,
        ];
        $results = $this->payslipServices->index($data);

        return ApiResponse::success(['data' => $results->items(), 'total' => $results->total()]);
    }

    public function finalize(Payslip $payslip, FinalizePayslipRequest $request)
    {
        $data = $request->validated();
        $result = $this->payslipServices->finalize($payslip, $data);

        return ApiResponse::success(['status' => $result]);
    }

    public function downloadExcel(Payslip $payslip)
    {
        $data = $this->payslipServices->exportExcel($payslip);
        $fileName = "{$payslip->name}.xlsx";

        return Excel::download($data, $fileName);
    }

    public function getData(Payslip $payslip, string $type)
    {
        $data = $this->payslipServices->getData($payslip, $type);

        return ApiResponse::success([
            'results' => $data['items'],
            'name' => $data['name'],
            'date' => $data['date'],
            'status' => $data['status'],
            'period' => $data['period'],
            'exchange_rate' => $data['exchange_rate'] ?? null,
        ]);
    }

    public function getVouchers(Payslip $payslip, Employee $employee)
    {
        $data = $this->payslipServices->getEmployeeVouchers($payslip, $employee);
        return ApiResponse::success($data);
    }

    public function updateVouchers(Payslip $payslip, UpdateEmployeeVouchersRequest $request)
    {
        $data = $request->validated();
        $results = $this->payslipServices->updateVouchers($payslip, $data);

        return ApiResponse::success(['status' => $results]);
    }

    public function store()
    {
        Artisan::call('app:generate-payslip');

        return ApiResponse::success(['message' => 'Nómina generada exitosamente']);
    }

    public function downloadPdf(Payslip $payslip, Request $request)
    {
        $type = in_array($request->input('type', 'legal'), ['full', 'legal']) ? $request->input('type', 'legal') : 'legal';
        $data = $this->payslipServices->getData($payslip, $type);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.payslip', [
            'items'         => $data['items'],
            'name'          => $data['name'],
            'period'        => $data['period'],
            'status'        => $data['status'],
            'exchange_rate' => $data['exchange_rate'] ?? 1,
        ])->setPaper('letter', 'landscape');

        $filename = 'nomina_' . str_replace([' ', '/'], '_', $data['name']) . '.pdf';

        return $pdf->download($filename);
    }
}
