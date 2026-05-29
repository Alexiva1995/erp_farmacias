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
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("
                ALTER TABLE suppliers 
                MODIFY COLUMN payment_due_type 
                ENUM('invoice_date', 'early_payment', 'custom') 
                NOT NULL
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("
                ALTER TABLE suppliers 
                MODIFY COLUMN payment_due_type 
                ENUM('invoice_date', 'early_payment', 'expiration_date', 'custom') 
                NOT NULL
            ");
        }
    }
};
