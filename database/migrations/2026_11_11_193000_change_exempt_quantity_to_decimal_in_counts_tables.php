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
            $table->decimal('exempt_quantity', 12, 4)->default(0)->change();
        });

        Schema::table('sales_counts', function (Blueprint $table) {
            $table->decimal('exempt_quantity', 12, 4)->default(0)->change();
        });

        Schema::table('invoices_counts', function (Blueprint $table) {
            $table->decimal('exempt_quantity', 12, 4)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_counts', function (Blueprint $table) {
            $table->unsignedInteger('exempt_quantity')->default(0)->change();
        });

        Schema::table('sales_counts', function (Blueprint $table) {
            $table->unsignedInteger('exempt_quantity')->default(0)->change();
        });

        Schema::table('invoices_counts', function (Blueprint $table) {
            $table->unsignedInteger('exempt_quantity')->default(0)->change();
        });
    }
};
