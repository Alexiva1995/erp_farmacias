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
        Schema::table('auto_orders', function (Blueprint $table) {
            $table->timestamp('sent_at')->nullable()->after('status');
            $table->date('tentative_delivery_date')->nullable()->after('sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auto_orders', function (Blueprint $table) {
            $table->dropColumn(['sent_at', 'tentative_delivery_date']);
        });
    }
};
