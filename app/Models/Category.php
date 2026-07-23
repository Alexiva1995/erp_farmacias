<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Category extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
    ];

    protected static function booted()
    {
        static::saved(function () {
            \Illuminate\Support\Facades\Cache::forget('resources.categories');
            \Illuminate\Support\Facades\Cache::forget('resources.categories.dishes');
        });

        static::deleted(function () {
            \Illuminate\Support\Facades\Cache::forget('resources.categories');
            \Illuminate\Support\Facades\Cache::forget('resources.categories.dishes');
        });
    }

    public function offers()
    {
        return $this->hasMany(CategoryOffer::class);
    }

    public function profitability()
    {
        return $this->hasOne(CategoryProfitability::class);
    }

    /**
     * Obtener los productos asociados a la categoría.
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    /**
     * Obtener los platos asociados a la categoría.
     */
    public function dishes()
    {
        return $this->hasMany(Dish::class, 'category_id');
    }
}
