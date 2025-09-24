<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ResignationService
{
    public function generatePdfData($resignationData)
    {
        return [
            'employee_name' => $resignationData['employee_name'],
            'employee_identification' => $resignationData['employee_identification'],
            'employee_position' => $resignationData['employee_position'] ?? 'empleado',
            'start_date_formatted' => $this->formatDate($resignationData['start_date']),
            'effective_date_formatted' => $this->formatDate($resignationData['effective_date']),
        ];
    }

    public function generatePdf($resignationData)
    {
        $pdfData = $this->generatePdfData($resignationData);

        $pdf = Pdf::loadView('pdf.resignation-letter', $pdfData);
        $pdf->setPaper('A4', 'portrait');

        return $pdf;
    }

    public function downloadPdf($resignationData)
    {
        $pdf = $this->generatePdf($resignationData);
        $filename = 'carta-renuncia-' . $resignationData['employee_identification'] . '.pdf';

        return $pdf->download($filename);
    }

    private function formatDate($date)
    {
        return Carbon::parse($date)->format('d/m/Y');
    }

    public function notifyLiquidation($resignationData)
    {
        // TODO: Implementar notificación a Jesús Freita
        // Por ahora solo log
        \Illuminate\Support\Facades\Log::info('Notificación de liquidación enviada', $resignationData);
    }
}
