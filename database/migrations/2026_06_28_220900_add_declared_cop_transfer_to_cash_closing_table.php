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
        Schema::table('cash_closing', function (Blueprint $table) {
            $table->decimal('declared_cop_transfer', 15, 2)->default(0.00)->after('declared_cop');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_closing', function (Blueprint $table) {
            $table->dropColumn('declared_cop_transfer');
        });
    }
};
