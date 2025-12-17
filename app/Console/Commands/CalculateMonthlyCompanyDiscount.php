<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Company;
use App\Models\CompanyOffer;
use Carbon\Carbon;
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
    public function handle()
    {
        $companies = Company::with(['offers.scales', 'clients'])->get();
        $lastMonth = Carbon::now()->subMonth();
        foreach ($companies as $company) {
            /*$totalComprado = DB::table('orders')
                ->whereIn('client_id', $company->clients()->pluck('id'))
                ->whereMonth('created_at', $lastMonth->month)
                ->whereYear('created_at', $lastMonth->year)
                ->sum('total_amount');*/
            $clientIds = $company->clients->pluck('id');

            if ($clientIds->isEmpty()) {
                $company->update(['current_discount' => 0]);
                continue;
            }

            $volumenTotal = DB::table('order_details') 
                ->join('orders', 'order_details.order_id', '=', 'orders.id')
                ->whereIn('orders.client_id', $clientIds)
                ->whereMonth('orders.created_at', $lastMonth->month)
                ->whereYear('orders.created_at', $lastMonth->year)
                ->where('orders.status', 'Completed') 
                ->sum('order_details.quantity');

            $oferta = $company->offers->first();
            $nuevoDescuento = 0;

            if ($oferta) {
                $escalaGanadora = $oferta->scales()
                ->where('min_volume', '<=', $volumenTotal)
                ->where(function($query) use ($volumenTotal) {
                    $query->where('max_volume', '>=', $volumenTotal)
                        ->orWhereNull('max_volume');
                })
                ->orderBy('min_volume', 'desc')
                ->first();

                $nuevoDescuento = $escalaGanadora ? $escalaGanadora->discount_percentage : 0;
            }

            $company->update([
                'current_discount' => $nuevoDescuento
            ]);
            $this->info("Empresa: {$company->name} | Volumen: {$volumenTotal} | Descuento: {$nuevoDescuento}%");
        }
        $this->info('Proceso finalizado con éxito.');
    }
}
