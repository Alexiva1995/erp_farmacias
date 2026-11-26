<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CategoryProfitability extends Model
{
    use HasFactory;

    protected $table = 'category_profitability';

    protected $fillable = [
        'category_id',
        'profitability_percentage',
        'shipping_cost',
        'packaging_cost',
        'expense_margin',
        'profit_margin',
        'tax_usa',
        'status',
    ];

    protected $casts = [
        'category_id' => 'integer',
        'profitability_percentage' => 'float',
        'shipping_cost' => 'float',
        'packaging_cost' => 'float',
        'expense_margin' => 'float',
        'profit_margin' => 'float',
        'tax_usa' => 'float',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
