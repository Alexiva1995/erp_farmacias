<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Modificar products para yield_percentage y sub-receta
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'yield_percentage')) {
                $table->decimal('yield_percentage', 5, 2)->default(100.00)->after('unit_cost');
            }
            if (!Schema::hasColumn('products', 'is_sub_recipe')) {
                $table->boolean('is_sub_recipe')->default(false)->after('yield_percentage');
            }
        });

        // 2. Modificar dishes para vincular producto elaborado y datos de rendimiento
        Schema::table('dishes', function (Blueprint $table) {
            if (!Schema::hasColumn('dishes', 'output_product_id')) {
                $table->foreignId('output_product_id')->nullable()->after('category_id')->constrained('products')->nullOnDelete();
            }
            if (!Schema::hasColumn('dishes', 'expected_yield_quantity')) {
                $table->decimal('expected_yield_quantity', 10, 2)->default(1.00)->after('output_product_id');
            }
            if (!Schema::hasColumn('dishes', 'shelf_life_days')) {
                $table->integer('shelf_life_days')->default(7)->after('expected_yield_quantity');
            }
        });

        // 3. Tabla de Órdenes de Producción Interna (Sub-recetas de cocina)
        if (!Schema::hasTable('production_orders')) {
            Schema::create('production_orders', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique();
                $table->foreignId('dish_id')->constrained('dishes')->onDelete('cascade');
                $table->foreignId('output_product_id')->constrained('products')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('kitchen_station_id')->nullable()->constrained('kitchen_stations')->nullOnDelete();
                $table->decimal('batch_multiplier', 8, 2)->default(1.00);
                $table->decimal('expected_quantity', 10, 2)->default(1.00);
                $table->decimal('actual_quantity', 10, 2)->default(1.00);
                $table->decimal('total_production_cost', 14, 2)->default(0.00);
                $table->decimal('unit_cost_calculated', 14, 2)->default(0.00);
                $table->foreignId('generated_lot_id')->nullable()->constrained('product_lots')->nullOnDelete();
                $table->string('status', 20)->default('completed'); // draft, in_progress, completed, cancelled
                $table->text('notes')->nullable();
                $table->timestamp('produced_at')->nullable();
                $table->timestamps();

                $table->index(['dish_id', 'status']);
            });
        }

        // 4. Tabla de Mermas y Bajas de Cocina (Kitchen Waste)
        if (!Schema::hasTable('kitchen_wastes')) {
            Schema::create('kitchen_wastes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('kitchen_station_id')->nullable()->constrained('kitchen_stations')->nullOnDelete();
                $table->string('item_type', 20); // product, dish
                $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->foreignId('dish_id')->nullable()->constrained('dishes')->nullOnDelete();
                $table->foreignId('lot_id')->nullable()->constrained('product_lots')->nullOnDelete();
                $table->decimal('quantity', 10, 2)->default(1.00);
                $table->decimal('unit_cost', 14, 2)->default(0.00);
                $table->decimal('total_loss_usd', 14, 2)->default(0.00);
                $table->string('reason', 30)->default('burned_spoiled');
                $table->text('notes')->nullable();
                $table->timestamp('occurred_at')->nullable();
                $table->timestamps();

                $table->index(['item_type', 'reason']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kitchen_wastes');
        Schema::dropIfExists('production_orders');

        Schema::table('dishes', function (Blueprint $table) {
            if (Schema::hasColumn('dishes', 'output_product_id')) {
                $table->dropForeign(['output_product_id']);
                $table->dropColumn(['output_product_id', 'expected_yield_quantity', 'shelf_life_days']);
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'yield_percentage')) {
                $table->dropColumn(['yield_percentage', 'is_sub_recipe']);
            }
        });
    }
};
