<?php

namespace App\Services\Restaurant;

use App\Contracts\SplitCheckServiceInterface;
use App\Models\Order;
use App\Models\OrderDetail;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SplitCheckService implements SplitCheckServiceInterface
{
    public function splitBySeatsOrItems(Order $order, array $splits): array
    {
        return DB::transaction(function () use ($order, $splits) {
            if (empty($splits)) {
                throw new InvalidArgumentException('Se requiere al menos una partición para dividir la cuenta.');
            }

            $order->load('details');
            $createdChildOrders = [];

            // El primer grupo de división puede quedarse en la orden principal, o
            // podemos desacoplar los ítems de los grupos adicionales creando nuevas órdenes pendientes.
            // Para mantener trazabilidad:
            // Cada sub-grupo a partir del índice 1 crea una nueva orden hija con parent_order_id = $order->id.
            foreach ($splits as $index => $splitGroup) {
                $detailIds = $splitGroup['detail_ids'] ?? [];
                if (empty($detailIds)) {
                    continue;
                }

                if ($index === 0) {
                    // El primer grupo se queda en la orden original, solo verificamos o etiquetamos
                    $createdChildOrders[] = $order;
                } else {
                    // Crear orden hija
                    $childOrder = Order::create([
                        'client_id' => $order->client_id,
                        'seller_id' => $order->seller_id,
                        'order_date' => now(),
                        'status' => Order::PENDING,
                        'currency' => $order->currency,
                        'total_amount' => 0,
                        'total_amount_usd' => 0,
                        'total_cost' => 0,
                        'money_returns' => 0,
                        'usd_conversion' => $order->usd_conversion,
                        'dining_table_id' => $order->dining_table_id,
                        'parent_order_id' => $order->id,
                        'guests_count' => 1,
                    ]);

                    // Mover los detalles seleccionados a la orden hija
                    OrderDetail::whereIn('id', $detailIds)
                        ->where('order_id', $order->id)
                        ->update(['order_id' => $childOrder->id]);

                    $childOrder->recalculateTotals();
                    $createdChildOrders[] = $childOrder;
                }
            }

            // Recalcular los totales de la orden padre original
            $order->recalculateTotals();

            return $createdChildOrders;
        });
    }

    public function splitEqually(Order $order, int $parts): array
    {
        if ($parts < 2) {
            throw new InvalidArgumentException('El número de divisiones iguales debe ser de al menos 2.');
        }

        $totalAmount = (float) $order->total_amount;
        $totalUsd = (float) $order->total_amount_usd;
        $serviceAmount = (float) ($order->service_charge_amount ?? 0);
        $serviceAmountUsd = (float) ($order->service_charge_amount_usd ?? 0);

        $grandTotal = $totalAmount + $serviceAmount;
        $grandTotalUsd = $totalUsd + $serviceAmountUsd;

        $perPartTotal = round($grandTotal / $parts, 2);
        $perPartUsd = round($grandTotalUsd / $parts, 2);

        $divisions = [];
        for ($i = 1; $i <= $parts; $i++) {
            $divisions[] = [
                'part_number' => $i,
                'total_parts' => $parts,
                'order_id' => $order->id,
                'amount' => $perPartTotal,
                'amount_usd' => $perPartUsd,
                'currency' => $order->currency,
            ];
        }

        return [
            'original_order_id' => $order->id,
            'parts_count' => $parts,
            'total_amount' => $grandTotal,
            'total_amount_usd' => $grandTotalUsd,
            'divisions' => $divisions,
        ];
    }
}
