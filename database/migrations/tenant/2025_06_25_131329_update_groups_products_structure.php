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
        Schema::table('groups_products', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropForeign(['related_product_id']);

            $table->dropColumn(['product_id', 'related_product_id']);

            $table->string('name')->after('id');

            $table->unique('name', 'uniq_group_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups_products', function (Blueprint $table) {
            $table->dropUnique('uniq_group_name');
            $table->dropColumn('name');

            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('related_product_id');

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('related_product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }
};
