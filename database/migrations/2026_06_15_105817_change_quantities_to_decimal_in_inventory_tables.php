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
        Schema::table('product_lots', function (Blueprint $table) {
            $table->decimal('quantity', 12, 3)->change();
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->decimal('quantity', 12, 3)->change();
            $table->decimal('stock_before', 12, 3)->nullable()->change();
            $table->decimal('stock_after', 12, 3)->nullable()->change();
        });

        Schema::table('order_details', function (Blueprint $table) {
            $table->decimal('quantity', 12, 3)->change();
        });

        Schema::table('invoice_details', function (Blueprint $table) {
            $table->decimal('quantity', 12, 3)->change();
        });

        // Other tables as needed, ignoring counts for now to keep it safe or we can include them
        Schema::table('product_counts', function (Blueprint $table) {
            $table->decimal('counted_quantity', 12, 3)->nullable()->change();
            $table->decimal('system_quantity', 12, 3)->nullable()->change();
        });

        Schema::table('sales_counts', function (Blueprint $table) {
            $table->decimal('counted_quantity', 12, 3)->nullable()->change();
            $table->decimal('system_quantity', 12, 3)->nullable()->change();
        });

        Schema::table('invoices_counts', function (Blueprint $table) {
            $table->decimal('counted_quantity', 12, 3)->nullable()->change();
            $table->decimal('system_quantity', 12, 3)->nullable()->change();
        });

        Schema::table('expirations', function (Blueprint $table) {
            $table->decimal('quantity', 12, 3)->change();
        });
        
        Schema::table('expired_logs', function (Blueprint $table) {
            $table->decimal('expired_quantity', 12, 3)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverting to integers
        Schema::table('product_lots', function (Blueprint $table) {
            $table->integer('quantity')->change();
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->integer('quantity')->change();
            $table->integer('stock_before')->nullable()->change();
            $table->integer('stock_after')->nullable()->change();
        });

        Schema::table('order_details', function (Blueprint $table) {
            $table->integer('quantity')->change();
        });

        Schema::table('invoice_details', function (Blueprint $table) {
            $table->integer('quantity')->change();
        });

        Schema::table('product_counts', function (Blueprint $table) {
            $table->integer('counted_quantity')->nullable()->change();
            $table->integer('system_quantity')->nullable()->change();
        });

        Schema::table('sales_counts', function (Blueprint $table) {
            $table->integer('counted_quantity')->nullable()->change();
            $table->integer('system_quantity')->nullable()->change();
        });

        Schema::table('invoices_counts', function (Blueprint $table) {
            $table->integer('counted_quantity')->nullable()->change();
            $table->integer('system_quantity')->nullable()->change();
        });

        Schema::table('expirations', function (Blueprint $table) {
            $table->integer('quantity')->change();
        });

        Schema::table('expired_logs', function (Blueprint $table) {
            $table->integer('expired_quantity')->change();
        });
    }
};
