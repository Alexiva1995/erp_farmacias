<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_name');
            $table->string('social_reason')->nullable();
            $table->string('sales_phone', 50)->nullable();
            $table->string('collections_phone', 50)->nullable();
            $table->integer('credit_days')->nullable()->comment('Si es nulo, la factura define los días');
            $table->json('dispatch_days')->nullable();
            $table->json('order_days')->nullable();
            $table->enum('payment_method', ['Bs', 'Divisas'])->default('Bs');
            $table->boolean('cash_payment')->default(false);
            $table->boolean('charges_igtf')->default(false);
            $table->decimal('rating', 5, 2)->default(0.00);

            $table->softDeletes();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
