<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DishRecalculationResource extends JsonResource
{
    /**
     * Transformar el resultado del recálculo masivo de platos en un arreglo.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'removed_ingredients_count' => (int) ($this['removed_ingredients_count'] ?? 0),
            'recalculated_dishes_count' => (int) ($this['recalculated_dishes_count'] ?? 0),
            'message' => (string) ($this['message'] ?? 'Costos fijos e imprevistos eliminados y costos recalculados con éxito.'),
        ];
    }
}
