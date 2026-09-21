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
        Schema::table('fiscal_z_reports', function (Blueprint $table) {
            $table->string('image_path')->nullable()->comment('Ruta de la imagen subida del Reporte Z');
            $table->text('ai_verification_notes')->nullable()->comment('Notas o resultado de la IA');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fiscal_z_reports', function (Blueprint $table) {
            $table->dropColumn(['image_path', 'ai_verification_notes']);
        });
    }
};
