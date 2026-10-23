<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     */
    public function up(): void
    {
        if (Schema::hasTable('product_suppliers')) {
            Schema::table('product_suppliers', function (Blueprint $table) {
                if (!Schema::hasColumn('product_suppliers', 'credit_days')) {
                    $table->integer('credit_days')->nullable()->after('expiration');
                }
            });
        }

        if (Schema::hasTable('supplier_connections')) {
            Schema::table('supplier_connections', function (Blueprint $table) {
                if (!Schema::hasColumn('supplier_connections', 'credit_days_email')) {
                    $table->string('credit_days_email')->nullable()->after('invoice_structure');
                }
                if (!Schema::hasColumn('supplier_connections', 'credit_days_cod_col')) {
                    $table->string('credit_days_cod_col', 50)->nullable()->after('credit_days_email');
                }
                if (!Schema::hasColumn('supplier_connections', 'credit_days_col')) {
                    $table->string('credit_days_col', 50)->nullable()->after('credit_days_cod_col');
                }
            });
        }
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        if (Schema::hasTable('product_suppliers')) {
            Schema::table('product_suppliers', function (Blueprint $table) {
                if (Schema::hasColumn('product_suppliers', 'credit_days')) {
                    $table->dropColumn('credit_days');
                }
            });
        }

        if (Schema::hasTable('supplier_connections')) {
            Schema::table('supplier_connections', function (Blueprint $table) {
                $cols = [];
                foreach (['credit_days_email', 'credit_days_cod_col', 'credit_days_col'] as $col) {
                    if (Schema::hasColumn('supplier_connections', $col)) {
                        $cols[] = $col;
                    }
                }
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
