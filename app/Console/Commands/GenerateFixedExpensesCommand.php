<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\FixedExpenses;
use Illuminate\Console\Command;

class GenerateFixedExpensesCommand extends Command
{
    /**
     * El nombre y la firma del comando de consola.
     *
     * @var string
     */
    protected $signature = 'app:generate-fixed-expenses';

    /**
     * Descripción del comando de consola.
     *
     * @var string
     */
    protected $description = 'Genera las cuotas por pagar de gastos fijos según su frecuencia y días de anticipación';

    /**
     * Ejecuta el comando de consola.
     */
    public function handle(FixedExpenses $service): int
    {
        $this->info('Iniciando generación de cuotas de gastos fijos...');

        $count = $service->generateDueOccurrences();

        $this->info("Proceso finalizado. Cuotas generadas: {$count}");

        return self::SUCCESS;
    }
}
