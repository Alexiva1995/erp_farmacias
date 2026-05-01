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
            $table->string('app_name')->nullable();
            $table->string('app_rif')->nullable();
            $table->string('app_logo')->nullable();
            $table->string('app_favicon')->nullable();
            $table->string('primary_color')->default('#7367F0');
            $table->string('secondary_color')->default('#82868B');
            $table->string('footer_text')->default('Todos los derechos reservados de Tova');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('general_settings', function (Blueprint $table) {
            $table->dropColumn([
                'app_name',
                'app_rif',
                'app_logo',
                'app_favicon',
                'primary_color',
                'secondary_color',
                'footer_text'
            ]);
        });
    }
};
