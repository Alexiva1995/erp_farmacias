<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    protected $fillable = [
        'name',
    ];

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'category_id');
    }

    public function recurringExpenses()
    {
        return $this->hasMany(RecurringExpense::class, 'category_id');
    }

    public function quickExpenses()
    {
        return $this->hasMany(QuickExpense::class, 'category_id');
    }
}
