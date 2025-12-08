<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * ISSUE #3: Agregar campo is_indexed para facturas indexadas
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Agregar campo is_indexed después de currency
            $table->boolean('is_indexed')->default(false)->after('currency')
                ->comment('Indica si la factura está indexada (Bs = USD × tasa BCV)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('is_indexed');
        });
    }
};
