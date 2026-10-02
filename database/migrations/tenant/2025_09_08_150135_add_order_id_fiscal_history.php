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
          Schema::table('fiscal_history', function (Blueprint $table) {
              if (!Schema::hasColumn('fiscal_history', 'order_id')) {
                $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('cascade')->after('user_id');
              }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         Schema::table('fiscal_history', function (Blueprint $table) {
             if (Schema::hasColumn('fiscal_history', 'order_id')) {
                $table->dropForeign(['order_id']);
                $table->dropColumn('order_id');
            }
        });
    }
};
