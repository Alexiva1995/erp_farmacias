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
        if (DB::getDriverName() !== 'sqlite') {
            // Fix para expense_categories
            DB::statement('ALTER TABLE `expense_categories` MODIFY `id` bigint unsigned NOT NULL AUTO_INCREMENT');

            // Fix para expenses
            DB::statement('ALTER TABLE `expenses` MODIFY `id` bigint unsigned NOT NULL AUTO_INCREMENT');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
