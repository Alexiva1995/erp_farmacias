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
        Schema::table('suppliers', function (Blueprint $table) {
            $table->enum('payment_due_type', ['invoice_date', 'early_payment', 'custom'])->default('invoice_date')->after('is_deleted');
            $table->integer('custom_due_days')->nullable()->after(column: 'payment_due_type');
            $table->enum('payment_due_reference', ['receipt_date', 'issue_date'])->nullable()->default('issue_date')->after(column: 'custom_due_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(columns: ['payment_due_type', 'custom_due_days', 'payment_due_reference']);
        });
    }
};
