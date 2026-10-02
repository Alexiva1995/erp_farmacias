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
        Schema::table('invoices', function (Blueprint $table) {
            // Campos que no vienen en el header y deben ser nullable
            $table->date('payment_date')->nullable()->change();
            $table->date('received_date')->nullable()->change();
            $table->decimal('total_usd', 12, 2)->nullable()->change();

            // Establecer valor por defecto para currency
            $table->enum('currency', ['Bs', 'USD', 'COP'])->default('Bs')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->date('payment_date')->nullable(false)->change();
            $table->date('received_date')->nullable(false)->change();
            $table->decimal('total_usd', 12, 2)->nullable(false)->change();

            $table->enum('currency', ['Bs', 'USD', 'COP'])->default(null)->change();
        });
    }
};
