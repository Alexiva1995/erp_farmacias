<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CategoryOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'discount_percentage',
        'start_date',
        'end_date',
        'is_active',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
