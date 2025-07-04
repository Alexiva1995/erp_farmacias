<?php

namespace App\Services\Donation;

use App\Models\Donation;
use App\Models\DonativeLog;
use Illuminate\Support\Facades\DB;

class DonationService
{
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
        $products = $donation->donativeLogs->map(function ($donativeLog) {
            return $donativeLog->expiredLog;
        })->filter();
        return (object) [
            'institution_name' => $donation->institution_name,
            'donation_date' => $donation->created_at,
            'products' => $products,
            'total_cost' => $products->sum('total_lost_value'),
        ];
    }
}
