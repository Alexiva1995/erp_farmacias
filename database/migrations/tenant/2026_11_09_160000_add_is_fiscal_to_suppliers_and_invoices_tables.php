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
        if (Schema::hasTable('suppliers') && !Schema::hasColumn('suppliers', 'is_fiscal')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->boolean('is_fiscal')->default(true)->after('rif')->comment('Indica si el proveedor emite facturación fiscal formal');
            });
        }

        if (Schema::hasTable('invoices') && !Schema::hasColumn('invoices', 'is_fiscal')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->boolean('is_fiscal')->default(true)->after('currency')->comment('Indica si la factura es un documento fiscal o compra informal');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('suppliers') && Schema::hasColumn('suppliers', 'is_fiscal')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->dropColumn('is_fiscal');
            });
        }

        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'is_fiscal')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('is_fiscal');
            });
        }
    }
};
