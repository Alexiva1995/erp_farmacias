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
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'idx_orders_status_created');
        });

        Schema::table('order_details', function (Blueprint $table) {
            $table->index(['product_id', 'order_id'], 'idx_order_details_product_order');
        });

        if (Schema::hasTable('auto_orders')) {
            Schema::table('auto_orders', function (Blueprint $table) {
                $table->index(['status', 'deleted_at'], 'idx_auto_orders_status_deleted');
            });
        }

        if (Schema::hasTable('auto_order_details')) {
            Schema::table('auto_order_details', function (Blueprint $table) {
                $table->index(['product_id', 'status', 'deleted_at'], 'idx_aod_product_status_deleted');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_status_created');
        });

        Schema::table('order_details', function (Blueprint $table) {
            $table->dropIndex('idx_order_details_product_order');
        });

        if (Schema::hasTable('auto_orders')) {
            Schema::table('auto_orders', function (Blueprint $table) {
                $table->dropIndex('idx_auto_orders_status_deleted');
            });
        }

        if (Schema::hasTable('auto_order_details')) {
            Schema::table('auto_order_details', function (Blueprint $table) {
                $table->dropIndex('idx_aod_product_status_deleted');
            });
        }
    }
};
