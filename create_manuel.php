<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Employee;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

try {
    $adminRole = Role::where('name', 'Admin')->first();
    
    // Manually assign IDs since DB doesn't have auto-increment defaults for some reason or trigger is failing
    $nextUserId = 100; // Safe offset
    $nextEmployeeId = 20; // Safe offset

    $user = new User();
    $user->id = $nextUserId;
    $user->username = 'manuelpirela';
    $user->email = 'manuel.pirela@example.com';
    $user->password_hash = Hash::make('password123');
    $user->role_id = $adminRole ? $adminRole->id : 1;
    $user->is_active = true;
    $user->save();

    $employee = new Employee();
    $employee->id = $nextEmployeeId;
    $employee->user_id = $user->id;
    $employee->name = 'Manuel Alfonso';
    $employee->last_name = 'Pirela aranque';
    $employee->identification = '9399935';
    $employee->is_active = true;
    $employee->total_package_usd = 160.00;
    $employee->save();

    echo "Empleado Manuel creado exitosamente con ID Usuario: " . $user->id . " e ID Empleado: " . $employee->id . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
