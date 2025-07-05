<?php

namespace App\Services\Donation;

use App\Models\Donation;
use App\Models\DonativeLog;
use App\Services\Resources\ResourceService;
use Illuminate\Support\Facades\DB;

class DonationService
{
    public function __construct(ResourceService $resourceService)
    {
        $this->resourceService = $resourceService;
    }
    /**
     * Registra una nueva donación y sus productos asociados.
     */

    public function recordDonation(array $data): Donation
    {
        return DB::transaction(function () use ($data) {
            $donation = Donation::create([
                'institution_name' => $data['institution_name'],
            ]);

            $donativeLogsData = [];
            foreach ($data['expired_log_ids'] as $expiredLogId) {
                $donativeLogsData[] = [
                    'donation_id' => $donation->id,
                    'expired_log_id' => $expiredLogId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DonativeLog::insert($donativeLogsData);

            return $donation;
        });
    }

    /**
     * Obtiene los datos para el PDF de la donación de un mes.
     */
    public function getMonthlyDonationData(string $month): ?object
    {
        $year = substr($month, 0, 4);
        $monthNum = substr($month, 5, 2);

        $donation = Donation::whereYear('created_at', $year)
            ->whereMonth('created_at', $monthNum)
            ->with('donativeLogs.expiredLog.product')
            ->first();

        if (!$donation) {
            return null;
        }

        // Obtener la tasa de cambio para bolívares
        $bsRate = $this->resourceService->getExchangeRate('BS');

        $products = $donation->donativeLogs->map(function ($donativeLog) use ($bsRate) {
            $expiredLog = $donativeLog->expiredLog;
            if ($expiredLog && $expiredLog->product) {
                // Agregar precios en bolívares
                $costPerUnit = $expiredLog->product->sale_price ?? 0;
                $expiredLog->cost_per_unit_bs = round($costPerUnit * $bsRate, 2);
                $expiredLog->total_lost_value_bs = round($expiredLog->total_lost_value * $bsRate, 2);
            }
            return $expiredLog;
        })->filter();

        return (object) [
            'institution_name' => $donation->institution_name,
            'donation_date' => $donation->created_at,
            'products' => $products,
            'total_cost' => $products->sum('total_lost_value'),
            'total_cost_bs' => $products->sum('total_lost_value_bs'),
            'exchange_rate_bs' => $bsRate, // Incluir la tasa de cambio
        ];
    }
}
