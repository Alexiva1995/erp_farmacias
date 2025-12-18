<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Company;
use App\Models\CompanyOffer;
use Carbon\Carbon;
use App\Services\Resources\ResourceService;
use Illuminate\Support\Facades\DB;


class CalculateMonthlyCompanyDiscount extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:calculate-monthly-company-discount';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calcula el descuento del mes de empresas basado en las compras del mes anterior';

    /**
     * Execute the console command.
     */
    public function handle(ResourceService $exchangeService)
    {
        $companies = Company::with(['offers.scales', 'clients'])->get();
        $lastMonth = Carbon::now()->subMonth();
        foreach ($companies as $company) {
            $clientIds = $company->clients->pluck('id');

            if ($clientIds->isEmpty()) {
                $company->update(['current_discount' => 0]);
                continue;
            }

            $rates = $exchangeService->getAllExchangeRate()->pluck('rate', 'currency_code');

            // 1. Obtenemos la suma agrupada por moneda
            $totalesPorMoneda = DB::table('orders')
            ->select('currency', DB::raw('SUM(total_amount) as total'))
            ->whereIn('client_id', $clientIds)
            ->whereMonth('created_at', $lastMonth->month)
            ->whereYear('created_at', $lastMonth->year)
            ->where('status', 'Completed')
            ->groupBy('currency')
            ->get();

            $montoTotalUSD = 0;
            foreach ($totalesPorMoneda as $registro) {
                $tasa = $rates->get($registro->currency, 1.0); //1.0 si no consigue tasa
                $montoTotalUSD += ($registro->total * $tasa);
            }

            $oferta = $company->offers->first();
            $nuevoDescuento = 0;

            if ($oferta) {
                $escalaGanadora = $oferta->scales()
                    ->where('min_amount', '<=', $montoTotalUSD)
                    ->where(function($query) use ($montoTotalUSD) {
                        $query->where('max_amount', '>=', $montoTotalUSD)
                            ->orWhereNull('max_amount');
                    })
                    ->orderBy('min_amount', 'desc') // Tomamos la escala más alta alcanzada
                    ->first();

                $nuevoDescuento = $escalaGanadora ? $escalaGanadora->discount_percentage : 0;
            }

            $company->update([
                'current_discount' => $nuevoDescuento
            ]);
            $this->info("Empresa: {$company->name} | Monto Total: $ {$montoTotalUSD} USD | Descuento: {$nuevoDescuento}%");
        }
        $this->info('Proceso finalizado con éxito.');
    }
}
