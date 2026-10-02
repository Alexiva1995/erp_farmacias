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
        Schema::table('clients', function (Blueprint $table) {
            $table->enum('client_type', ['Nuevo', 'Ocasional', 'Frecuente', 'VIP', 'En Riesgo'])
                ->default('Nuevo')
                ->after('status')
                ->comment('Tipo/clasificación del cliente, se actualiza automáticamente el día 5 de cada mes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('client_type');
        });
    }
};
