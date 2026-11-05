<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FixedExpenseOccurrenceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $dueDate = $this->due_date ? Carbon::parse($this->due_date)->startOfDay() : null;
        $today = Carbon::now(config('app.timezone'))->startOfDay();

        $isOverdue = false;
        $daysDifference = 0;

        if ($dueDate) {
            $isOverdue = $dueDate->lt($today) && ($this->status === \App\Enums\FixedExpenseOccurrenceStatus::PENDING || $this->status->value === 'Pending');
            $daysDifference = $today->diffInDays($dueDate, false);
        }

        return [
            'id' => $this->id,
            'fixed_expense_id' => $this->fixed_expense_id,
            'fixed_expense' => $this->whenLoaded('fixedExpense', function () {
                return [
                    'id' => $this->fixedExpense->id,
                    'name' => $this->fixedExpense->name,
                    'is_amount_variable' => (bool) $this->fixedExpense->is_amount_variable,
                    'default_count' => $this->fixedExpense->default_count,
                    'category' => $this->fixedExpense->relationLoaded('category') && $this->fixedExpense->category ? [
                        'id' => $this->fixedExpense->category->id,
                        'name' => $this->fixedExpense->category->name,
                    ] : null,
                ];
            }),
            'due_date' => $dueDate ? $dueDate->format('Y-m-d') : null,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'is_overdue' => $isOverdue,
            'days_difference' => $daysDifference,
            'expense_id' => $this->expense_id,
            'expense' => $this->whenLoaded('expense', function () {
                return [
                    'id' => $this->expense->id,
                    'status' => $this->expense->status,
                ];
            }),
            'paid_at' => $this->paid_at ? Carbon::parse($this->paid_at)->format('Y-m-d H:i:s') : null,
            'skipped_at' => $this->skipped_at ? Carbon::parse($this->skipped_at)->format('Y-m-d H:i:s') : null,
            'note' => $this->note,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
