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
        DB::statement("ALTER TABLE general_settings MODIFY COLUMN business_type ENUM('pharmacy', 'restaurant', 'sports_rental') NOT NULL DEFAULT 'pharmacy'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE general_settings MODIFY COLUMN business_type ENUM('pharmacy', 'restaurant') NOT NULL DEFAULT 'pharmacy'");
    }
};
