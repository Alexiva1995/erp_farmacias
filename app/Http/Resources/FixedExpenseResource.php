<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FixedExpenseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $frequencyLabel = $this->interval_unit instanceof \App\Enums\FixedExpenseIntervalUnit
            ? $this->interval_unit->describe((int) $this->interval_count)
            : "Cada {$this->interval_count} {$this->interval_unit}";

        return [
            'id' => $this->id,
            'name' => $this->name,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                ];
            }),
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'is_amount_variable' => (bool) $this->is_amount_variable,
            'default_count' => $this->default_count,
            'interval_unit' => $this->interval_unit instanceof \BackedEnum ? $this->interval_unit->value : $this->interval_unit,
            'interval_count' => (int) $this->interval_count,
            'frequency_label' => $frequencyLabel,
            'day_of_month' => $this->day_of_month,
            'start_date' => $this->start_date ? Carbon::parse($this->start_date)->format('Y-m-d') : null,
            'end_date' => $this->end_date ? Carbon::parse($this->end_date)->format('Y-m-d') : null,
            'next_due_date' => $this->next_due_date ? Carbon::parse($this->next_due_date)->format('Y-m-d') : null,
            'reminder_days_before' => (int) $this->reminder_days_before,
            'is_active' => (bool) $this->is_active,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
