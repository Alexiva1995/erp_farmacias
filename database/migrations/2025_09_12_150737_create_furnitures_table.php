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
        Schema::create('furnitures', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('cost', 12, 2);
            $table->year('acquisition_year');
            $table->decimal('annual_depreciation_rate', 5, 2)->comment('Porcentaje anual de depreciación (ej: 10.00 = 10%)');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('furniture');
    }
};
