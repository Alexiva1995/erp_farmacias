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
        if (Schema::hasTable('clients')) {
            Schema::table('clients', function (Blueprint $table) {
                if (!Schema::hasColumn('clients', 'sales_rep_id')) {
                    $table->unsignedBigInteger('sales_rep_id')->nullable()->index()->after('company_id');
                }
                if (!Schema::hasColumn('clients', 'telemarketing_user_id')) {
                    $table->unsignedBigInteger('telemarketing_user_id')->nullable()->index()->after('sales_rep_id');
                }
                if (!Schema::hasColumn('clients', 'collector_user_id')) {
                    $table->unsignedBigInteger('collector_user_id')->nullable()->index()->after('telemarketing_user_id');
                }
                if (!Schema::hasColumn('clients', 'credit_limit_usd')) {
                    $table->decimal('credit_limit_usd', 14, 2)->default(0)->after('balance');
                }
                if (!Schema::hasColumn('clients', 'default_credit_days')) {
                    $table->integer('default_credit_days')->default(0)->after('credit_limit_usd');
                }
                if (!Schema::hasColumn('clients', 'max_monthly_purchase_limit_usd')) {
                    $table->decimal('max_monthly_purchase_limit_usd', 14, 2)->nullable()->after('default_credit_days');
                }
                if (!Schema::hasColumn('clients', 'max_order_units_limit')) {
                    $table->integer('max_order_units_limit')->nullable()->after('max_monthly_purchase_limit_usd');
                }
                if (!Schema::hasColumn('clients', 'block_on_overdue')) {
                    $table->boolean('block_on_overdue')->default(true)->after('max_order_units_limit');
                }
                if (!Schema::hasColumn('clients', 'early_payment_discount_pct')) {
                    $table->decimal('early_payment_discount_pct', 5, 2)->default(0)->after('block_on_overdue');
                }
                if (!Schema::hasColumn('clients', 'commercial_discount_pct')) {
                    $table->decimal('commercial_discount_pct', 5, 2)->default(0)->after('early_payment_discount_pct');
                }
                if (!Schema::hasColumn('clients', 'sicm_code')) {
                    $table->string('sicm_code', 50)->nullable()->after('commercial_discount_pct');
                }
                if (!Schema::hasColumn('clients', 'sacs_permit_number')) {
                    $table->string('sacs_permit_number', 100)->nullable()->after('sicm_code');
                }
                if (!Schema::hasColumn('clients', 'sacs_permit_expires_at')) {
                    $table->date('sacs_permit_expires_at')->nullable()->after('sacs_permit_number');
                }
                if (!Schema::hasColumn('clients', 'regent_pharmacist_name')) {
                    $table->string('regent_pharmacist_name', 150)->nullable()->after('sacs_permit_expires_at');
                }
                if (!Schema::hasColumn('clients', 'regent_pharmacist_ci')) {
                    $table->string('regent_pharmacist_ci', 30)->nullable()->after('regent_pharmacist_name');
                }
                if (!Schema::hasColumn('clients', 'regent_mpps_code')) {
                    $table->string('regent_mpps_code', 50)->nullable()->after('regent_pharmacist_ci');
                }
                if (!Schema::hasColumn('clients', 'can_buy_psychotropics')) {
                    $table->boolean('can_buy_psychotropics')->default(false)->after('regent_mpps_code');
                }
                if (!Schema::hasColumn('clients', 'delivery_route_id')) {
                    $table->unsignedBigInteger('delivery_route_id')->nullable()->index()->after('can_buy_psychotropics');
                }
                if (!Schema::hasColumn('clients', 'linked_tenant_id')) {
                    $table->string('linked_tenant_id', 100)->nullable()->index()->after('delivery_route_id');
                }
            });
        }

        if (Schema::hasTable('companies')) {
            Schema::table('companies', function (Blueprint $table) {
                if (!Schema::hasColumn('companies', 'sales_rep_id')) {
                    $table->unsignedBigInteger('sales_rep_id')->nullable()->index()->after('type_company');
                }
                if (!Schema::hasColumn('companies', 'telemarketing_user_id')) {
                    $table->unsignedBigInteger('telemarketing_user_id')->nullable()->index()->after('sales_rep_id');
                }
                if (!Schema::hasColumn('companies', 'collector_user_id')) {
                    $table->unsignedBigInteger('collector_user_id')->nullable()->index()->after('telemarketing_user_id');
                }
                if (!Schema::hasColumn('companies', 'credit_limit_usd')) {
                    $table->decimal('credit_limit_usd', 14, 2)->default(0)->after('collector_user_id');
                }
                if (!Schema::hasColumn('companies', 'default_credit_days')) {
                    $table->integer('default_credit_days')->default(0)->after('credit_limit_usd');
                }
                if (!Schema::hasColumn('companies', 'block_on_overdue')) {
                    $table->boolean('block_on_overdue')->default(true)->after('default_credit_days');
                }
                if (!Schema::hasColumn('companies', 'sicm_code')) {
                    $table->string('sicm_code', 50)->nullable()->after('block_on_overdue');
                }
                if (!Schema::hasColumn('companies', 'sacs_permit_number')) {
                    $table->string('sacs_permit_number', 100)->nullable()->after('sicm_code');
                }
                if (!Schema::hasColumn('companies', 'linked_tenant_id')) {
                    $table->string('linked_tenant_id', 100)->nullable()->index()->after('sacs_permit_number');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('clients')) {
            Schema::table('clients', function (Blueprint $table) {
                $columns = [
                    'sales_rep_id',
                    'telemarketing_user_id',
                    'collector_user_id',
                    'credit_limit_usd',
                    'default_credit_days',
                    'max_monthly_purchase_limit_usd',
                    'max_order_units_limit',
                    'block_on_overdue',
                    'early_payment_discount_pct',
                    'commercial_discount_pct',
                    'sicm_code',
                    'sacs_permit_number',
                    'sacs_permit_expires_at',
                    'regent_pharmacist_name',
                    'regent_pharmacist_ci',
                    'regent_mpps_code',
                    'can_buy_psychotropics',
                    'delivery_route_id',
                    'linked_tenant_id',
                ];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('clients', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('companies')) {
            Schema::table('companies', function (Blueprint $table) {
                $columns = [
                    'sales_rep_id',
                    'telemarketing_user_id',
                    'collector_user_id',
                    'credit_limit_usd',
                    'default_credit_days',
                    'block_on_overdue',
                    'sicm_code',
                    'sacs_permit_number',
                    'linked_tenant_id',
                ];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('companies', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
