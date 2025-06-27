<?php
namespace App\Services\Donation;

use App\Models\DonativeLog;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class DonationService
{
    public function recordDonation(array $data): void
    {
        DB::transaction(function () use ($data) {
            $batchUuid = Str::uuid();

            foreach ($data['expired_log_ids'] as $expiredLogId) {
                DonativeLog::create([
                    'donation_batch_uuid' => $batchUuid,
                    'institution_name' => $data['institution_name'],
                    'expired_log_id' => $expiredLogId,
                ]);
            }
        });
    }
}
