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
        Schema::create('supplier_laboratories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('cascade');
            $table->foreignId('laboratory_id')->constrained('laboratories')->onDelete('cascade');
            $table->string('phone', 100)->nullable();
            $table->timestamps();

            $table->unique(['supplier_id', 'laboratory_id'], 'uniq_supplier_lab');
            $table->index('laboratory_id', 'laboratory_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_laboratories');
    }
};
