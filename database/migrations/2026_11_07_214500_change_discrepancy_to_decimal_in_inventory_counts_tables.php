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
        Schema::table('product_counts', function (Blueprint $table) {
            $table->decimal('discrepancy', 12, 3)->default(0)->change();
            if (Schema::hasColumn('product_counts', 'correction_difference')) {
                $table->decimal('correction_difference', 12, 3)->nullable()->change();
            }
        });

        Schema::table('sales_counts', function (Blueprint $table) {
            $table->decimal('discrepancy', 12, 3)->default(0)->change();
        });

        Schema::table('invoices_counts', function (Blueprint $table) {
            $table->decimal('discrepancy', 12, 3)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_counts', function (Blueprint $table) {
            $table->integer('discrepancy')->default(0)->change();
            if (Schema::hasColumn('product_counts', 'correction_difference')) {
                $table->integer('correction_difference')->nullable()->change();
            }
        });

        Schema::table('sales_counts', function (Blueprint $table) {
            $table->integer('discrepancy')->default(0)->change();
        });

        Schema::table('invoices_counts', function (Blueprint $table) {
            $table->integer('discrepancy')->default(0)->change();
        });
    }
};
