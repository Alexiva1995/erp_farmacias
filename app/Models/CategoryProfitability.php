<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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

    /**
     * Caché en memoria por conexión para evitar verificar esquema repetidamente.
     *
     * @var array<string, bool>
     */
    protected static array $schemaEnsuredCache = [];

    /**
     * Garantiza que la tabla y columnas compuestas de category_profitability existan en la conexión activa.
     */
    public static function ensureSchema($connection = null): bool
    {
        try {
            $conn = $connection ?? (new static())->getConnection();
            $connName = (string) $conn->getName();
            $tenantKey = function_exists('tenancy') && tenancy()->initialized
                ? (string) tenancy()->tenant?->id
                : 'central';
            $cacheKey = "{$connName}:{$tenantKey}";

            if (isset(static::$schemaEnsuredCache[$cacheKey])) {
                return static::$schemaEnsuredCache[$cacheKey];
            }

            if ($conn->transactionLevel() > 0) {
                return false;
            }

            $schema = $conn->getSchemaBuilder();

            if (!$schema->hasTable('category_profitability')) {
                $schema->create('category_profitability', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
                    $table->decimal('profitability_percentage', 5, 2)->nullable();
                    $table->decimal('shipping_cost', 10, 2)->nullable();
                    $table->decimal('packaging_cost', 10, 2)->nullable();
                    $table->decimal('expense_margin', 5, 2)->nullable();
                    $table->decimal('profit_margin', 5, 2)->nullable();
                    $table->decimal('tax_usa', 8, 2)->nullable();
                    $table->enum('status', ['open', 'closed'])->default('open');
                    $table->timestamps();
                    $table->unique('category_id', 'uniq_category_profit');
                    $table->index('status', 'idx_category_profit_status');
                });

                return static::$schemaEnsuredCache[$cacheKey] = true;
            }

            $needsCompoundColumns = !$schema->hasColumn('category_profitability', 'shipping_cost')
                || !$schema->hasColumn('category_profitability', 'packaging_cost')
                || !$schema->hasColumn('category_profitability', 'expense_margin')
                || !$schema->hasColumn('category_profitability', 'profit_margin')
                || !$schema->hasColumn('category_profitability', 'tax_usa');

            if ($needsCompoundColumns) {
                $schema->table('category_profitability', function (Blueprint $table) use ($schema) {
                    if ($schema->hasColumn('category_profitability', 'profitability_percentage')) {
                        $table->decimal('profitability_percentage', 5, 2)->nullable()->change();
                    }
                    if (!$schema->hasColumn('category_profitability', 'shipping_cost')) {
                        $table->decimal('shipping_cost', 10, 2)->nullable()->after('profitability_percentage');
                    }
                    if (!$schema->hasColumn('category_profitability', 'packaging_cost')) {
                        $table->decimal('packaging_cost', 10, 2)->nullable()->after('shipping_cost');
                    }
                    if (!$schema->hasColumn('category_profitability', 'expense_margin')) {
                        $table->decimal('expense_margin', 5, 2)->nullable()->after('packaging_cost');
                    }
                    if (!$schema->hasColumn('category_profitability', 'profit_margin')) {
                        $table->decimal('profit_margin', 5, 2)->nullable()->after('expense_margin');
                    }
                    if (!$schema->hasColumn('category_profitability', 'tax_usa')) {
                        $table->decimal('tax_usa', 8, 2)->nullable()->after('profit_margin');
                    }
                });
            }

            return static::$schemaEnsuredCache[$cacheKey] = true;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[CategoryProfitability] Error asegurando esquema: ' . $e->getMessage());

            return false;
        }
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
