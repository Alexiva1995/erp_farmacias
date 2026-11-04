<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('fiscal_history') && !Schema::hasColumn('fiscal_history', 'spe')) {
            Schema::table('fiscal_history', function (Blueprint $table) {
                $table->boolean('spe')->default(false)->after('iva_amount');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('fiscal_history') && Schema::hasColumn('fiscal_history', 'spe')) {
            Schema::table('fiscal_history', function (Blueprint $table) {
                $table->dropColumn('spe');
            });
        }
    }
};
