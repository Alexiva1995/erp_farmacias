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
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('loan_id')->nullable()->constrained('loans')->onDelete('cascade');
        });

        // Insertar categoría de Pagos de Préstamos si no existe
        \Illuminate\Support\Facades\DB::table('expense_categories')->updateOrInsert(
            ['name' => 'Pagos de Préstamos'],
            ['created_at' => now(), 'updated_at' => now()]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['loan_id']);
            $table->dropColumn('loan_id');
        });
    }
};
