<?php

declare(strict_types=1);

namespace App\Http\Resources\Inventory;

use App\Enums\CategoryTypeEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryManagementResource extends JsonResource
{
    /**
     * Transformar el recurso en un arreglo.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $rawType = $this->type instanceof CategoryTypeEnum
            ? $this->type->value
            : (string) ($this->type ?: CategoryTypeEnum::INVENTORY->value);

        $enumType = CategoryTypeEnum::tryFrom($rawType) ?? CategoryTypeEnum::INVENTORY;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $enumType->value,
            'type_label' => $enumType->label(),
            'products_count' => (int) ($this->products_count ?? 0),
            'dishes_count' => (int) ($this->dishes_count ?? 0),
        ];
    }
}
