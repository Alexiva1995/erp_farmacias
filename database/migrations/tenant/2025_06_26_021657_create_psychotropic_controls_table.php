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
        Schema::create('psychotropic_controls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->date('movement_date');
            $table->enum('movement_type', ['purchase', 'sale']);
            $table->integer('quantity');
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade');
            $table->string('photo_url')->nullable();
            $table->timestamps();

            $table->index('product_id');
            $table->index('supplier_id');
            $table->index('user_id');
            $table->index('invoice_id', 'fk_psychotropic_controls_invoice_id');
            $table->index('order_id', 'fk_psychotropic_controls_order_id');
            $table->index('movement_date', 'idx_psych_date');
            $table->index('movement_type', 'idx_psych_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('psychotropic_controls');
    }
};
