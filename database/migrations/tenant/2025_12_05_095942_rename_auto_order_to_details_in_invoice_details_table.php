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
        Schema::table('invoice_details', function (Blueprint $table) {
            $table->dropForeign(['auto_order_id']);
            $table->dropIndex(['auto_order_id']);
            $table->dropColumn('auto_order_id');

            $table->foreignId('auto_order_details_id')->nullable()->after('product_id')->constrained('auto_order_details')->nullOnDelete();
            $table->index('auto_order_details_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_details', function (Blueprint $table) {
            $table->dropForeign(['auto_order_details_id']);
            $table->dropIndex(['auto_order_details_id']);
            $table->dropColumn('auto_order_details_id');

            $table->foreignId('auto_order_id')->nullable()->after('product_id')->constrained('auto_orders')->nullOnDelete();
            $table->index('auto_order_id');
        });
    }
};
