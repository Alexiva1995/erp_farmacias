<?php

namespace App\Console\Commands;

use App\Models\Employee;
use Illuminate\Console\Command;

class ListEmployeesCommand extends Command
{
    protected $signature = 'app:list-employees';
    protected $description = 'Listar todos los empleados';

    public function handle()
    {
        $this->info('👥 Lista de Empleados:');
        $this->newLine();

        $employees = Employee::select('id', 'name', 'last_name', 'is_active', 'created_at')->get();

        if ($employees->isEmpty()) {
            $this->warn('No se encontraron empleados');
            return;
        }

        foreach ($employees as $employee) {
            $status = $employee->is_active ? '✅ Activo' : '❌ Inactivo';
            $years = \Carbon\Carbon::parse($employee->created_at)->diffInYears(\Carbon\Carbon::now());

            $this->info("ID: {$employee->id} | {$employee->name} {$employee->last_name} | {$status} | Antigüedad: {$years} años");
        }

        $this->newLine();
        $this->info("Total empleados: {$employees->count()}");
    }
}
