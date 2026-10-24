<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class RunTenantSeederCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:run-seeder 
                            {tenant : El ID o subdominio del tenant} 
                            {seeder? : La clase del seeder a ejecutar como argumento posicional} 
                            {--class= : La clase del seeder a ejecutar como opcion}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ejecuta un seeder específico dentro de la base de datos de un tenant aislado.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantId = (string) $this->argument('tenant');
        $seederClass = (string) ($this->argument('seeder') ?: ($this->option('class') ?: 'Database\\Seeders\\RestaurantDemoSeeder'));

        $tenant = Tenant::find($tenantId) ?? Tenant::where('id', 'like', "%{$tenantId}%")->first();

        if (!$tenant) {
            $this->error("No se encontró el tenant con identificador: {$tenantId}");
            return self::FAILURE;
        }

        $this->info("Conectando al tenant: {$tenant->id}...");

        if (!class_exists($seederClass)) {
            $namespaced = "Database\\Seeders\\{$seederClass}";
            if (class_exists($namespaced)) {
                $seederClass = $namespaced;
            } else {
                $this->error("La clase del seeder [{$seederClass}] no existe.");
                return self::FAILURE;
            }
        }

        $tenant->run(function () use ($seederClass) {
            $this->line("Ejecutando seeder: {$seederClass} en la base de datos del tenant...");
            $seederInstance = app($seederClass);
            $seederInstance->run();
        });

        $this->info("¡Seeder ejecutado con éxito en el tenant {$tenant->id}!");
        return self::SUCCESS;
    }
}
