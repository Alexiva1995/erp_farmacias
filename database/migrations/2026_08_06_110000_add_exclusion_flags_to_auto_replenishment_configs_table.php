<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auto_replenishment_configs', function (Blueprint $table) {
            $table->boolean('exclude_colombian')->default(false)->after('con_descuento');
            $table->boolean('exclude_novaventa')->default(false)->after('exclude_colombian');
        });
    }

    public function down(): void
    {
        Schema::table('auto_replenishment_configs', function (Blueprint $table) {
            $table->dropColumn(['exclude_colombian', 'exclude_novaventa']);
        });
    }
};
