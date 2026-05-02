<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

$columns = Schema::getColumnListing('employee_cleaning_activity');
echo "Columns in employee_cleaning_activity:\n";
print_r($columns);

$columns = Schema::getColumnListing('cleaning_activities');
echo "\nColumns in cleaning_activities:\n";
print_r($columns);
