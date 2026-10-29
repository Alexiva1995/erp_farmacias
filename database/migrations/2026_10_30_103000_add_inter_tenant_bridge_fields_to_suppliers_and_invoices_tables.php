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
        if (Schema::hasTable('suppliers')) {
            Schema::table('suppliers', function (Blueprint $table) {
                if (!Schema::hasColumn('suppliers', 'provider_tenant_id')) {
                    $table->string('provider_tenant_id', 100)->nullable()->index()->after('rif');
                }
            });
        }

        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (!Schema::hasColumn('invoices', 'source_drogueria_tenant_id')) {
                    $table->string('source_drogueria_tenant_id', 100)->nullable()->index()->after('supplier_id');
                }
                if (!Schema::hasColumn('invoices', 'source_drogueria_order_id')) {
                    $table->unsignedBigInteger('source_drogueria_order_id')->nullable()->index()->after('source_drogueria_tenant_id');
                }
            });
        }

        if (Schema::hasTable('invoice_payments')) {
            Schema::table('invoice_payments', function (Blueprint $table) {
                if (!Schema::hasColumn('invoice_payments', 'sync_status')) {
                    $table->string('sync_status', 40)->default('not_applicable')->index()->after('status');
                }
                if (!Schema::hasColumn('invoice_payments', 'remote_credit_payment_id')) {
                    $table->unsignedBigInteger('remote_credit_payment_id')->nullable()->index()->after('sync_status');
                }
                if (!Schema::hasColumn('invoice_payments', 'rejection_reason')) {
                    $table->text('rejection_reason')->nullable()->after('remote_credit_payment_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('suppliers')) {
            Schema::table('suppliers', function (Blueprint $table) {
                if (Schema::hasColumn('suppliers', 'provider_tenant_id')) {
                    $table->dropColumn('provider_tenant_id');
                }
            });
        }

        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (Schema::hasColumn('invoices', 'source_drogueria_tenant_id')) {
                    $table->dropColumn('source_drogueria_tenant_id');
                }
                if (Schema::hasColumn('invoices', 'source_drogueria_order_id')) {
                    $table->dropColumn('source_drogueria_order_id');
                }
            });
        }

        if (Schema::hasTable('invoice_payments')) {
            Schema::table('invoice_payments', function (Blueprint $table) {
                if (Schema::hasColumn('invoice_payments', 'sync_status')) {
                    $table->dropColumn('sync_status');
                }
                if (Schema::hasColumn('invoice_payments', 'remote_credit_payment_id')) {
                    $table->dropColumn('remote_credit_payment_id');
                }
                if (Schema::hasColumn('invoice_payments', 'rejection_reason')) {
                    $table->dropColumn('rejection_reason');
                }
            });
        }
    }
};
