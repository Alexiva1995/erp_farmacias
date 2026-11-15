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
        if (Schema::hasTable('suppliers') && !Schema::hasColumn('suppliers', 'logo_url')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->string('logo_url', 500)
                    ->nullable()
                    ->after('name')
                    ->comment('URL o ruta del logotipo oficial del proveedor gestionado desde el Master');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('suppliers') && Schema::hasColumn('suppliers', 'logo_url')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->dropColumn('logo_url');
            });
        }
    }
};
