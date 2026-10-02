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
        // 1. Crear tabla de flujos de proceso
        Schema::create('process_flows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Crear tabla de fases para cada flujo
        Schema::create('process_flow_phases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flow_id')->constrained('process_flows')->onDelete('cascade');
            $table->string('name');
            $table->string('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // 3. Modificar o recrear la tabla de auditorías para hacerla dinámica
        Schema::dropIfExists('process_audits');
        Schema::create('process_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flow_id')->constrained('process_flows')->onDelete('restrict');
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('cascade');
            $table->foreignId('cashier_id')->nullable()->constrained('employees')->onDelete('restrict');
            $table->foreignId('cook_id')->constrained('employees')->onDelete('restrict');
            $table->integer('total_seconds')->default(0);
            $table->timestamps();
        });

        // 4. Crear tabla relacional de tiempos medidos en cada fase para una auditoría
        Schema::create('process_audit_phases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_audit_id')->constrained('process_audits')->onDelete('cascade');
            $table->foreignId('flow_phase_id')->constrained('process_flow_phases')->onDelete('restrict');
            $table->integer('seconds')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('process_audit_phases');
        Schema::dropIfExists('process_audits');
        Schema::dropIfExists('process_flow_phases');
        Schema::dropIfExists('process_flows');
    }
};
