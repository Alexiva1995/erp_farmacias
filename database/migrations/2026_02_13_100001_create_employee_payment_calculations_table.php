<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('employee_payment_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('total_package_usd', 12, 2);
            $table->decimal('salario_base', 12, 2)->default(40);
            $table->decimal('bono_alimentacion', 12, 2)->default(40);
            $table->decimal('consumo_farmacia_actual', 12, 2)->default(0);
            $table->decimal('saldo_deuda_anterior', 12, 2)->default(0);
            $table->decimal('disponible_para_incentivo', 12, 2);
            $table->decimal('consumo_total_a_descontar', 12, 2);
            $table->decimal('incentivo_metas', 12, 2);
            $table->decimal('nuevo_saldo_deuda', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['employee_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_payment_calculations');
    }
};
