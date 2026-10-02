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
        Schema::table('quotation_products', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->change();
            $table->foreignId('dish_id')->nullable()->constrained('dishes')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotation_products', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable(false)->change();
            $table->dropForeign(['dish_id']);
            $table->dropColumn('dish_id');
        });
    }
};
