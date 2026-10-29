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
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (!Schema::hasColumn('products', 'credit_days')) {
                    $table->integer('credit_days')->nullable()->after('unit_cost');
                }
                if (!Schema::hasColumn('products', 'allow_early_payment_discount')) {
                    $table->boolean('allow_early_payment_discount')->default(true)->after('credit_days');
                }
                if (!Schema::hasColumn('products', 'max_units_per_order')) {
                    $table->integer('max_units_per_order')->nullable()->after('allow_early_payment_discount');
                }
                if (!Schema::hasColumn('products', 'max_units_per_client_day')) {
                    $table->integer('max_units_per_client_day')->nullable()->after('max_units_per_order');
                }
                if (!Schema::hasColumn('products', 'max_units_per_client_month')) {
                    $table->integer('max_units_per_client_month')->nullable()->after('max_units_per_client_day');
                }
                if (!Schema::hasColumn('products', 'min_wholesale_units')) {
                    $table->integer('min_wholesale_units')->default(1)->after('max_units_per_client_month');
                }
                if (!Schema::hasColumn('products', 'units_per_inner_pack')) {
                    $table->integer('units_per_inner_pack')->nullable()->after('min_wholesale_units');
                }
                if (!Schema::hasColumn('products', 'units_per_master_box')) {
                    $table->integer('units_per_master_box')->nullable()->after('units_per_inner_pack');
                }
                if (!Schema::hasColumn('products', 'requires_cold_chain')) {
                    $table->boolean('requires_cold_chain')->default(false)->after('units_per_master_box');
                }
                if (!Schema::hasColumn('products', 'sanitary_registration')) {
                    $table->string('sanitary_registration', 100)->nullable()->after('requires_cold_chain');
                }
            });
        }

        if (Schema::hasTable('laboratories')) {
            Schema::table('laboratories', function (Blueprint $table) {
                if (!Schema::hasColumn('laboratories', 'default_credit_days')) {
                    $table->integer('default_credit_days')->default(0)->after('description');
                }
                if (!Schema::hasColumn('laboratories', 'commercial_discount_pct')) {
                    $table->decimal('commercial_discount_pct', 5, 2)->default(0)->after('default_credit_days');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                $columns = [
                    'credit_days',
                    'allow_early_payment_discount',
                    'max_units_per_order',
                    'max_units_per_client_day',
                    'max_units_per_client_month',
                    'min_wholesale_units',
                    'units_per_inner_pack',
                    'units_per_master_box',
                    'requires_cold_chain',
                    'sanitary_registration',
                ];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('products', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('laboratories')) {
            Schema::table('laboratories', function (Blueprint $table) {
                $columns = [
                    'default_credit_days',
                    'commercial_discount_pct',
                ];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('laboratories', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
