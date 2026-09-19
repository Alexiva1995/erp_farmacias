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
        Schema::create('fiscal_z_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('report_number')->unique()->index()->comment('Número consecutivo de Reporte Z');
            $table->date('report_date')->index()->comment('Fecha del corte fiscal');
            $table->time('opening_time')->nullable()->default('08:00:00')->comment('Hora de inicio de operaciones');
            $table->time('closing_time')->nullable()->default('23:59:59')->comment('Hora de emisión del Reporte Z');
            $table->string('first_invoice_number', 50)->nullable()->comment('Primera factura del día');
            $table->string('last_invoice_number', 50)->nullable()->comment('Última factura del día');
            $table->unsignedInteger('invoices_count')->default(0)->comment('Total facturas emitidas en el día');
            $table->decimal('exempt_amount', 15, 2)->default(0.00)->comment('Monto Exento en Bs');
            $table->decimal('base_16_amount', 15, 2)->default(0.00)->comment('Base Imponible General 16% (Big 16.00%)');
            $table->decimal('iva_amount', 15, 2)->default(0.00)->comment('IVA General 16% (IVA G)');
            $table->decimal('igtf_base_amount', 15, 2)->default(0.00)->comment('Base Imponible IGTF / SPE');
            $table->decimal('igtf_amount', 15, 2)->default(0.00)->comment('Monto IGTF 3% / SPE');
            $table->decimal('total_amount', 15, 2)->default(0.00)->comment('Total Bs (Exento + Base 16 + IVA + IGTF)');
            $table->string('status', 30)->default('closed')->comment('Estado del reporte');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fiscal_z_reports');
    }
};
