<?php

namespace App\Services\Restaurant;

use App\Contracts\PreBillServiceInterface;
use App\Enums\DiningTableStatusEnum;
use App\Models\GeneralSetting;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PreBillService implements PreBillServiceInterface
{
    public function generatePreBill(Order $order, float $servicePercentage = 10.0): array
    {
        return DB::transaction(function () use ($order, $servicePercentage) {
            $order->load(['details.dish', 'diningTable', 'seller', 'client']);

            $subtotal = (float) $order->total_amount;
            $subtotalUsd = (float) $order->total_amount_usd;

            // Cálculo de propina / servicio sugerido
            $serviceAmount = round(($subtotal * $servicePercentage) / 100, 2);
            $serviceAmountUsd = round(($subtotalUsd * $servicePercentage) / 100, 2);

            $grandTotal = $subtotal + $serviceAmount;
            $grandTotalUsd = $subtotalUsd + $serviceAmountUsd;

            // Actualizar la orden con el monto de servicio y timestamp de impresión
            $order->update([
                'service_charge_percentage' => $servicePercentage,
                'service_charge_amount' => $serviceAmount,
                'service_charge_amount_usd' => $serviceAmountUsd,
                'pre_bill_printed_at' => Carbon::now(),
            ]);

            // Actualizar estado de la mesa a 'bill_requested' (cuenta solicitada)
            if ($order->diningTable) {
                $order->diningTable->update([
                    'status' => DiningTableStatusEnum::BILL_REQUESTED,
                ]);
            }

            $settings = GeneralSetting::first();

            return [
                'order_id' => $order->id,
                'table_name' => $order->diningTable?->name ?? 'Sin Mesa',
                'waiter_name' => $order->seller?->name ?? $order->seller?->username ?? 'Mesero',
                'client_name' => $order->client?->name ?? 'Consumidor Final',
                'client_doc' => $order->client?->id_number ?? '',
                'guests_count' => $order->guests_count ?? 1,
                'date' => Carbon::now()->format('d/m/Y H:i:s'),
                'restaurant_name' => $settings?->name_company ?? 'Restaurant ERP',
                'restaurant_rif' => $settings?->rif ?? '',
                'items' => $order->details->map(function ($detail) {
                    return [
                        'id' => $detail->id,
                        'name' => $detail->dish?->name ?? ($detail->notes ?: 'Ítem'),
                        'quantity' => (float) $detail->quantity,
                        'unit_price' => (float) $detail->price,
                        'unit_price_usd' => (float) $detail->unit_price_usd,
                        'subtotal' => (float) ($detail->quantity * $detail->price),
                        'subtotal_usd' => (float) ($detail->quantity * $detail->unit_price_usd),
                        'seat_number' => $detail->seat_number,
                    ];
                })->values()->toArray(),
                'subtotal' => $subtotal,
                'subtotal_usd' => $subtotalUsd,
                'service_percentage' => $servicePercentage,
                'service_amount' => $serviceAmount,
                'service_amount_usd' => $serviceAmountUsd,
                'grand_total' => $grandTotal,
                'grand_total_usd' => $grandTotalUsd,
                'per_guest_usd' => ($order->guests_count > 0) ? round($grandTotalUsd / $order->guests_count, 2) : $grandTotalUsd,
            ];
        });
    }

    public function cancelPreBill(Order $order): bool
    {
        return DB::transaction(function () use ($order) {
            $order->update([
                'pre_bill_printed_at' => null,
                'service_charge_amount' => 0,
                'service_charge_amount_usd' => 0,
            ]);

            if ($order->diningTable) {
                $order->diningTable->update([
                    'status' => DiningTableStatusEnum::OCCUPIED,
                ]);
            }

            return true;
        });
    }
}
