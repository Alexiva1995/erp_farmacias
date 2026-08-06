<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE invoices MODIFY COLUMN status ENUM('pending', 'loaded', 'to_order', 'ordered', 'deleted') NOT NULL DEFAULT 'pending'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE invoices MODIFY COLUMN status ENUM('pending', 'loaded', 'to_order', 'ordered') NOT NULL DEFAULT 'pending'");
        }
    }
};
