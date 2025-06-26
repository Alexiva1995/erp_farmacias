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
        Schema::table('products', function (Blueprint $table) {
            $table->string('active_ingredient')->nullable(false)->change();
            $table->boolean('iva')->nullable()->default(0)->change();
            $table->boolean('psychotropic')->nullable()->default(0)->change();

            $table->renameColumn('cost_price', 'unit_cost');
            $table->renameColumn('from_colombia', 'is_colombian_origin');
            $table->boolean('is_colombian_origin')->nullable()->default(0)->change();

            $table->dropColumn('stock');
            $table->dropColumn('created_at');
            $table->dropColumn('updated_at');

            if (Schema::hasColumn('products', 'deleted_at')) {
                $table->dropColumn('deleted_at');
            }

            $table->decimal('sales_average', 10, 2)->default(0)->after('photo_url');
            $table->foreignId(column: 'group_id')->nullable()->constrained('groups_products')->nullOnDelete()->after('category_id');  
            $table->foreignId('cycle_id')->nullable()->constrained('inventory_cycles')->nullOnDelete()->after('category_id');
            $table->boolean('is_deleted')->nullable()->default(false)->after('photo_url');      
               
            $table->timestamps();
        });

        // Índices nuevos (solo los que aparecen en el dump)
        Schema::table('products', function (Blueprint $table) {
            $table->index('barcode', 'idx_product_barcode');
            $table->index('is_deleted', 'idx_product_deleted');
            $table->index('sales_average', 'idx_product_sales');
            $table->index('psychotropic', 'idx_product_psychotropic');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('active_ingredient')->nullable()->change();
            $table->boolean('iva')->default(0)->nullable(false)->change();
            $table->boolean('psychotropic')->default(0)->nullable(false)->change();

            $table->renameColumn('unit_cost', 'cost_price');
            $table->renameColumn('is_colombian_origin', 'from_colombia');
            $table->boolean('is_colombian_origin')->default(0)->nullable(false)->change();

            $table->dropColumn([
                'sales_average',
                'group_id',
                'cycle_id',
                'is_deleted',
            ]);

            $table->unsignedInteger('stock')->default(0);
            $table->timestamp('deleted_at')->nullable();

            $table->dropIndex('idx_product_barcode');
            $table->dropIndex('idx_product_deleted');
            $table->dropIndex('idx_product_sales');
            $table->dropIndex('idx_product_psychotropic');
        });
    }
};
