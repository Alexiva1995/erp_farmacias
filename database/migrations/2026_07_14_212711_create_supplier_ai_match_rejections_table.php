<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_ai_match_rejections', function (Blueprint $table) {
            $table->id();
            // Producto del sistema que fue rechazado
            $table->unsignedBigInteger('product_id')->index();
            // Producto del proveedor que Gemini sugirió y fue rechazado
            $table->unsignedBigInteger('product_supplier_id')->index();
            // Quién rechazó (usuario)
            $table->unsignedBigInteger('rejected_by')->nullable();
            // Motivo del rechazo (opcional, para mejorar el prompt futuro)
            $table->string('reason')->nullable();
            $table->timestamps();

            // Evitar duplicar el mismo rechazo (nombre de índice abreviado por límite MySQL)
            $table->unique(['product_id', 'product_supplier_id'], 'ai_rejection_unique');

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('product_supplier_id')->references('id')->on('product_suppliers')->onDelete('cascade');
        });

        // Agregar columna no_match_possible a products para marcar que no vale la pena seguir intentando
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('no_ai_match_possible')->default(false)->after('sales_average_updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('no_ai_match_possible');
        });
        Schema::dropIfExists('supplier_ai_match_rejections');
    }
};
