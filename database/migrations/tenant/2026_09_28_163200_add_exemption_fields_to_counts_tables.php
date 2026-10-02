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
        Schema::table('product_counts', function (Blueprint $table) {
            $table->unsignedInteger('exempt_quantity')->default(0)->after('discrepancy');
            $table->string('exemption_reason', 255)->nullable()->after('exempt_quantity');
            $table->foreignId('exempted_by_id')->nullable()->after('exemption_reason')->constrained('users')->nullOnDelete();
        });

        Schema::table('invoices_counts', function (Blueprint $table) {
            $table->unsignedInteger('exempt_quantity')->default(0)->after('correction_difference');
            $table->string('exemption_reason', 255)->nullable()->after('exempt_quantity');
            $table->foreignId('exempted_by_id')->nullable()->after('exemption_reason')->constrained('users')->nullOnDelete();
        });

        Schema::table('sales_counts', function (Blueprint $table) {
            $table->unsignedInteger('exempt_quantity')->default(0)->after('correction_difference');
            $table->string('exemption_reason', 255)->nullable()->after('exempt_quantity');
            $table->foreignId('exempted_by_id')->nullable()->after('exemption_reason')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_counts', function (Blueprint $table) {
            $table->dropForeign(['exempted_by_id']);
            $table->dropColumn(['exempt_quantity', 'exemption_reason', 'exempted_by_id']);
        });

        Schema::table('invoices_counts', function (Blueprint $table) {
            $table->dropForeign(['exempted_by_id']);
            $table->dropColumn(['exempt_quantity', 'exemption_reason', 'exempted_by_id']);
        });

        Schema::table('sales_counts', function (Blueprint $table) {
            $table->dropForeign(['exempted_by_id']);
            $table->dropColumn(['exempt_quantity', 'exemption_reason', 'exempted_by_id']);
        });
    }
};
