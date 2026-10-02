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
        Schema::table('general_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('general_settings', 'ecommerce_shipping_methods')) {
                $table->json('ecommerce_shipping_methods')->nullable()->after('ecommerce_menu');
            }
        });

        Schema::table('ecommerce_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('ecommerce_orders', 'shipping_method')) {
                $table->string('shipping_method', 100)->nullable()->after('shipping_address');
            }
            if (!Schema::hasColumn('ecommerce_orders', 'shipping_cost')) {
                $table->decimal('shipping_cost', 10, 2)->default(0.00)->after('shipping_method');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('general_settings', function (Blueprint $table) {
            if (Schema::hasColumn('general_settings', 'ecommerce_shipping_methods')) {
                $table->dropColumn('ecommerce_shipping_methods');
            }
        });

        Schema::table('ecommerce_orders', function (Blueprint $table) {
            if (Schema::hasColumn('ecommerce_orders', 'shipping_method')) {
                $table->dropColumn('shipping_method');
            }
            if (Schema::hasColumn('ecommerce_orders', 'shipping_cost')) {
                $table->dropColumn('shipping_cost');
            }
        });
    }
};
