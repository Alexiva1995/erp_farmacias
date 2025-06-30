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
        Schema::create('fiscal_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->string('invoice_number', 50);
            $table->string('business_name');
            $table->string('identification', 100);
            $table->string('address')->nullable();
            $table->decimal('exempt_amount', 15, 2)->nullable()->default(0);
            $table->decimal('iva_amount', 15, 2)->nullable()->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->date('invoice_date');

            $table->timestamps();

            $table->index('user_id');
            $table->index('order_id');
            $table->index('invoice_date', 'idx_fiscal_date');
            $table->index('invoice_number', 'idx_fiscal_invoice');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fiscal_history');
    }
};
