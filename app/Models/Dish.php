<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Dish extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'cost_price',
        'cpv',
        'suggested_price',
        'designated_price',
        'percentage_profit',
        'category_id',
        'status',
        'photo_url',
        'description',
        'iva',
        'tax_rate',
        'preparation_time_minutes',
        'kitchen_station_id',
        'output_product_id',
        'expected_yield_quantity',
        'shelf_life_days',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'cpv' => 'decimal:2',
        'suggested_price' => 'decimal:2',
        'designated_price' => 'decimal:2',
        'percentage_profit' => 'decimal:2',
        'iva' => 'boolean',
        'tax_rate' => 'decimal:2',
        'preparation_time_minutes' => 'integer',
        'kitchen_station_id' => 'integer',
        'output_product_id' => 'integer',
        'expected_yield_quantity' => 'decimal:2',
        'shelf_life_days' => 'integer',
    ];

    protected static array $hasIsRemovableCache = [];
    protected static array $schemaEnsured = [];

    /**
     * Asegurar que las columnas de subrecetas y restaurante existan en la conexión activa (soporte multi-tenant).
     */
    public static function ensureRestaurantSchema($connection = null): void
    {
        try {
            $conn = $connection ?? (new static())->getConnection();
            $connName = $conn->getName();

            if (!empty(static::$schemaEnsured[$connName])) {
                return;
            }

            // Si la conexión ya se encuentra dentro de una transacción activa, abortar la ejecución DDL
            // porque en MySQL / MariaDB cualquier ALTER TABLE ejecuta un COMMIT IMPLÍCITO y rompe la transacción
            if ($conn->transactionLevel() > 0) {
                return;
            }

            static::$schemaEnsured[$connName] = true;

            $schema = $conn->getSchemaBuilder();

            if ($schema->hasTable('products')) {
                $missingYield = !$schema->hasColumn('products', 'yield_percentage');
                $missingSubRecipe = !$schema->hasColumn('products', 'is_sub_recipe');
                if ($missingYield || $missingSubRecipe) {
                    $schema->table('products', function (\Illuminate\Database\Schema\Blueprint $table) use ($missingYield, $missingSubRecipe) {
                        if ($missingYield) {
                            $table->decimal('yield_percentage', 5, 2)->default(100.00);
                        }
                        if ($missingSubRecipe) {
                            $table->boolean('is_sub_recipe')->default(false);
                        }
                    });
                }
            }

            if ($schema->hasTable('dishes')) {
                $missingOutputProd = !$schema->hasColumn('dishes', 'output_product_id');
                $missingExpectedYield = !$schema->hasColumn('dishes', 'expected_yield_quantity');
                $missingShelfLife = !$schema->hasColumn('dishes', 'shelf_life_days');
                $missingKitchenStation = !$schema->hasColumn('dishes', 'kitchen_station_id');
                $missingPrepTime = !$schema->hasColumn('dishes', 'preparation_time_minutes');
                $missingCpv = !$schema->hasColumn('dishes', 'cpv');
                $missingIva = !$schema->hasColumn('dishes', 'iva');
                $missingTaxRate = !$schema->hasColumn('dishes', 'tax_rate');

                if ($missingOutputProd || $missingExpectedYield || $missingShelfLife || $missingKitchenStation || $missingPrepTime || $missingCpv || $missingIva || $missingTaxRate) {
                    $schema->table('dishes', function (\Illuminate\Database\Schema\Blueprint $table) use (
                        $missingOutputProd,
                        $missingExpectedYield,
                        $missingShelfLife,
                        $missingKitchenStation,
                        $missingPrepTime,
                        $missingCpv,
                        $missingIva,
                        $missingTaxRate
                    ) {
                        if ($missingOutputProd) {
                            $table->unsignedBigInteger('output_product_id')->nullable();
                        }
                        if ($missingExpectedYield) {
                            $table->decimal('expected_yield_quantity', 10, 2)->default(1.00);
                        }
                        if ($missingShelfLife) {
                            $table->integer('shelf_life_days')->default(7);
                        }
                        if ($missingKitchenStation) {
                            $table->unsignedBigInteger('kitchen_station_id')->nullable();
                        }
                        if ($missingPrepTime) {
                            $table->integer('preparation_time_minutes')->default(15);
                        }
                        if ($missingCpv) {
                            $table->decimal('cpv', 12, 2)->default(0.00);
                        }
                        if ($missingIva) {
                            $table->boolean('iva')->default(false);
                        }
                        if ($missingTaxRate) {
                            $table->decimal('tax_rate', 5, 2)->default(16.00);
                        }
                    });
                }
            }

            if ($schema->hasTable('dish_ingredients')) {
                if (!$schema->hasColumn('dish_ingredients', 'is_removable')) {
                    $schema->table('dish_ingredients', function (\Illuminate\Database\Schema\Blueprint $table) {
                        $table->boolean('is_removable')->default(false);
                    });
                }
                static::$hasIsRemovableCache[$connName] = true;
            }
        } catch (\Throwable $e) {
            // Ignorar errores de concurrencia o permisos de DDL
        }
    }

    public function ingredients(): BelongsToMany
    {
        $conn = $this->getConnection();
        $connName = $conn->getName();

        static::ensureRestaurantSchema($conn);

        if (!isset(static::$hasIsRemovableCache[$connName])) {
            try {
                $schema = $conn->getSchemaBuilder();
                if ($schema->hasTable('dish_ingredients')) {
                    if (!$schema->hasColumn('dish_ingredients', 'is_removable')) {
                        $schema->table('dish_ingredients', function (\Illuminate\Database\Schema\Blueprint $table) {
                            $table->boolean('is_removable')->default(false)->after('designated_cost');
                        });
                    }
                    static::$hasIsRemovableCache[$connName] = true;
                } else {
                    static::$hasIsRemovableCache[$connName] = false;
                }
            } catch (\Throwable $e) {
                try {
                    static::$hasIsRemovableCache[$connName] = $conn->getSchemaBuilder()->hasColumn('dish_ingredients', 'is_removable');
                } catch (\Throwable $ex) {
                    static::$hasIsRemovableCache[$connName] = false;
                }
            }
        }

        $pivotColumns = ['id', 'portion', 'designated_cost'];
        if (!empty(static::$hasIsRemovableCache[$connName])) {
            $pivotColumns[] = 'is_removable';
        }

        return $this->belongsToMany(Product::class, 'dish_ingredients', 'dish_id', 'product_id')
            ->withPivot($pivotColumns)
            ->withTimestamps();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function kitchenStation(): BelongsTo
    {
        return $this->belongsTo(KitchenStation::class, 'kitchen_station_id');
    }

    public function outputProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'output_product_id');
    }

    public function modifierGroups(): BelongsToMany
    {
        return $this->belongsToMany(ModifierGroup::class, 'dish_modifier_group')
            ->withPivot('sort_order')
            ->withTimestamps();
    }
}
