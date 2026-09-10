<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Promedio mensual ponderado móvil (últimos 3 meses: 50% M1, 30% M2, 20% M3)
            $table->decimal('sales_average_weighted', 10, 2)
                ->default(0)
                ->after('sales_average');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('sales_average_weighted');
        });
    }
};
