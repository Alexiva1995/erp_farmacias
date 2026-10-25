<?php

namespace App\Models;

use App\Enums\DiningTableStatusEnum;
use App\Enums\TableShapeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiningTable extends Model
{
    use HasFactory;

    protected $table = 'dining_tables';

    protected $fillable = [
        'dining_zone_id',
        'name',
        'capacity',
        'shape',
        'pos_x',
        'pos_y',
        'width',
        'height',
        'rotation',
        'status',
        'current_order_id',
        'is_active',
        'qr_token',
        'needs_attention',
        'attention_reason',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'pos_x' => 'float',
        'pos_y' => 'float',
        'width' => 'float',
        'height' => 'float',
        'rotation' => 'float',
        'is_active' => 'boolean',
        'needs_attention' => 'boolean',
        'status' => DiningTableStatusEnum::class,
        'shape' => TableShapeEnum::class,
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(DiningZone::class, 'dining_zone_id');
    }

    public function currentOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'current_order_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'dining_table_id');
    }
}
