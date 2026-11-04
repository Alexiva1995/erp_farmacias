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
        if (Schema::hasTable('dishes') && !Schema::hasColumn('dishes', 'designated_price_cop')) {
            Schema::table('dishes', function (Blueprint $table) {
                $table->decimal('designated_price_cop', 14, 2)->nullable()->after('designated_price');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('dishes') && Schema::hasColumn('dishes', 'designated_price_cop')) {
            Schema::table('dishes', function (Blueprint $table) {
                $table->dropColumn('designated_price_cop');
            });
        }
    }
};
