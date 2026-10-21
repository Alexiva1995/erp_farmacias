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
        Schema::create('purchase_books', function (Blueprint $table) {
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
            $table->decimal('total_purchases_with_vat', 18, 4)->default(0);
            $table->decimal('total_exempt_purchases', 18, 4)->default(0);
            $table->decimal('total_general_taxable_base', 18, 4)->default(0);
            $table->decimal('total_general_vat', 18, 4)->default(0);
            $table->decimal('total_reduced_taxable_base', 18, 4)->default(0);
            $table->decimal('total_reduced_vat', 18, 4)->default(0);
            $table->decimal('total_vat_withheld', 18, 4)->default(0);

            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['year', 'month', 'fortnight']);
            $table->index(['start_date', 'end_date']);
        });

        Schema::create('purchase_book_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_book_id')->constrained('purchase_books')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('retention_id')->nullable()->constrained('retentions')->nullOnDelete();
            
            $table->unsignedInteger('operation_number');
            $table->date('operation_date');
            $table->string('rif')->nullable();
            $table->string('supplier_name')->nullable();
            $table->string('import_planilla_number')->nullable();
            $table->string('import_dossier_number')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('control_number')->nullable();
            $table->string('debit_note_number')->nullable();
            $table->string('credit_note_number')->nullable();
            $table->string('affected_invoice_number')->nullable();
            $table->string('document_type')->default('FACTURA'); // FACTURA | COMPROBANTE DE RETENCION DE IVA | NOTA DE CREDITO | NOTA DE DEBITO
            $table->string('transaction_type')->default('01-REG'); // 01-REG | 05-RET | etc
            
            $table->decimal('total_purchases_with_vat', 18, 4)->default(0);
            $table->decimal('exempt_purchases', 18, 4)->default(0);
            $table->decimal('general_taxable_base', 18, 4)->default(0);
            $table->decimal('general_aliquot', 8, 4)->default(0.16);
            $table->decimal('general_vat', 18, 4)->default(0);
            $table->decimal('reduced_taxable_base', 18, 4)->default(0);
            $table->decimal('reduced_aliquot', 8, 4)->default(0.08);
            $table->decimal('reduced_vat', 18, 4)->default(0);
            $table->decimal('vat_withheld', 18, 4)->default(0);
            $table->string('withholding_receipt_number')->nullable();

            $table->boolean('is_manual_override')->default(false);
            $table->timestamps();

            $table->index(['purchase_book_id', 'operation_number']);
            $table->index('operation_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_book_entries');
        Schema::dropIfExists('purchase_books');
    }
};
