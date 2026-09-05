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
        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE suppliers MODIFY COLUMN payment_due_reference ENUM('receipt_date', 'issue_date') NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\DB::table('suppliers')
            ->whereNull('payment_due_reference')
            ->update(['payment_due_reference' => 'issue_date']);

        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE suppliers MODIFY COLUMN payment_due_reference ENUM('receipt_date', 'issue_date') NOT NULL DEFAULT 'issue_date'");
        }
    }
};
