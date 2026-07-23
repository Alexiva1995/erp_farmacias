<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class MigrateModular extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'db:migrate-modular {--force : Forzar la ejecución en producción}';

    /**
     * The console command description.
     */
    protected $description = 'Ejecuta las migraciones de base de datos filtrando según el tipo de negocio configurado';

    /**
     * Mapeo de patrones de nombres de archivos que pertenecen a módulos específicos.
     */
    protected array $modulePatterns = [
        'restaurant' => [
            'dishes',
            'dish_ingredients',
            'process_audits',
            'process_flows',
            'employee_dish'
        ],
        'reservation' => [
            'courts',
            'fixed_schedules',
            'reservations',
            'booking_visits',
            'fixed_schedule_exceptions'
        ],
        'lottery' => [
            'lottery'
        ],
        'pharmacy' => [
            'doctors',
            'doctor_offers',
            'specialties',
            'expired_logs',
            'expirations',
            'expiration_offers',
            'product_lots',
            'psychotropic_controls'
        ]
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $businessType = env('BUSINESS_TYPE', 'pharmacy');
        $enabledModulesString = env('ENABLED_MODULES', 'pharmacy');
        $enabledModules = array_map('trim', explode(',', strtolower($enabledModulesString)));

        $this->info("Iniciando migración modular...");
        $this->info("Tipo de Negocio Activo: " . strtoupper($businessType));
        $this->info("Módulos Habilitados: " . implode(', ', $enabledModules));

        $migrationFiles = File::files(database_path('migrations'));
        $pathsToMigrate = [];

        foreach ($migrationFiles as $file) {
            $filename = $file->getFilename();
            $shouldInclude = true;

            // Evaluar contra los patrones de módulos deshabilitados
            foreach ($this->modulePatterns as $moduleName => $patterns) {
                if (!in_array($moduleName, $enabledModules)) {
                    foreach ($patterns as $pattern) {
                        if (str_contains($filename, $pattern)) {
                            $shouldInclude = false;
                            break 2; // Rompe ambos bucles si coincide con un módulo excluido
                        }
                    }
                }
            }

            if ($shouldInclude) {
                // Ruta relativa desde la raíz del proyecto para artisan migrate
                $pathsToMigrate[] = 'database/migrations/' . $filename;
            }
        }

        if (empty($pathsToMigrate)) {
            $this->warn("No se encontraron archivos de migración válidos para ejecutar.");
            return Command::SUCCESS;
        }

        $this->info("Se ejecutarán " . count($pathsToMigrate) . " migraciones de " . count($migrationFiles) . " disponibles.");

        // Construir y ejecutar el comando artisan migrate nativo
        $parameters = [
            '--path' => $pathsToMigrate,
        ];

        if ($this->option('force')) {
            $parameters['--force'] = true;
        }

        $exitCode = Artisan::call('migrate', $parameters, $this->output);

        if ($exitCode === 0) {
            $this->info("Migración modular completada con éxito.");
        } else {
            $this->error("Ocurrió un error al ejecutar las migraciones.");
        }

        return $exitCode;
    }
}
