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
              if (!Schema::hasColumn('cash_closing', 'total_sales')) {
                $table->decimal('total_sales', 15, 2)->default(0.00)->after('status');
              }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_closing', function (Blueprint $table) {
            if (Schema::hasColumn('cash_closing', 'total_sales')) {
               $table->dropColumn('total_sales');
              }
        });
    }
};
