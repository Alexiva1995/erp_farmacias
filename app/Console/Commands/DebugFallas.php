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
        try {
            $this->info("1. Probando vista individual con stock=all y stockout_adjusted_rop_plus...");
            $res1 = $service->getFilteredReportWithPaginate([
                'stock' => 'all',
                'tipo_filtracion' => 'stockout_adjusted_rop_plus',
                'lapso_de_tiempo' => '1 month',
                'page' => 1,
                'itemsPerPage' => 5
            ]);
            $this->info("   -> Éxito Individual! Items devueltos: " . $res1->count() . " / Total: " . $res1->total());

            $this->info("2. Probando vista individual con stock=fallas...");
            $res2 = $service->getFilteredReportWithPaginate([
                'stock' => 'fallas',
                'tipo_filtracion' => 'stockout_adjusted_rop_plus',
                'lapso_de_tiempo' => '1 month',
                'page' => 1,
                'itemsPerPage' => 5
            ]);
            $this->info("   -> Éxito Fallas! Items devueltos: " . $res2->count() . " / Total: " . $res2->total());

            $this->info("3. Probando vista grupal con stock=fallas página 1 y página 2...");
            $res3_p1 = $service->getGroupedReportWithPaginate([
                'stock' => 'fallas',
                'tipo_filtracion' => 'stockout_adjusted_rop',
                'tipo_vista' => true,
                'lapso_de_tiempo' => '1 month',
                'page' => 1,
                'itemsPerPage' => 5
            ]);
            $this->info("   -> Éxito Grupos P1! Grupos devueltos: " . count($res3_p1['grupos']) . " / Total grupos: " . $res3_p1['total_grupos']);

            $res3_p2 = $service->getGroupedReportWithPaginate([
                'stock' => 'fallas',
                'tipo_filtracion' => 'stockout_adjusted_rop',
                'tipo_vista' => true,
                'lapso_de_tiempo' => '1 month',
                'page' => 2,
                'itemsPerPage' => 5
            ]);
            $this->info("   -> Éxito Grupos P2! Grupos devueltos: " . count($res3_p2['grupos']) . " / Total grupos: " . $res3_p2['total_grupos']);

            $this->info("TODAS LAS PRUEBAS PASARON CORRECTAMENTE.");
        } catch (\Throwable $e) {
            $this->error("ERROR: " . $e->getMessage());
            $this->line("File: " . $e->getFile() . ':' . $e->getLine());
        }
    }
}
