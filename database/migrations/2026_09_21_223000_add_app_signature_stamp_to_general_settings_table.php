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
        Schema::table('general_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('general_settings', 'app_signature_stamp')) {
                $table->string('app_signature_stamp')->nullable()->after('app_favicon')->comment('Ruta de la imagen de firma y sello digital del agente de retención');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('general_settings', function (Blueprint $table) {
            if (Schema::hasColumn('general_settings', 'app_signature_stamp')) {
                $table->dropColumn('app_signature_stamp');
            }
        });
    }
};
