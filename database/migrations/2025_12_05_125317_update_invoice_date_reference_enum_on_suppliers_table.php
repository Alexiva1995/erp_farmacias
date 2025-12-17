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
        DB::statement("ALTER TABLE suppliers CHANGE invoice_date_reference invoice_date_reference ENUM('receipt_date', 'expiration_date', 'issue_date') NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('suppliers')
            ->where('invoice_date_reference', 'issue_date')
            ->update(['invoice_date_reference' => 'receipt_date']);

        DB::statement("ALTER TABLE suppliers CHANGE invoice_date_reference invoice_date_reference ENUM('receipt_date', 'expiration_date') NULL");
    }
};
