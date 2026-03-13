<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Payslip;
use App\Models\PayslipDetails;
use Illuminate\Support\Facades\DB;

$payslips = Payslip::whereYear('payslip_date', 2025)->get();
foreach ($payslips as $p) {
    PayslipDetails::where('payslip_id', $p->id)->delete();
    $p->delete();
}

echo "Nóminas de 2025 eliminadas para regeneración limpia.\n";
