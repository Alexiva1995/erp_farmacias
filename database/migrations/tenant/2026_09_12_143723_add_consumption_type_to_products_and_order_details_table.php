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
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'consumption_type')) {
                // chronic, single_treatment, sporadic
                $table->string('consumption_type', 30)->default('sporadic')->after('is_chronic')->index();
            }
        });

        Schema::table('order_details', function (Blueprint $table) {
            if (!Schema::hasColumn('order_details', 'consumption_type')) {
                $table->string('consumption_type', 30)->nullable()->after('product_type')->index();
            }
            if (!Schema::hasColumn('order_details', 'treatment_duration_days')) {
                $table->integer('treatment_duration_days')->nullable()->after('consumption_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'consumption_type')) {
                $table->dropColumn('consumption_type');
            }
        });

        Schema::table('order_details', function (Blueprint $table) {
            if (Schema::hasColumn('order_details', 'treatment_duration_days')) {
                $table->dropColumn('treatment_duration_days');
            }
            if (Schema::hasColumn('order_details', 'consumption_type')) {
                $table->dropColumn('consumption_type');
            }
        });
    }
};