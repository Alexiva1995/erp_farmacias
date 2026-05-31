<?php

namespace App\Http\Resources\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SoldExpiringProductResource extends JsonResource
{
    /**
     * Nombre del envoltorio del recurso.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * Transforma el recurso en un array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lot = $this->productLot;
        
        if (!$lot) {
            $startOfMonth = \Carbon\Carbon::now()->startOfMonth()->toDateString();
            $endOfMonth = \Carbon\Carbon::now()->endOfMonth()->toDateString();
            $lot = \App\Models\ProductLot::where('product_id', $this->product_id)
                ->whereBetween('expiration_date', [$startOfMonth, $endOfMonth])
                ->first();
        }

        return [
            'id' => $this->id,
            'product_name' => $this->product?->name ?? 'Producto Desconocido',
            'laboratory_name' => $this->product?->laboratory?->name ?? 'Sin Laboratorio',
            'lot_number' => $lot ? $lot->lot_number : 'S/L',
            'expiration_date' => $lot && $lot->expiration_date ? $lot->expiration_date->format('Y-m-d') : null,
            'quantity' => abs((int) $this->quantity),
            'sold_date' => $this->movement_date ?? $this->created_at?->toDateTimeString(),
            'user_name' => $this->user?->employee
                ? trim($this->user->employee->name . ' ' . $this->user->employee->last_name)
                : ($this->user?->username ?? 'Usuario de Ventas'),
        ];
    }
}
