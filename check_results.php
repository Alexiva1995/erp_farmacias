<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$payslips = App\Models\Payslip::whereYear('payslip_date', 2025)->get(['id', 'name', 'payslip_date', 'total']);
echo "ID | Name | Date | Total USD\n";
foreach ($payslips as $p) {
    echo "{$p->id} | {$p->name} | {$p->payslip_date} | {$p->total}\n";
}
echo "Total payrolls for 2025: " . $payslips->count() . "\n";

$users = App\Models\User::orderBy('id', 'desc')->limit(1)->get();
foreach ($users as $u) {
    echo "Last User ID: " . $u->id . "\n";
}

$employees = App\Models\Employee::orderBy('id', 'desc')->limit(1)->get();
foreach ($employees as $e) {
    echo "Last Employee ID: " . $e->id . "\n";
}
