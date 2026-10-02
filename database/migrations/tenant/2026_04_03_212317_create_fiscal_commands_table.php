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
        Schema::create('fiscal_commands', function (Blueprint $table) {
            $table->id();
            $table->string('command'); // REPORT_Z, REPORT_X, ANNUL_INVOICE, REPRINT_INVOICE
            $table->json('payload')->nullable(); // Para el nro de factura, etc.
            $table->enum('status', ['pending', 'success', 'error'])->default('pending');
            $table->text('response')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fiscal_commands');
    }
};
