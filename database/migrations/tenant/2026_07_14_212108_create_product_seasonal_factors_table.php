<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_seasonal_factors', function (Blueprint $table) {
            $table->id();
            // null = factor global (aplica a todos los grupos sin factor propio)
            $table->unsignedBigInteger('group_id')->nullable()->index();
            // Mes del año: 1 (enero) - 12 (diciembre)
            $table->tinyInteger('month')->unsigned();
            // Factor multiplicador: 1.0 = sin ajuste, 1.4 = 40% más demanda
            $table->decimal('factor', 5, 2)->default(1.00);
            $table->timestamps();

            // Un grupo solo puede tener un factor por mes
            $table->unique(['group_id', 'month']);

            $table->foreign('group_id')
                ->references('id')
                ->on('groups_products')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_seasonal_factors');
    }
};
