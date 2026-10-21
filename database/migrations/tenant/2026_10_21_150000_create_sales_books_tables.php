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
        Schema::create('sales_books', function (Blueprint $table) {
            $table->id();
            $table->string('period_type')->default('fortnightly'); // fortnightly | monthly
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedTinyInteger('fortnight')->nullable(); // 1, 2 o null si mensual
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('open'); // open | closed
            $table->boolean('enable_zoom_snapshot')->default(false);
            
            // Totales acumulados
            $table->decimal('total_sales_with_vat', 18, 4)->default(0);
            $table->decimal('total_non_taxable_sales', 18, 4)->default(0);
            $table->decimal('total_taxpayer_taxable_base', 18, 4)->default(0);
            $table->decimal('total_taxpayer_vat', 18, 4)->default(0);
            $table->decimal('total_non_taxpayer_taxable_base', 18, 4)->default(0);
            $table->decimal('total_non_taxpayer_vat', 18, 4)->default(0);
            $table->decimal('total_vat_withheld', 18, 4)->default(0);
            $table->decimal('total_igtf', 18, 4)->default(0);

            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['year', 'month', 'fortnight']);
            $table->index(['start_date', 'end_date']);
        });

        Schema::create('sales_book_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_book_id')->constrained('sales_books')->cascadeOnDelete();
            $table->foreignId('fiscal_z_report_id')->nullable()->constrained('fiscal_z_reports')->nullOnDelete();
            $table->foreignId('fiscal_command_id')->nullable()->constrained('fiscal_commands')->nullOnDelete();
            
            $table->unsignedInteger('operation_number');
            $table->date('operation_date');
            $table->string('identification')->nullable(); // 0 para Reporte Z, o RIF cliente
            $table->string('client_name')->nullable(); // CLIENTES VARIOS o nombre del cliente
            $table->string('export_planilla_number')->nullable();
            $table->string('document_type')->default('REPORTE Z'); // REPORTE Z | NOTA DE CRÉDITO | COMPROBANTE DE RETENCIÓN DE IVA
            $table->string('invoice_number')->nullable();
            $table->string('control_number')->nullable();
            $table->string('z_report_number')->nullable();
            $table->string('z_first_invoice')->nullable();
            $table->string('z_last_invoice')->nullable();
            $table->string('machine_serial')->nullable();
            $table->string('debit_note_number')->nullable();
            $table->string('credit_note_number')->nullable();
            $table->string('affected_invoice_number')->nullable();
            $table->string('transaction_type')->default('01-REGISTRO'); // 01-REGISTRO | 03-REGISTRO | 05-RETENCIÓN
            
            $table->decimal('total_sales_with_vat', 18, 4)->default(0);
            $table->decimal('non_taxable_sales', 18, 4)->default(0);
            
            // Bloque 1: Ventas a Contribuyentes / General 16%
            $table->decimal('taxpayer_taxable_base', 18, 4)->default(0);
            $table->decimal('taxpayer_aliquot', 8, 4)->default(0.16);
            $table->decimal('taxpayer_vat', 18, 4)->default(0);
            
            // Bloque 2: Ventas a No Contribuyentes / Zoom 8%
            $table->decimal('non_taxpayer_taxable_base', 18, 4)->default(0);
            $table->decimal('non_taxpayer_aliquot', 8, 4)->default(0.08);
            $table->decimal('non_taxpayer_vat', 18, 4)->default(0);
            
            $table->decimal('vat_withheld', 18, 4)->default(0);
            $table->string('withholding_receipt_number')->nullable();
            $table->date('withholding_receipt_date')->nullable();
            $table->decimal('igtf_amount', 18, 4)->default(0);

            $table->boolean('is_manual_override')->default(false);
            $table->timestamps();

            $table->index(['sales_book_id', 'operation_number']);
            $table->index('operation_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_book_entries');
        Schema::dropIfExists('sales_books');
    }
};
