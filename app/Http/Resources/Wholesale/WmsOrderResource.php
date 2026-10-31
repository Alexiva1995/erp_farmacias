<?php

declare(strict_types=1);

namespace App\Http\Resources\Wholesale;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WmsOrderResource extends JsonResource
{
    /**
     * Transforma la orden WMS y sus detalles para el tablero de almacén.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $detailsCollection = ($this->relationLoaded('details') ? $this->details : collect())->map(function ($detail) {
            $lotData = $detail->relationLoaded('lot') && $detail->lot ? [
                'id' => (int) $detail->lot->id,
                'lot_number' => (string) $detail->lot->lot_number,
                'expiration_date' => $detail->lot->expiration_date ? $detail->lot->expiration_date->format('Y-m-d') : 'N/A',
                'quantity' => (float) $detail->lot->quantity,
            ] : null;

            $productData = $detail->relationLoaded('product') && $detail->product ? [
                'id' => (int) $detail->product->id,
                'name' => (string) $detail->product->name,
                'barcode' => (string) ($detail->product->barcode ?? 'S/C'),
            ] : null;

            $itemData = [
                'id' => (int) $detail->id,
                'order_detail_id' => (int) $detail->id,
                'order_id' => (int) $detail->order_id,
                'product_id' => (int) $detail->product_id,
                'product_name' => $detail->product?->name ?? "Producto #{$detail->product_id}",
                'product' => $productData,
                'quantity' => (float) $detail->quantity,
                'picked_quantity' => (float) ($detail->picked_quantity ?? $detail->quantity),
                'checked_quantity' => (float) ($detail->checked_quantity ?? $detail->picked_quantity ?? $detail->quantity),
                'credit_days_applied' => (int) ($detail->credit_days_applied ?? 0),
                'product_lot_id' => $detail->product_lot_id ? (int) $detail->product_lot_id : null,
                'product_lot' => $lotData,
                'lot' => $lotData,
            ];

            return $itemData;
        })->values()->all();

        $clientData = $this->relationLoaded('client') && $this->client ? [
            'id' => (int) $this->client->id,
            'name' => (string) $this->client->name,
            'identification' => (string) ($this->client->identification ?? ''),
            'identification_number' => (string) ($this->client->identification ?? ''),
            'delivery_route_id' => $this->client->delivery_route_id ? (int) $this->client->delivery_route_id : null,
        ] : null;

        $deliveryRouteData = $this->relationLoaded('deliveryRoute') && $this->deliveryRoute ? [
            'id' => (int) $this->deliveryRoute->id,
            'code' => (string) $this->deliveryRoute->code,
            'name' => (string) $this->deliveryRoute->name,
        ] : null;

        $pickerData = $this->relationLoaded('picker') && $this->picker ? [
            'id' => (int) $this->picker->id,
            'name' => (string) $this->picker->name,
        ] : null;

        $checkerData = $this->relationLoaded('checker') && $this->checker ? [
            'id' => (int) $this->checker->id,
            'name' => (string) $this->checker->name,
        ] : null;

        $totalUsd = (float) ($this->total_amount_usd ?: $this->total_amount ?: 0.0);

        return [
            'id' => (int) $this->id,
            'order_date' => $this->order_date ? $this->order_date->toISOString() : null,
            'status' => (string) $this->status,
            'b2b_workflow_status' => (string) ($this->b2b_workflow_status ?? 'draft'),
            'credit_term_days' => (int) ($this->credit_term_days ?? 0),
            'parent_b2b_order_id' => $this->parent_b2b_order_id ? (int) $this->parent_b2b_order_id : null,
            'delivery_route_id' => $this->delivery_route_id ? (int) $this->delivery_route_id : null,
            'packages_count' => (int) ($this->packages_count ?? 0),
            'cold_boxes_count' => (int) ($this->cold_boxes_count ?? 0),
            'assigned_picker_id' => $this->assigned_picker_id ? (int) $this->assigned_picker_id : null,
            'assigned_checker_id' => $this->assigned_checker_id ? (int) $this->assigned_checker_id : null,
            'total' => $totalUsd,
            'total_amount' => $totalUsd,
            'client' => $clientData,
            'delivery_route' => $deliveryRouteData,
            'picker' => $pickerData,
            'checker' => $checkerData,
            'details' => $detailsCollection,
            'order_details' => $detailsCollection,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
