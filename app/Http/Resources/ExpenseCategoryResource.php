<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseCategoryResource extends JsonResource
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
            'name' => $this->name,
            'expenses_count' => (int) ($this->expenses_count ?? 0),
            'recurring_expenses_count' => (int) ($this->recurring_expenses_count ?? 0),
            'quick_expenses_count' => (int) ($this->quick_expenses_count ?? 0),
            'total_usage_count' => (int) (($this->expenses_count ?? 0) + ($this->recurring_expenses_count ?? 0) + ($this->quick_expenses_count ?? 0)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
