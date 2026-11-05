<?php

namespace App\Models;

use App\Enums\FixedExpenseIntervalUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FixedExpense extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'category_id',
        'amount',
        'currency',
        'is_amount_variable',
        'default_count',
        'interval_unit',
        'interval_count',
        'day_of_month',
        'start_date',
        'end_date',
        'next_due_date',
        'reminder_days_before',
        'is_active',
        'notes',
        'user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_amount_variable' => 'boolean',
        'interval_unit' => FixedExpenseIntervalUnit::class,
        'interval_count' => 'integer',
        'day_of_month' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'next_due_date' => 'date',
        'reminder_days_before' => 'integer',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(FixedExpenseOccurrence::class);
    }
}
