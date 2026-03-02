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
        // Forzar AUTO_INCREMENT en tablas críticas que presentan el error 1364
        
        // Fix para invoice_payments
        DB::statement('ALTER TABLE `invoice_payments` MODIFY `id` bigint unsigned NOT NULL AUTO_INCREMENT');
        
        // Fix para transactions
        DB::statement('ALTER TABLE `transactions` MODIFY `id` bigint unsigned NOT NULL AUTO_INCREMENT');

        // Fix para expense_categories
        DB::statement('ALTER TABLE `expense_categories` MODIFY `id` bigint unsigned NOT NULL AUTO_INCREMENT');

        // Fix para expenses
        DB::statement('ALTER TABLE `expenses` MODIFY `id` bigint unsigned NOT NULL AUTO_INCREMENT');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No revertimos AUTO_INCREMENT pues id siempre debe tenerlo
    }
};
