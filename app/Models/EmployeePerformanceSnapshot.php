<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePerformanceSnapshot extends Model
{
    protected $fillable = [
        'employee_id',
        'month',
        'year',
        'name',
        'last_name',
        'sales',
        'growth',
        'expirations',
        'inventory_counted',
        'inventory_errors',
        'premium_products',
        'cleaning_assigned',
        'cleaning_completed',
        'strategy_sales',
        'invoice_items',
        'invoice_headers',
        'invoice_archived',
        'score_sales',
        'score_growth',
        'score_expiration',
        'score_inventory',
        'score_premium',
        'score_invoice',
        'score_cleaning',
        'score_strategy',
        'score_loaded',
        'score_registered',
        'score_ordered',
        'total_score',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
