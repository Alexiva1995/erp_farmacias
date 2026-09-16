<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_counts', function (Blueprint $table) {
            $table->decimal('points_earned', 8, 2)->default(1.0)->change();
        });

        Schema::table('invoices_counts', function (Blueprint $table) {
            $table->decimal('points_earned', 8, 2)->default(1.0)->change();
        });

        Schema::table('sales_counts', function (Blueprint $table) {
            $table->decimal('points_earned', 8, 2)->default(1.0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('product_counts', function (Blueprint $table) {
            $table->integer('points_earned')->default(1)->change();
        });

        Schema::table('invoices_counts', function (Blueprint $table) {
            $table->integer('points_earned')->default(1)->change();
        });

        Schema::table('sales_counts', function (Blueprint $table) {
            $table->integer('points_earned')->default(1)->change();
        });
    }
};
