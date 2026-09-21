<?php

namespace App\Http\Resources\Fiscal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FiscalZReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $rawNotes = $this->ai_verification_notes;
        $parsedAiData = null;
        $notesText = $rawNotes;
        $extractedData = null;
        $discrepancies = [];
        $discrepanciesMap = [];

        if (!empty($rawNotes)) {
            $decoded = json_decode($rawNotes, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $parsedAiData = $decoded;
                $notesText = $decoded['notes'] ?? '';
                $extractedData = $decoded['extracted_data'] ?? null;
                $discrepancies = $decoded['discrepancies'] ?? [];
                foreach ($discrepancies as $d) {
                    if (isset($d['field'])) {
                        $discrepanciesMap[$d['field']] = $d;
                    }
                }
            }
        }

        return [
            'id'                    => $this->id,
            'report_number'         => $this->report_number,
            'report_number_padded'  => 'Z' . str_pad((string) $this->report_number, 6, '0', STR_PAD_LEFT),
            'report_date'           => $this->report_date ? $this->report_date->format('Y-m-d') : null,
            'opening_time'          => $this->opening_time,
            'closing_time'          => $this->closing_time,
            'first_invoice_number'  => $this->first_invoice_number,
            'last_invoice_number'   => $this->last_invoice_number,
            'invoices_count'        => (int) $this->invoices_count,
            'exempt_amount'         => (float) $this->exempt_amount,
            'base_16_amount'        => (float) $this->base_16_amount,
            'iva_amount'            => (float) $this->iva_amount,
            'igtf_base_amount'      => (float) $this->igtf_base_amount,
            'igtf_amount'           => (float) $this->igtf_amount,
            'total_amount'          => (float) $this->total_amount,
            'status'                => $this->status,
            'image_path'            => $this->image_path,
            'image_url'             => $this->image_path ? asset('storage/' . $this->image_path) : null,
            'ai_verification_notes' => $notesText,
            'ai_verification_data'  => $parsedAiData,
            'extracted_data'        => $extractedData,
            'discrepancies'         => $discrepancies,
            'discrepancies_map'     => (object) $discrepanciesMap,
            'created_at'            => $this->created_at?->toISOString(),
            'updated_at'            => $this->updated_at?->toISOString(),
        ];
    }
}
