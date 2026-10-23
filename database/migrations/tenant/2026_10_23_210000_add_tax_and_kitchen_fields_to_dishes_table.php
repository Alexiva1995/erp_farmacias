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
        Schema::table('dishes', function (Blueprint $table) {
            if (!Schema::hasColumn('dishes', 'iva')) {
                $table->boolean('iva')->default(false)->after('status');
            }
            if (!Schema::hasColumn('dishes', 'tax_rate')) {
                $table->decimal('tax_rate', 5, 2)->default(16.00)->after('iva');
            }
            if (!Schema::hasColumn('dishes', 'preparation_time_minutes')) {
                $table->unsignedInteger('preparation_time_minutes')->nullable()->default(15)->after('tax_rate');
            }
            if (!Schema::hasColumn('dishes', 'kitchen_station_id')) {
                $table->unsignedBigInteger('kitchen_station_id')->nullable()->index()->after('preparation_time_minutes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dishes', function (Blueprint $table) {
            $table->dropColumn([
                'iva',
                'tax_rate',
                'preparation_time_minutes',
                'kitchen_station_id',
            ]);
        });
    }
};
