<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auto_replenishment_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);

            // Parámetros de análisis
            $table->enum('tipo_filtracion', ['average', 'sales', 'combinado'])->default('average');
            $table->string('lapso_de_tiempo', 20)->default('1 month');
            $table->decimal('min_solicitar', 10, 2)->default(1);
            $table->boolean('con_descuento')->default(false);
            $table->string('stock_filter', 20)->default('fallas');

            // Alcance
            $table->unsignedBigInteger('supplier_id')->nullable()->index();
            $table->json('group_ids')->nullable();

            // Programación (expresión cron estándar)
            $table->string('schedule_expression', 50)->default('0 6 * * *');

            // Control de ejecución
            $table->timestamp('last_run_at')->nullable();
            $table->integer('last_run_products')->nullable();
            $table->integer('last_run_orders')->nullable();

            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auto_replenishment_configs');
    }
};
