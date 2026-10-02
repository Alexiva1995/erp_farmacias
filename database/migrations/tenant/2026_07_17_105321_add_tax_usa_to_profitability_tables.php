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
        Schema::table('profitability_settings', function (Blueprint $table) {
            $table->decimal('tax_usa', 8, 2)->nullable()->default(0.00)->after('profit_margin');
        });

        Schema::table('product_profitability', function (Blueprint $table) {
            $table->decimal('tax_usa', 8, 2)->nullable()->default(0.00)->after('profit_margin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profitability_settings', function (Blueprint $table) {
            $table->dropColumn('tax_usa');
        });

        Schema::table('product_profitability', function (Blueprint $table) {
            $table->dropColumn('tax_usa');
        });
    }
};
