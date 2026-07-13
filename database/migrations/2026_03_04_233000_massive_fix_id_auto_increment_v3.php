<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Tablas detectadas que requieren fix (excluyendo 'sessions' que usa string id)
        $tablesToFix = [
            'employees',
            'social_benefits',
            'supplier_connections',
            'supplier_discounts',
            'supplier_payment_methods',
            'supplier_ratings',
            'supplier_scores',
            'suppliers_config_products',
            'tax_credits',
            'tax_units',
            'user_config',
            'vat_reports'
        ];

        foreach ($tablesToFix as $tableName) {
            try {
                // MySQL 1075 error correction: Force PRIMARY KEY and AUTO_INCREMENT together
                // We use BIGINT UNSIGNED as it's the standard for modern Laravel migrations
                DB::statement("ALTER TABLE `{$tableName}` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY");
                Log::info("AUTO_INCREMENT y PRIMARY KEY restaurados en: {$tableName}");
            } catch (\Exception $e) {
                // If it fails because PK already exists, try only AUTO_INCREMENT
                try {
                    DB::statement("ALTER TABLE `{$tableName}` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
                    Log::info("AUTO_INCREMENT restaurado en: {$tableName} (PK ya existía)");
                } catch (\Exception $e2) {
                    Log::error("Error crítico al aplicar fix en {$tableName}: " . $e2->getMessage());
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No revertimos fixes estructurales
    }
};
