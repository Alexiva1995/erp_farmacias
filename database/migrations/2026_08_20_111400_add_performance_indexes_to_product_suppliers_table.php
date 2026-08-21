<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_suppliers', function (Blueprint $table) {
            $table->index(['created_at', 'unit_cost_usd'], 'idx_ps_created_unit_cost_usd');
            $table->index(['supplier_id', 'created_at'], 'idx_ps_supplier_created');
            $table->index(['product_id', 'is_active'], 'idx_ps_product_active');
        });
    }

    public function down(): void
    {
        Schema::table('product_suppliers', function (Blueprint $table) {
            $table->dropIndex('idx_ps_created_unit_cost_usd');
            $table->dropIndex('idx_ps_supplier_created');
            $table->dropIndex('idx_ps_product_active');
        });
    }
};
