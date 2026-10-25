<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KdsTicketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dining_table_name' => $this->diningTable?->name ?? 'Salón',
            'client_name' => $this->client?->name ?? 'Cliente General',
            'seller_name' => $this->seller?->name ?? 'Mesonero / Caja',
            'order_date' => $this->order_date?->format('Y-m-d H:i:s'),
            'status' => $this->status,
            'items' => $this->details->map(function ($detail) {
                return [
                    'id' => $detail->id,
                    'dish_id' => $detail->dish_id,
                    'dish_name' => $detail->dish?->name ?? 'Plato sin nombre',
                    'category_name' => $detail->dish?->category?->name,
                    'quantity' => (float) $detail->quantity,
                    'kitchen_status' => $detail->kitchen_status instanceof \App\Enums\KitchenStatusEnum 
                        ? $detail->kitchen_status->value 
                        : ($detail->kitchen_status ?? 'draft'),
                    'kitchen_status_label' => $detail->kitchen_status instanceof \App\Enums\KitchenStatusEnum 
                        ? $detail->kitchen_status->label() 
                        : 'En Cola',
                    'kitchen_status_color' => $detail->kitchen_status instanceof \App\Enums\KitchenStatusEnum 
                        ? $detail->kitchen_status->color() 
                        : 'info',
                    'course_number' => (int) ($detail->course_number ?? 1),
                    'course_label' => match ((int) ($detail->course_number ?? 1)) {
                        1 => '1° Tiempo (Entrada)',
                        2 => '2° Tiempo (Fuerte)',
                        3 => '3° Tiempo (Postre)',
                        default => '1° Tiempo',
                    },
                    'notes' => $detail->notes,
                    'preparation_time_minutes' => (int) ($detail->dish?->preparation_time_minutes ?? 15),
                    'kitchen_station' => $detail->dish?->kitchenStation ? [
                        'id' => $detail->dish->kitchenStation->id,
                        'name' => $detail->dish->kitchenStation->name,
                        'code' => $detail->dish->kitchenStation->code,
                        'color' => $detail->dish->kitchenStation->color,
                        'icon' => $detail->dish->kitchenStation->icon,
                    ] : null,
                    'commanded_at' => $detail->commanded_at?->format('Y-m-d H:i:s'),
                    'prepared_at' => $detail->prepared_at?->format('Y-m-d H:i:s'),
                    'modifiers' => $detail->modifiers->map(function ($mod) {
                        return [
                            'id' => $mod->id,
                            'name' => $mod->name,
                            'modifier_type' => $mod->modifier_type instanceof \App\Enums\ModifierTypeEnum ? $mod->modifier_type->value : $mod->modifier_type,
                            'extra_price_usd' => (float) $mod->extra_price_usd,
                        ];
                    }),
                    'removable_ingredients' => $detail->dish?->ingredients ? $detail->dish->ingredients->filter(function ($ing) {
                        return (bool) ($ing->pivot?->is_removable ?? false);
                    })->map(function ($ing) {
                        return [
                            'id' => $ing->id,
                            'name' => $ing->name,
                        ];
                    })->values() : [],
                ];
            }),
        ];
    }
}
