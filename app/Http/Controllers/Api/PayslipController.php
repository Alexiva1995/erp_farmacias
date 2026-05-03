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
use Carbon\Carbon;
use App\Models\PayslipDetails;
use App\Models\SalaryConcept;
use Illuminate\Support\Facades\DB;

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

    public function store(Request $request)
    {
        try {
            $date = $request->input('date', now()->toDateString());
            
            Artisan::call('app:generate-payslip', [
                '--date' => $date
            ]);

            return ApiResponse::success(['message' => 'Nómina generada exitosamente']);
        } catch (\Exception $e) {
            \Log::error("Error al generar nómina: " . $e->getMessage());
            return ApiResponse::error('Error al generar la nómina: ' . $e->getMessage(), 500);
        }
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

    public function downloadBulkPdf(Request $request)
    {
        $year = $request->input('year', 2025);
        $type = in_array($request->input('type', 'legal'), ['full', 'legal']) ? $request->input('type', 'legal') : 'legal';
        
        $payslips = Payslip::whereYear('payslip_date', $year)
            ->orderBy('payslip_date', 'asc')
            ->get();

        $allData = [];
        foreach ($payslips as $payslip) {
            $allData[] = $this->payslipServices->getData($payslip, $type);
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.payslip_bulk', [
            'payrolls' => $allData,
        ])->setPaper('letter', 'landscape');

        $filename = "nominas_consolidadas_{$year}.pdf";

        return $pdf->download($filename);
    }

    public function regenerateHistory()
    {
        try {
            DB::beginTransaction();

            // 1. Sincronizar Conceptos Críticos
            $concepts = [
                'Salario Básico Mensual' => ['type' => 'salary', 'frequency' => 'fortnight'],
                'Bono de Alimentación' => ['type' => 'salary', 'frequency' => 'monthly'],
                'Asistencia Social de Salud (Art. 105 LOTTT)' => ['type' => 'salary', 'frequency' => 'monthly'],
                'Bono Extraordinario de Rendimiento' => ['type' => 'salary', 'frequency' => 'monthly'],
                'IVSS (4%)' => ['type' => 'deduction', 'frequency' => 'fortnight'],
                'RPE - Paro Forzoso (0.5%)' => ['type' => 'deduction', 'frequency' => 'fortnight'],
                'FAOV (1%)' => ['type' => 'deduction', 'frequency' => 'fortnight']
            ];

            foreach ($concepts as $name => $data) {
                SalaryConcept::updateOrCreate(['name' => $name], $data);
            }

            // 2. Limpiar periodos específicos
            Payslip::whereIn('payslip_date', ['2026-03-31', '2026-04-15'])->each(function($p) {
                $p->details()->delete();
                $p->delete();
            });

            // 3. Generar Marzo 31
            $march31 = Carbon::parse('2026-03-31');
            $this->payslipServices->generate($march31);
            $pMarch = Payslip::where('payslip_date', '2026-03-31')->latest()->first();
            if ($pMarch) {
                $pMarch->update(['exchange_rate' => 473.87]);
                // Excluir Marianny (UID 89)
                PayslipDetails::where('payslip_id', $pMarch->id)
                    ->whereHas('salary', function($q) { $q->where('user_id', 89); })
                    ->delete();
                $pMarch->update(['total' => PayslipDetails::where('payslip_id', $pMarch->id)->where('amount', '>', 0)->sum('amount')]);
            }

            // 4. Generar Abril 15
            $april15 = Carbon::parse('2026-04-15');
            $this->payslipServices->generate($april15);
            $pApril = Payslip::where('payslip_date', '2026-04-15')->latest()->first();
            if ($pApril) {
                $pApril->update(['exchange_rate' => 478.58]);
                // Excluir Marianny (89) y Jose (90)
                PayslipDetails::where('payslip_id', $pApril->id)
                    ->whereHas('salary', function($q) { $q->whereIn('user_id', [89, 90]); })
                    ->delete();
                $pApril->update(['total' => PayslipDetails::where('payslip_id', $pApril->id)->where('amount', '>', 0)->sum('amount')]);
            }

            DB::commit();
            return ApiResponse::success(['message' => 'Historial de nóminas (Marzo/Abril) regenerado con éxito.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al regenerar historial: ' . $e->getMessage());
        }
    }
}
