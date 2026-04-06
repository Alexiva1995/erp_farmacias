<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Reports\IaAssistantReportService;

class DebugFallas extends Command
{
    protected $signature = 'app:debug-fallas';
    protected $description = 'Debug 500 error in IaAssistant';

    public function handle(IaAssistantReportService $service)
    {
        $filtros = [
            "tipo_de_filtracion" => "combinado",
            "tipo_vista"         => true,
            "lapso_de_tiempo" => "1 month",
            "groups"          => [2], // The filter by group that user mentioned
            "page"            => 1,
            "itemsPerPage"    => 25
        ];

        try {
            $report = $service->getGroupedReportWithPaginate($filtros);
            $this->info("Groups populated: " . count($report['grupos']));
        } catch (\Exception $e) {
            $this->error("ERROR CAUGHT: " . $e->getMessage());
            $this->line($e->getFile() . ':' . $e->getLine());
        }
    }
}
