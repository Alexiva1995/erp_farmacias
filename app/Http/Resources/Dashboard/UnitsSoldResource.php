<?php

namespace App\Http\Resources\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitsSoldResource extends JsonResource
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
        return [
            'units' => (int) $this->resource,
        ];
    }
}
