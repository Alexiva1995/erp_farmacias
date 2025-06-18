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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('active_ingredient')->nullable()->default(null);

            // Llaves Foráneas (asegúrate que las tablas referenciadas existan)
            $table->foreignId('laboratory_id')->nullable()->constrained('laboratories')->default(null);
            $table->foreignId('origin_id')->nullable()->constrained('origins')->default(null);
            $table->foreignId('category_id')->nullable()->constrained('categories')->default(null);
            // $table->foreignId('group_id')->nullable()->constrained('groups_products')->default(null);

            $table->decimal('cost_price', 10, 2);
            $table->decimal('sale_price', 10, 2);
            $table->boolean('iva')->default(false)->comment('0 = No, 1 = Si');
            $table->boolean('from_colombia')->default(false);
            $table->boolean('psychotropic')->default(false)->comment('0 = No, 1 = Si');

            $table->string('barcode')->unique();
            $table->string('photo_url')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
