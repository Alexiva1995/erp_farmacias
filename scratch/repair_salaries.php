<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\UsersSalaryDetails;
use App\Models\SalaryConcept;
use App\Models\Payslip;
use App\Models\PayslipDetails;
use Illuminate\Support\Facades\DB;

try {
    DB::beginTransaction();

    // 1. Corregir users_salary_details (Configuración base)
    $concepts = SalaryConcept::whereIn('name', ['Salario Básico Mensual', 'Bono de Alimentación'])->get();
    foreach ($concepts as $concept) {
        $updated = UsersSalaryDetails::where('salary_concept_id', $concept->id)
            ->where(function($q) {
                $q->where('amount', 0)->orWhereNull('amount');
            })
            ->update(['amount' => 40.00]);
        echo "Actualizados " . $updated . " registros de " . $concept->name . " en la configuración general.\n";
    }

    // 2. Corregir las nóminas recientes (de abril y mayo)
    $recentPayslips = Payslip::whereIn(DB::raw('MONTH(payslip_date)'), [4, 5])->whereYear('payslip_date', 2026)->get();
    foreach ($recentPayslips as $payslip) {
        echo "Corrigiendo nómina ID: " . $payslip->id . " (" . $payslip->name . ")\n";
        
        $conceptSalario = SalaryConcept::where('name', 'Salario Básico Mensual')->first();
        if ($conceptSalario) {
            $details = PayslipDetails::where('payslip_id', $payslip->id)
                ->whereHas('salary', function($q) use ($conceptSalario) {
                    $q->where('salary_concept_id', $conceptSalario->id);
                })
                ->where('amount', 0)
                ->update(['amount' => 20.00]);
            
            echo "  Actualizados " . $details . " registros de sueldo base.\n";
        }

        // Recalcular el total de la nómina
        $total = PayslipDetails::where('payslip_id', $payslip->id)
            ->where('amount', '>', 0)
            ->sum('amount');
        
        $payslip->update(['total' => $total]);
        echo "  Total de la nómina actualizado a: " . $total . " USD\n";
    }

    DB::commit();
    echo "Reparación completada con éxito.\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "Error durante la reparación: " . $e->getMessage() . "\n";
}
