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
        Schema::create('fiscal_history_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_history_id')->constrained('fiscal_history')->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained('products')->onDelete('cascade');
            $table->string('product_name');
            $table->integer('quantity');
            $table->boolean('vat_status')->default(0)->comment('0 = sin IVA, 1 = con IVA');

            $table->decimal('exempt_amount', 15, 2)->default(0);
            $table->decimal('iva_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);

            $table->timestamps();

            $table->index('fiscal_history_id', 'idx_fiscal_detail_history');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fiscal_history_details');
    }
};
