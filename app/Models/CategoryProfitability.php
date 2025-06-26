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
        'status',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
