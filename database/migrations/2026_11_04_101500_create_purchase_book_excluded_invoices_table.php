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
        if (!Schema::hasTable('purchase_book_excluded_invoices')) {
            Schema::create('purchase_book_excluded_invoices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_book_id')->constrained('purchase_books')->cascadeOnDelete();
                $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
                $table->string('invoice_number')->nullable();
                $table->string('control_number')->nullable();
                $table->foreignId('retention_id')->nullable()->constrained('retentions')->nullOnDelete();
                $table->timestamps();

                $table->index(['purchase_book_id', 'invoice_id'], 'idx_pbei_book_invoice');
                $table->index(['purchase_book_id', 'invoice_number'], 'idx_pbei_book_number');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_book_excluded_invoices');
    }
};
