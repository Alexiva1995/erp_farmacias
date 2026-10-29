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
        Schema::create('vat_retention_books', function (Blueprint $table) {
            $table->id();
            $table->string('period_type')->default('fortnightly'); // fortnightly | monthly
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedTinyInteger('fortnight')->nullable(); // 1, 2 o null si mensual
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('open'); // open | closed

            // Totales acumulados del período
            $table->unsignedInteger('total_documents')->default(0);
            $table->decimal('total_document_amount', 18, 4)->default(0);
            $table->decimal('total_taxable_base', 18, 4)->default(0);
            $table->decimal('total_tax_amount', 18, 4)->default(0);
            $table->decimal('total_withheld_amount', 18, 4)->default(0);
            $table->decimal('total_exempt_amount', 18, 4)->default(0);

            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['year', 'month', 'fortnight']);
            $table->index(['start_date', 'end_date']);
        });

        Schema::create('vat_retention_book_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vat_retention_book_id')->constrained('vat_retention_books')->cascadeOnDelete();
            $table->foreignId('retention_id')->nullable()->constrained('retentions')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();

            $table->unsignedInteger('operation_number');
            $table->string('agent_rif', 20); // Col 1: RIF de la empresa agente sin guiones
            $table->string('fiscal_period', 10); // Col 2: YYYYMM
            $table->date('document_date'); // Col 3: Fecha de la factura
            $table->string('operation_type', 5)->default('C'); // Col 4: C
            $table->string('document_type', 5)->default('01'); // Col 5: 01, 02, 03
            $table->string('supplier_rif', 20); // Col 6: RIF proveedor limpio
            $table->string('supplier_name', 255)->nullable();
            $table->string('document_number', 100); // Col 7: Factura
            $table->string('control_number', 100)->nullable(); // Col 8: Control
            $table->decimal('total_document_amount', 18, 4)->default(0); // Col 9: Total Doc
            $table->decimal('taxable_base', 18, 4)->default(0); // Col 10: Base
            $table->decimal('tax_amount', 18, 4)->default(0); // IVA factura
            $table->decimal('withheld_amount', 18, 4)->default(0); // Col 11: IVA Retenido
            $table->string('affected_document_number', 100)->default('0'); // Col 12: Doc Afectado
            $table->string('retention_number', 50); // Col 13: Comprobante 14 digitos
            $table->decimal('exempt_amount', 18, 4)->default(0); // Col 14: Exento
            $table->decimal('tax_rate', 8, 2)->default(16.00); // Col 15: Alicuota
            $table->string('import_dossier_number', 50)->default('0'); // Col 16: Expediente

            $table->timestamps();

            $table->index(['vat_retention_book_id']);
            $table->index(['retention_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vat_retention_book_entries');
        Schema::dropIfExists('vat_retention_books');
    }
};
