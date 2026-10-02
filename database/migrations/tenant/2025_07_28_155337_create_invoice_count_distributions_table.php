<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('invoice_count_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_count_id')
                ->constrained('invoices_counts')
                ->onDelete('cascade');
            $table->foreignId('product_lot_id')
                ->constrained('product_lots')
                ->onDelete('cascade');
            $table->integer('quantity');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_count_distributions');
    }
};
