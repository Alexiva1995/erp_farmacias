<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auto_orders', function (Blueprint $table) {
            $table->string('hash_token', 64)->nullable()->unique()->after('status');
        });

        Schema::table('auto_order_details', function (Blueprint $table) {
            $table->boolean('supplier_confirmed')->nullable()->after('received');
            $table->string('supplier_rejected_reason')->nullable()->after('supplier_confirmed');
        });
    }

    public function down(): void
    {
        Schema::table('auto_order_details', function (Blueprint $table) {
            $table->dropColumn(['supplier_confirmed', 'supplier_rejected_reason']);
        });

        Schema::table('auto_orders', function (Blueprint $table) {
            $table->dropColumn('hash_token');
        });
    }
};
