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
        Schema::create('inventory_books', function (Blueprint $table) {
            $table->id();
            $table->string('period_type')->default('fortnightly'); // fortnightly | monthly
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedTinyInteger('fortnight')->nullable(); // 1, 2 o null si mensual
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('open'); // open | closed
            $table->boolean('include_negative_adjustments_as_withdrawals')->default(true);

            // Totales acumulados en Unidades
            $table->decimal('total_initial_units', 18, 4)->default(0);
            $table->decimal('total_purchase_units', 18, 4)->default(0);
            $table->decimal('total_sales_units', 18, 4)->default(0);
            $table->decimal('total_withdrawal_units', 18, 4)->default(0);
            $table->decimal('total_self_consumption_units', 18, 4)->default(0);
            $table->decimal('total_final_units', 18, 4)->default(0);

            // Totales acumulados en Bolívares
            $table->decimal('total_previous_value_bs', 18, 4)->default(0);
            $table->decimal('total_purchase_cost_bs', 18, 4)->default(0);
            $table->decimal('total_sales_cost_bs', 18, 4)->default(0);
            $table->decimal('total_withdrawal_cost_bs', 18, 4)->default(0);
            $table->decimal('total_self_consumption_cost_bs', 18, 4)->default(0);
            $table->decimal('total_final_inventory_value_bs', 18, 4)->default(0);

            // Resumen de Desincorporación y Débito Fiscal IVA por Retiros
            $table->decimal('total_withdrawal_exempt_bs', 18, 4)->default(0);
            $table->decimal('total_withdrawal_taxable_bs', 18, 4)->default(0);
            $table->decimal('total_withdrawal_vat_bs', 18, 4)->default(0);

            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['year', 'month', 'fortnight']);
            $table->index(['start_date', 'end_date']);
        });

        Schema::create('inventory_book_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_book_id')->constrained('inventory_books')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            
            $table->unsignedInteger('operation_number');
            $table->string('product_code');
            $table->string('product_description');
            $table->decimal('unit_cost_bs', 18, 4)->default(0);

            // Unidades
            $table->decimal('initial_stock', 14, 2)->default(0);
            $table->decimal('purchase_entries', 14, 2)->default(0);
            $table->decimal('sales_exits', 14, 2)->default(0);
            $table->decimal('withdrawals', 14, 2)->default(0);
            $table->decimal('self_consumptions', 14, 2)->default(0);
            $table->decimal('final_stock', 14, 2)->default(0);

            // Bolívares
            $table->decimal('previous_value_bs', 18, 4)->default(0);
            $table->decimal('purchase_entries_cost_bs', 18, 4)->default(0);
            $table->decimal('sales_exits_cost_bs', 18, 4)->default(0);
            $table->decimal('withdrawals_cost_bs', 18, 4)->default(0);
            $table->decimal('self_consumptions_cost_bs', 18, 4)->default(0);
            $table->decimal('final_inventory_value_bs', 18, 4)->default(0);

            // Datos fiscales y desincorporación
            $table->decimal('iva_rate', 8, 4)->default(0); // 0.16 o 0.00
            $table->decimal('withdrawal_vat_bs', 18, 4)->default(0);
            $table->string('withdrawal_reason')->nullable();
            $table->boolean('is_manual_override')->default(false);

            $table->timestamps();

            $table->index(['inventory_book_id', 'operation_number']);
            $table->index(['inventory_book_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_book_entries');
        Schema::dropIfExists('inventory_books');
    }
};
