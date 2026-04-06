<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

use Illuminate\Support\Facades\DB;

try {
    echo "Disabling FK checks...\n";
    DB::statement('SET GLOBAL foreign_key_checks=0;');
    DB::statement('SET SESSION foreign_key_checks=0;');

    echo "Reading farma.sql...\n";
    $sql = file_get_contents('farma.sql');
    if(!$sql) die("Farma.sql not found or empty.");

    echo "Executing raw SQL dump...\n";
    DB::unprepared($sql);

    echo "Enabling FK checks...\n";
    DB::statement('SET GLOBAL foreign_key_checks=1;');
    DB::statement('SET SESSION foreign_key_checks=1;');

    echo "Database import complete.\n";

} catch(\Exception $e) {
    echo "Error: ". $e->getMessage();
}
