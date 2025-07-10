<?php

namespace App\Services\Pdf;

use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

class PdfGenerationService
{
    /**
     * Genera el HTML para una carta de donación.
     *
     * @param string $institutionName
     * @param Carbon $donationDate
     * @param Collection $products
     * @return string
     */
    public function generateDonationLetterHtml(string $institutionName, Carbon $donationDate, Collection $products): string
    {
        $totalCost = $products->sum('total_lost_value');

        return view('pdfs.donation_letter', [
            'institution_name' => $institutionName,
            'donation_date' => $donationDate->format('d/m/Y'),
            'products' => $products,
            'total_cost' => $totalCost,
        ])->render();
    }
}
