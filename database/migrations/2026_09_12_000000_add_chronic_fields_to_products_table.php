<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_chronic')->default(false)->after('is_favorite');
            $table->unsignedInteger('treatment_duration_days')->default(30)->after('is_chronic');
            $table->index('is_chronic', 'idx_products_is_chronic');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_is_chronic');
            $table->dropColumn(['is_chronic', 'treatment_duration_days']);
        });
    }
};
