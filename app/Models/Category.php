<?php

namespace App\Models;

use App\Enums\CategoryTypeEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Category extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Atributos asignables masivamente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'type',
    ];

    /**
     * Caché en memoria por conexión para verificación de columna type.
     *
     * @var array<string, bool>
     */
    protected static array $hasTypeColumnCache = [];

    /**
     * Asegurar que la columna type exista en la tabla categories de la conexión activa.
     */
    public static function ensureTypeColumn($connection = null): bool
    {
        try {
            $conn = $connection ?? (new static())->getConnection();
            $connName = $conn->getName();

            if (isset(static::$hasTypeColumnCache[$connName])) {
                return static::$hasTypeColumnCache[$connName];
            }

            $schema = $conn->getSchemaBuilder();
            if (!$schema->hasTable('categories')) {
                return static::$hasTypeColumnCache[$connName] = false;
            }

            if ($schema->hasColumn('categories', 'type')) {
                return static::$hasTypeColumnCache[$connName] = true;
            }

            if ($conn->transactionLevel() > 0) {
                return false;
            }

            $schema->table('categories', function (Blueprint $table) {
                $table->string('type', 20)->default(CategoryTypeEnum::INVENTORY->value)->after('name')->index();
            });

            if ($schema->hasTable('dishes') && $schema->hasTable('products')) {
                $dishCategoryIds = $conn->table('dishes')
                    ->whereNotNull('category_id')
                    ->distinct()
                    ->pluck('category_id')
                    ->map(fn ($id) => (int) $id)
                    ->toArray();

                $productCategoryIds = $conn->table('products')
                    ->whereNotNull('category_id')
                    ->distinct()
                    ->pluck('category_id')
                    ->map(fn ($id) => (int) $id)
                    ->toArray();

                $onlyDishIds = array_values(array_diff($dishCategoryIds, $productCategoryIds));
                $bothIds = array_values(array_intersect($dishCategoryIds, $productCategoryIds));

                if (!empty($onlyDishIds)) {
                    $conn->table('categories')
                        ->whereIn('id', $onlyDishIds)
                        ->update(['type' => CategoryTypeEnum::DISH->value]);
                }

                if (!empty($bothIds)) {
                    $conn->table('categories')
                        ->whereIn('id', $bothIds)
                        ->update(['type' => CategoryTypeEnum::BOTH->value]);
                }
            }

            return static::$hasTypeColumnCache[$connName] = true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Limpiar todas las variantes de caché de categorías.
     */
    public static function clearCategoriesCache(): void
    {
        $conn = DB::getDefaultConnection();
        Cache::forget('resources.categories');
        Cache::forget('resources.categories.dishes');
        Cache::forget('resources.categories.inventory');
        if ($conn) {
            Cache::forget("resources.categories.{$conn}");
            Cache::forget("resources.categories.dishes.{$conn}");
            Cache::forget("resources.categories.inventory.{$conn}");
        }
    }

    protected static function booted()
    {
        static::saving(function (self $category) {
            if (!static::ensureTypeColumn($category->getConnection())) {
                unset($category->attributes['type']);
            } elseif (empty($category->type)) {
                $category->type = CategoryTypeEnum::INVENTORY->value;
            } elseif ($category->type === 'dishes') {
                $category->type = CategoryTypeEnum::DISH->value;
            } elseif ($category->type === 'products') {
                $category->type = CategoryTypeEnum::INVENTORY->value;
            }
        });

        static::saved(function () {
            static::clearCategoriesCache();
        });

        static::deleted(function () {
            static::clearCategoriesCache();
        });
    }

    /**
     * Filtrar categorías válidas para inventario (insumos y productos).
     */
    public function scopeForInventory(Builder $query): Builder
    {
        if (static::ensureTypeColumn($query->getConnection())) {
            return $query->whereIn('categories.type', CategoryTypeEnum::inventoryValues());
        }

        return $query;
    }

    /**
     * Filtrar categorías válidas para platos / menú de restaurante.
     */
    public function scopeForDishes(Builder $query): Builder
    {
        if (static::ensureTypeColumn($query->getConnection())) {
            return $query->whereIn('categories.type', CategoryTypeEnum::dishValues());
        }

        return $query;
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
