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
            $table->unsignedInteger('quota_tier')->default(1)->after('status');
            $table->integer('points_earned')->default(1)->after('quota_tier');
            $table->string('error_penalty_type', 30)->nullable()->after('points_earned')->comment('false_discrepancy, wrong_discrepancy');
            $table->integer('penalty_points')->default(0)->after('error_penalty_type');
        });

        Schema::table('invoices_counts', function (Blueprint $table) {
            $table->integer('points_earned')->default(2)->after('status');
            $table->string('error_penalty_type', 30)->nullable()->after('points_earned');
            $table->integer('penalty_points')->default(0)->after('error_penalty_type');
        });

        Schema::table('sales_counts', function (Blueprint $table) {
            $table->integer('points_earned')->default(2)->after('status');
            $table->string('error_penalty_type', 30)->nullable()->after('points_earned');
            $table->integer('penalty_points')->default(0)->after('error_penalty_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_counts', function (Blueprint $table) {
            $table->dropColumn(['quota_tier', 'points_earned', 'error_penalty_type', 'penalty_points']);
        });

        Schema::table('invoices_counts', function (Blueprint $table) {
            $table->dropColumn(['points_earned', 'error_penalty_type', 'penalty_points']);
        });

        Schema::table('sales_counts', function (Blueprint $table) {
            $table->dropColumn(['points_earned', 'error_penalty_type', 'penalty_points']);
        });
    }
};
