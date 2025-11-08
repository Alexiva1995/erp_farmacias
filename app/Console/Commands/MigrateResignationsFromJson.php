<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Resignation;
use App\Models\Employee;
use Illuminate\Support\Facades\Storage;

class MigrateResignationsFromJson extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'resignations:migrate-from-json';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrar datos de renuncias desde JSON a base de datos';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando migración de datos de renuncias...');
        
        $jsonFile = storage_path('app/resignations.json');
        
        if (!file_exists($jsonFile)) {
            $this->warn('No existe archivo JSON de renuncias. Creando tabla vacía.');
            $this->info('La tabla resignations está lista para recibir nuevos datos.');
            return;
        }
        
        $jsonData = json_decode(file_get_contents($jsonFile), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Error al leer archivo JSON: ' . json_last_error_msg());
            return;
        }
        
        $this->info('Encontrados ' . count($jsonData) . ' registros en JSON');
        
        $migrated = 0;
        $errors = 0;
        $skipped = 0;
        
        foreach ($jsonData as $resignationData) {
            try {
                // Verificar que el empleado existe
                $employee = Employee::find($resignationData['employee_id']);
                if (!$employee) {
                    $this->warn("Empleado ID {$resignationData['employee_id']} no existe, saltando...");
                    $skipped++;
                    continue;
                }
                
                // Verificar si ya existe la renuncia en BD
                $existingResignation = Resignation::where('employee_id', $resignationData['employee_id'])->first();
                if ($existingResignation) {
                    $this->warn("Renuncia para empleado ID {$resignationData['employee_id']} ya existe en BD, saltando...");
                    $skipped++;
                    continue;
                }
                
                // Preparar datos para inserción
                $data = [
                    'employee_id' => $resignationData['employee_id'],
                    'employee_name' => $resignationData['employee_name'],
                    'employee_identification' => $resignationData['employee_identification'],
                    'employee_email' => $resignationData['employee_email'] ?? null,
                    'employee_position' => $resignationData['employee_position'] ?? null,
                    'start_date' => $resignationData['start_date'],
                    'resignation_type' => $resignationData['resignation_type'],
                    'request_date' => $resignationData['request_date'],
                    'effective_date' => $resignationData['effective_date'],
                    'employee_status' => $resignationData['employee_status'],
                    'created_at' => $resignationData['created_at'] ?? now(),
                    'updated_at' => $resignationData['updated_at'] ?? now()
                ];
                
                Resignation::create($data);
                $migrated++;
                
                $this->line("✓ Migrado: {$data['employee_name']} (ID: {$data['employee_id']})");
                
            } catch (\Exception $e) {
                $this->error("Error migrando renuncia: " . $e->getMessage());
                $errors++;
            }
        }
        
        $this->info("\n" . str_repeat("=", 50));
        $this->info("Migración completada:");
        $this->info("- Registros migrados: {$migrated}");
        $this->info("- Registros saltados: {$skipped}");
        $this->info("- Errores: {$errors}");
        
        // Crear backup del JSON
        if ($migrated > 0) {
            $backupFile = storage_path('app/resignations_backup_' . date('Y-m-d_H-i-s') . '.json');
            copy($jsonFile, $backupFile);
            $this->info("Backup creado en: {$backupFile}");
        }
        
        if ($migrated > 0) {
            $this->info("\n✅ Migración exitosa! Los datos están ahora en la base de datos.");
            $this->info("💡 Puedes eliminar el archivo JSON original cuando estés seguro de que todo funciona correctamente.");
        } else {
            $this->warn("\n⚠️ No se migraron datos. Verifica el archivo JSON y los empleados existentes.");
        }
    }
}
