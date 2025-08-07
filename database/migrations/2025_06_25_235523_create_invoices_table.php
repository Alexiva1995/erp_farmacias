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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('cascade');
            $table->foreignId('auto_order_id')->nullable()->constrained('auto_orders')->nullOnDelete();
            $table->string('invoice_number', 100)->unique();
            $table->string('control_number', 100);
            $table->date('exp_date');
            $table->date('payment_date');
            $table->date('received_date');
            $table->enum('currency', ['Bs', 'USD', 'COP']);
            $table->foreignId('discount_rule_id')->nullable()->constrained('discount_rules')->nullOnDelete();
            $table->decimal('exempt_amount', 12, 2)->nullable()->default(0.00);
            $table->decimal('taxable_base', 12, 2)->nullable()->default(0.00);
            $table->decimal('tax_amount', 12, 2)->nullable()->default(0.00);
            $table->decimal('total_amount', 12, 2);
            $table->decimal('exchange_rate', 10, 4)->nullable();
            $table->decimal('total_usd', 12, 2);
            $table->enum('status', ['loaded', 'to_order', 'ordered'])->default('loaded');
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('registered_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('ordered_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('ordered_by');
            $table->index('registered_by');
            $table->index('uploaded_by');
            $table->index('discount_rule_id');
            $table->index('auto_order_id');
            $table->index('supplier_id', 'idx_invoice_supplier');
            $table->index('status', 'idx_invoice_status');
            $table->index('received_date', 'idx_received_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
