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
        Schema::table('prescription_offers', function (Blueprint $table) {
            $table->json('products')->nullable()->after('discount_percentage')->comment('JSON con productos asociados: {product_id: {sale_price, quantity}}');
            $table->decimal('total_cost', 10, 2)->default(0)->after('is_active')->comment('Suma total del costo de todos los productos');
            
            $table->index('total_cost', 'idx_prescription_total_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prescription_offers', function (Blueprint $table) {
            $table->dropColumn(['products', 'total_cost']);
        });
    }
};
