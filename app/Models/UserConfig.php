<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserConfig extends Model
{
    protected $table = 'user_config';
    protected $fillable = ['user_id', 'sort_products_orders'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

}
