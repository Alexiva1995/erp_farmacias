<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega las columnas faltantes de nómina quincenal y actualiza el índice único.
     */
    public function up(): void
    {
        if (!Schema::hasTable('employee_payment_calculations')) {
            return;
        }

        Schema::table('employee_payment_calculations', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_payment_calculations', 'fortnight')) {
                $table->unsignedTinyInteger('fortnight')->default(1)->after('month');
            }
            if (!Schema::hasColumn('employee_payment_calculations', 'beneficio_salud')) {
                $table->decimal('beneficio_salud', 12, 2)->default(0)->after('consumo_total_a_descontar');
            }
            if (!Schema::hasColumn('employee_payment_calculations', 'deduccion_ivss')) {
                $table->decimal('deduccion_ivss', 12, 2)->default(0)->after('nuevo_saldo_deuda');
            }
            if (!Schema::hasColumn('employee_payment_calculations', 'deduccion_rpe')) {
                $table->decimal('deduccion_rpe', 12, 2)->default(0)->after('deduccion_ivss');
            }
            if (!Schema::hasColumn('employee_payment_calculations', 'deduccion_faov')) {
                $table->decimal('deduccion_faov', 12, 2)->default(0)->after('deduccion_rpe');
            }
        });

        // Crear primero el índice único que incluye fortnight para respaldar la FK de employee_id en MySQL
        try {
            Schema::table('employee_payment_calculations', function (Blueprint $table) {
                $table->unique(
                    ['employee_id', 'year', 'month', 'fortnight'],
                    'emp_pay_calc_emp_year_month_fortnight_unique'
                );
            });
        } catch (\Throwable $e) {
            // Ignorar si ya existe el índice compuesto
        }

        try {
            Schema::table('employee_payment_calculations', function (Blueprint $table) {
                $table->dropUnique('employee_payment_calculations_employee_id_year_month_unique');
            });
        } catch (\Throwable $e) {
            // Ignorar si el índice anterior no existe
        }
    }

    /**
     * Revierte los cambios agregados a employee_payment_calculations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('employee_payment_calculations')) {
            return;
        }

        try {
            Schema::table('employee_payment_calculations', function (Blueprint $table) {
                $table->unique(['employee_id', 'year', 'month']);
            });
        } catch (\Throwable $e) {
        }

        try {
            Schema::table('employee_payment_calculations', function (Blueprint $table) {
                $table->dropUnique('emp_pay_calc_emp_year_month_fortnight_unique');
            });
        } catch (\Throwable $e) {
        }

        $columns = array_values(array_filter(
            ['fortnight', 'beneficio_salud', 'deduccion_ivss', 'deduccion_rpe', 'deduccion_faov'],
            fn (string $column) => Schema::hasColumn('employee_payment_calculations', $column)
        ));

        if (!empty($columns)) {
            Schema::table('employee_payment_calculations', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
