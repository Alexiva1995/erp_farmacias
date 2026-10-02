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
        Schema::table('products', function (Blueprint $table) {
            $table->integer('dias_quiebre_m1')->nullable()->default(0)->after('dias_quiebre_90d_cache')->comment('Dias sin stock en M1 (0-30d)');
            $table->integer('dias_quiebre_m2')->nullable()->default(0)->after('dias_quiebre_m1')->comment('Dias sin stock en M2 (31-60d)');
            $table->integer('dias_quiebre_m3')->nullable()->default(0)->after('dias_quiebre_m2')->comment('Dias sin stock en M3 (61-90d)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['dias_quiebre_m1', 'dias_quiebre_m2', 'dias_quiebre_m3']);
        });
    }
};
