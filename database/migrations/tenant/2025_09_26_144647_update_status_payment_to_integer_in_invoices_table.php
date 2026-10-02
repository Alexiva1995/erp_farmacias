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
        // Primero actualizar los datos existentes
        DB::statement("UPDATE invoices SET status_payment = CASE 
            WHEN status_payment = 'paid' THEN 1
            WHEN status_payment = 'unpaid' OR status_payment = '' OR status_payment IS NULL THEN 0
            ELSE 0
        END");

        // Cambiar el tipo de columna a integer
        Schema::table('invoices', function (Blueprint $table) {
            $table->integer('status_payment')->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir los datos a strings
        DB::statement("UPDATE invoices SET status_payment = CASE 
            WHEN status_payment = 1 THEN 'paid'
            WHEN status_payment = 0 THEN 'unpaid'
            ELSE 'unpaid'
        END");

        // Revertir el tipo de columna a string
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('status_payment')->nullable()->change();
        });
    }
};
