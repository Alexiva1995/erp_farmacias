<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpiredLog extends Model
{
    use HasFactory;
    protected $fillable = [
        'lot_id',
        'product_id',
        'product_name',
        'lot_number',
        'expired_quantity',
        'cost_per_unit',
        'total_lost_value',
    ];
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function donativeLog()
    {
        return $this->hasOne(DonativeLog::class);
    }
}
