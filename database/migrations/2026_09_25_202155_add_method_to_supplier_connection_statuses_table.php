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
        Schema::table('supplier_connection_statuses', function (Blueprint $table) {
            if (!Schema::hasColumn('supplier_connection_statuses', 'method')) {
                $table->string('method', 50)->nullable()->after('user_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_connection_statuses', function (Blueprint $table) {
            if (Schema::hasColumn('supplier_connection_statuses', 'method')) {
                $table->dropColumn('method');
            }
        });
    }
};
