<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE clients MODIFY COLUMN client_type ENUM('Nuevo', 'Ocasional', 'Frecuente', 'VIP', 'En Riesgo', 'Inactivo') DEFAULT 'Nuevo'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE clients MODIFY COLUMN client_type ENUM('Nuevo', 'Ocasional', 'Frecuente', 'VIP', 'En Riesgo') DEFAULT 'Nuevo'");
        }
    }
};
