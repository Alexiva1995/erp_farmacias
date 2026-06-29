<?php

namespace App\Console\Commands;

use App\Services\Identity\CNEQueryService;
use Illuminate\Console\Command;

class VerifyClientsCneCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:verify-clients-cne';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verificar identidades de clientes vía CNE de forma automática';

    /**
     * Execute the console command.
     */
    public function handle(CNEQueryService $cneService)
    {
        $this->info("Iniciando verificación masiva programada...");

        $results = $cneService->verifyBatch(100);

        $this->info("Proceso completado.");
        $this->info("Actualizados: {$results['updated']}");
        $this->info("No encontrados: {$results['not_found']}");
    }
}
