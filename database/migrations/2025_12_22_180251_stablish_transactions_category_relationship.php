<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Primero eliminamos la foránea anterior si existe
            // Usamos try-catch o comprobación si es necesario, pero dropForeign suele fallar si no existe en algunos drivers.
            // Dado el error, sabemos que EXISTE.
            $table->dropForeign(['category_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->change();

            $table->foreign('category_id')
                ->references('id')
                ->on('expense_categories')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->onDelete('set null');
        });
    }
};
