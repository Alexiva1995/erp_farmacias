<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones para agregar auditoría de anulaciones,
     * eliminación lógica y trazabilidad de vueltos en créditos.
     */
    public function up(): void
    {
        if (Schema::hasTable('credits')) {
            Schema::table('credits', function (Blueprint $table) {
                if (!Schema::hasColumn('credits', 'voided_by')) {
                    $table->unsignedBigInteger('voided_by')->nullable()->index()->after('collector_user_id');
                }
                if (!Schema::hasColumn('credits', 'void_reason')) {
                    $table->string('void_reason', 255)->nullable()->after('voided_by');
                }
                if (!Schema::hasColumn('credits', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        if (Schema::hasTable('credit_payments')) {
            Schema::table('credit_payments', function (Blueprint $table) {
                if (!Schema::hasColumn('credit_payments', 'change_amount_usd')) {
                    $table->decimal('change_amount_usd', 14, 2)->default(0)->after('money_returns');
                }
                if (!Schema::hasColumn('credit_payments', 'change_amount_cop')) {
                    $table->decimal('change_amount_cop', 14, 2)->default(0)->after('change_amount_usd');
                }
                if (!Schema::hasColumn('credit_payments', 'voided_by')) {
                    $table->unsignedBigInteger('voided_by')->nullable()->index()->after('rejection_reason');
                }
                if (!Schema::hasColumn('credit_payments', 'voided_at')) {
                    $table->timestamp('voided_at')->nullable()->after('voided_by');
                }
                if (!Schema::hasColumn('credit_payments', 'void_reason')) {
                    $table->string('void_reason', 255)->nullable()->after('voided_at');
                }
            });
        }
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        if (Schema::hasTable('credits')) {
            Schema::table('credits', function (Blueprint $table) {
                foreach (['voided_by', 'void_reason', 'deleted_at'] as $column) {
                    if (Schema::hasColumn('credits', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('credit_payments')) {
            Schema::table('credit_payments', function (Blueprint $table) {
                foreach (['change_amount_usd', 'change_amount_cop', 'voided_by', 'voided_at', 'void_reason'] as $column) {
                    if (Schema::hasColumn('credit_payments', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
