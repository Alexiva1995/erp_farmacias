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
        Schema::table('invoice_details', function (Blueprint $table) {
            if (!Schema::hasColumn('invoice_details', 'display_order')) {
                $table->integer('display_order')->nullable()->default(null)->after('id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_details', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_details', 'display_order')) {
                $table->dropColumn('display_order');
            }
        });
    }
};
