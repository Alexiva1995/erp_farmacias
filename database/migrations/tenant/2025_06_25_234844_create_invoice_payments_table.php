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
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->date('payment_date');
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 100)->nullable();
            $table->string('reference', 100)->nullable();
            $table->enum('status', ['paid', 'unpaid'])->default('unpaid');
            $table->foreignId('payment_by')->constrained('users')->onDelete('cascade');
            $table->string('photo_url')->nullable();
            $table->timestamps();

            $table->index('payment_date', 'idx_payment_date');
            $table->index('status', 'idx_payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};
