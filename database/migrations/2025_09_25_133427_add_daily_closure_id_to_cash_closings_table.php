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
             if (!Schema::hasColumn('cash_closing', 'daily_closure_id')) {
                $table->foreignId('daily_closure_id')->nullable()->constrained('daily_closures');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_closing', function (Blueprint $table) {
              if (Schema::hasColumn('cash_closing', 'daily_closure_id')) {
                 $table->dropForeign(['daily_closure_id']);
                 $table->dropColumn('daily_closure_id');
            }
        });
    }
};
