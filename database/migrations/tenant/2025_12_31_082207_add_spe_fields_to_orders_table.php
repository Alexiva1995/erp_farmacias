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
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('taxable_base', 15, 2)->default(0.00)->after('total_cost')->comment('Monto de la orden donde se le aplica el % de Recargo spe');
            $table->decimal('spe_surcharge_rate', 5, 2)->default(0.00)->after('taxable_base')->comment('El % aplicado Recargo Sujeto Pasivo Especial');
            $table->decimal('spe_surcharge_amount', 15, 2)->default(0.00)->after('spe_surcharge_rate')->comment('Monto del Recargo Sujeto Pasivo Especial');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['base_imponible', 'tasa_recargo_spe', 'monto_recargo_spe']);
        });
    }
};
