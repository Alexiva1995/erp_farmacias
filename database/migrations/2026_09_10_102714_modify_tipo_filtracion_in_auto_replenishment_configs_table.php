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
        Schema::table('auto_replenishment_configs', function (Blueprint $table) {
            $table->string('tipo_filtracion', 50)->default('weighted')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auto_replenishment_configs', function (Blueprint $table) {
            $table->enum('tipo_filtracion', ['average', 'sales', 'combinado'])->default('average')->change();
        });
    }
};
