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
        if (Schema::hasTable('dish_ingredients') && !Schema::hasColumn('dish_ingredients', 'is_removable')) {
            Schema::table('dish_ingredients', function (Blueprint $table) {
                $table->boolean('is_removable')->default(false)->after('designated_cost');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('dish_ingredients') && Schema::hasColumn('dish_ingredients', 'is_removable')) {
            Schema::table('dish_ingredients', function (Blueprint $table) {
                $table->dropColumn('is_removable');
            });
        }
    }
};
