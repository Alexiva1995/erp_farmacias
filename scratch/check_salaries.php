<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Employee;

$employees = Employee::where('is_active', true)->get();
foreach ($employees as $e) {
    echo "Employee: " . $e->name . " " . $e->last_name . " (" . $e->identification . ")\n";
    if ($e->user) {
        foreach ($e->user->salaries as $s) {
            echo "  - " . $s->concept->name . ": " . $s->amount . "\n";
        }
    } else {
        echo "  - No user found\n";
    }
}
