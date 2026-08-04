<?php

namespace App\Console\Commands;

use App\Jobs\GenerateAutomaticSocialBenefitsJob;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ScheduleAutomaticSocialBenefitsCommand extends Command
{
    protected $signature = 'app:schedule-automatic-social-benefits';
    protected $description = 'Programa la generación automática de prestaciones sociales según las reglas de negocio';

    public function handle()
    {
        $this->info("📅 Programando generación automática de prestaciones sociales...");

        // Verificar si es 16 de diciembre para utilidades
        if (Carbon::now()->format('m-d') === '12-16') {
            $this->info("💰 Programando generación de utilidades (16 de diciembre)");
            GenerateAutomaticSocialBenefitsJob::dispatch('utilities');
        } else {
            $this->info("⏰ No es 16 de diciembre, saltando generación de utilidades");
        }

        // Siempre verificar vacaciones (empleados que cumplieron 1 año)
        $this->info("🏖️ Verificando empleados para generación de vacaciones");
        GenerateAutomaticSocialBenefitsJob::dispatch('vacations');


        $this->info("✅ Programación completada");
    }
}
