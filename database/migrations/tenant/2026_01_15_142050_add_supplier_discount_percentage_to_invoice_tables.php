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
        Schema::table('invoice_details', function (Blueprint $table) {
            $table->decimal('supplier_discount_percentage', 5, 2)->default(0)->after('unit_cost');
        });

        Schema::table('invoice_returns', function (Blueprint $table) {
            $table->decimal('supplier_discount_percentage', 5, 2)->default(0)->after('quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_details', function (Blueprint $table) {
            $table->dropColumn('supplier_discount_percentage');
        });

        Schema::table('invoice_returns', function (Blueprint $table) {
            $table->dropColumn('supplier_discount_percentage');
        });;
    }
};
