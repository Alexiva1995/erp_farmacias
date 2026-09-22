<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierConnectionStatusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $details = $this->details ?? [];
        $invoices = $details['invoices'] ?? [];

        if (!empty($invoices) && is_array($invoices)) {
            $invoiceNumbers = array_values(array_filter(array_map(function($inv) {
                return trim((string)($inv['invoice_number'] ?? ''));
            }, $invoices)));

            $dbInvoices = [];
            if (!empty($invoiceNumbers) && $this->supplier_id) {
                $dbInvoices = \App\Models\Invoice::where('supplier_id', $this->supplier_id)
                    ->whereIn('invoice_number', $invoiceNumbers)
                    ->get()
                    ->keyBy(function($item) {
                        return strtoupper(trim((string)$item->invoice_number));
                    });
            }

            foreach ($invoices as &$inv) {
                $num = strtoupper(trim((string)($inv['invoice_number'] ?? '')));
                if (isset($dbInvoices[$num])) {
                    $dbInv = $dbInvoices[$num];
                    if (empty($inv['total_usd']) || floatval($inv['total_usd']) == 0) {
                        $inv['total_usd'] = floatval($dbInv->total_usd ?? 0);
                    }
                    if (empty($inv['total_amount']) || floatval($inv['total_amount']) == 0) {
                        $inv['total_amount'] = floatval($dbInv->total_amount ?? 0);
                    }
                    if (empty($inv['control_number']) || $inv['control_number'] === '—' || $inv['control_number'] === 'S/N') {
                        $inv['control_number'] = $dbInv->control_number ?: '—';
                    }
                    if (empty($inv['date']) || $inv['date'] === '—') {
                        $inv['date'] = optional($dbInv->created_invoice_date)->format('d/m/Y') ?? optional($dbInv->created_at)->format('d/m/Y');
                    }
                }
            }
            unset($inv);

            // Ordenar por fecha más pronta de creación (más reciente primero)
            usort($invoices, function($a, $b) {
                $dateA = !empty($a['date']) ? strtotime(str_replace('/', '-', $a['date'])) : 0;
                $dateB = !empty($b['date']) ? strtotime(str_replace('/', '-', $b['date'])) : 0;
                if ($dateA == $dateB) {
                    return strcmp($b['invoice_number'] ?? '', $a['invoice_number'] ?? '');
                }
                return $dateB <=> $dateA;
            });

            $details['invoices'] = $invoices;
        }

        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'user_id' => $this->user_id,
            'user_name' => $this->user?->username ?? 'Sistema',
            'status' => $this->status,
            'message' => $this->message,
            'count_product' => (int) ($this->count_product ?? 0),
            'count_invoice' => (int) ($this->count_invoice ?? 0),
            'details' => $details,
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_formatted' => $this->created_at ? $this->created_at->format('d/m/Y h:i A') : '—',
        ];
    }
}

