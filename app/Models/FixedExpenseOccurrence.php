<?php

namespace App\Models;

use App\Enums\FixedExpenseOccurrenceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedExpenseOccurrence extends Model
{
    protected $fillable = [
        'fixed_expense_id',
        'due_date',
        'amount',
        'currency',
        'status',
        'expense_id',
        'paid_at',
        'paid_by_id',
        'skipped_at',
        'skipped_by_id',
        'note',
    ];

    protected $casts = [
        'due_date' => 'date',
        'amount' => 'decimal:2',
        'status' => FixedExpenseOccurrenceStatus::class,
        'paid_at' => 'datetime',
        'skipped_at' => 'datetime',
    ];

    public function fixedExpense(): BelongsTo
    {
        // Se incluyen plantillas eliminadas para conservar el historial
        return $this->belongsTo(FixedExpense::class)->withTrashed();
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_id');
    }
}
