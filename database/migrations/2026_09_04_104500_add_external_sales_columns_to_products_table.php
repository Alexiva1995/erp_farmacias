<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE products MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE product_lots MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        } catch (\Throwable) {}

        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'external_accumulated_sales')) {
                $table->decimal('external_accumulated_sales', 12, 2)->nullable()->default(0)->after('sales_average');
            }
            if (!Schema::hasColumn('products', 'external_sales_date')) {
                $table->date('external_sales_date')->nullable()->after('external_accumulated_sales');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['external_accumulated_sales', 'external_sales_date']);
        });
    }
};
