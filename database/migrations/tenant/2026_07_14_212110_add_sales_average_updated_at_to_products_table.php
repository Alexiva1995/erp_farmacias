<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Timestamp de la última actualización de sales_average para detectar datos obsoletos
            $table->timestamp('sales_average_updated_at')
                ->nullable()
                ->after('sales_average');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('sales_average_updated_at');
        });
    }
};
