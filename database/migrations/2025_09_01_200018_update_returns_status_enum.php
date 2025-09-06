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
        Schema::table('returns', function (Blueprint $table) {
            DB::statement("ALTER TABLE returns CHANGE COLUMN status status ENUM('Created', 'Approved') DEFAULT 'Created'");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            DB::statement("ALTER TABLE returns CHANGE COLUMN status status ENUM('Active', 'Paid') DEFAULT 'Active'");
        });
    }
};
