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
        if (!Schema::hasTable('modifier_groups')) {
            Schema::create('modifier_groups', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('description')->nullable();
                $table->unsignedInteger('min_selections')->default(0);
                $table->unsignedInteger('max_selections')->default(1);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('modifier_options')) {
            Schema::create('modifier_options', function (Blueprint $table) {
                $table->id();
                $table->foreignId('modifier_group_id')->constrained('modifier_groups')->cascadeOnDelete();
                $table->string('name');
                $table->string('modifier_type')->default('add'); // add, remove, info
                $table->decimal('extra_price_usd', 12, 4)->default(0.0000);
                $table->unsignedBigInteger('product_id')->nullable();
                $table->decimal('portion', 12, 4)->default(0.0000);
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('dish_modifier_group')) {
            Schema::create('dish_modifier_group', function (Blueprint $table) {
                $table->id();
                $table->foreignId('dish_id')->constrained('dishes')->cascadeOnDelete();
                $table->foreignId('modifier_group_id')->constrained('modifier_groups')->cascadeOnDelete();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['dish_id', 'modifier_group_id']);
            });
        }

        if (!Schema::hasTable('order_detail_modifiers')) {
            Schema::create('order_detail_modifiers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_detail_id')->constrained('order_details')->cascadeOnDelete();
                $table->unsignedBigInteger('modifier_option_id')->nullable();
                $table->string('name');
                $table->string('modifier_type')->default('add'); // add, remove, info
                $table->decimal('extra_price_usd', 12, 4)->default(0.0000);
                $table->unsignedBigInteger('product_id')->nullable();
                $table->decimal('portion', 12, 4)->default(0.0000);
                $table->timestamps();

                $table->foreign('modifier_option_id')->references('id')->on('modifier_options')->nullOnDelete();
                $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_detail_modifiers');
        Schema::dropIfExists('dish_modifier_group');
        Schema::dropIfExists('modifier_options');
        Schema::dropIfExists('modifier_groups');
    }
};
