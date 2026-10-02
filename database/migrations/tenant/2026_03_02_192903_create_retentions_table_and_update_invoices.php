<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Asegurar PK en suppliers
        try {
            DB::statement('ALTER TABLE suppliers MODIFY id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY');
        } catch (\Exception $e) {}

        // 2. Limpiar restos
        Schema::dropIfExists('retentions');

        // 3. Crear tabla base
        Schema::create('retentions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id')->index();
            $table->string('number')->unique();
            $table->date('date');
            $table->decimal('total_taxable_base', 15, 2);
            $table->decimal('total_tax_amount', 15, 2);
            $table->decimal('total_withheld_amount', 15, 2);
            $table->decimal('retention_percentage', 5, 2)->default(75);
            $table->timestamps();
        });

        // 4. Intentar FKs por separado (Opcional)
        try {
            Schema::table('retentions', function (Blueprint $table) {
                $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');
            });
        } catch (\Exception $e) {
            \Log::warning("FK failed in retentions: " . $e->getMessage());
        }

        // 5. Invoices
        if (!Schema::hasColumn('invoices', 'retention_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->unsignedBigInteger('retention_id')->nullable()->after('retention_generated')->index();
            });
        }

        try {
            Schema::table('invoices', function (Blueprint $table) {
                $table->foreign('retention_id')->references('id')->on('retentions')->onDelete('set null');
            });
        } catch (\Exception $e) {
            \Log::warning("FK failed in invoices->retention_id: " . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['retention_id']);
            $table->dropColumn('retention_id');
        });

        Schema::dropIfExists('retentions');
    }
};
