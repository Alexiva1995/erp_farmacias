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
        if (Schema::hasTable('credits')) {
            Schema::table('credits', function (Blueprint $table) {
                if (!Schema::hasColumn('credits', 'invoice_number')) {
                    $table->string('invoice_number', 50)->nullable()->index()->after('order_id');
                }
                if (!Schema::hasColumn('credits', 'control_number')) {
                    $table->string('control_number', 50)->nullable()->after('invoice_number');
                }
                if (!Schema::hasColumn('credits', 'exchange_rate_issue')) {
                    $table->decimal('exchange_rate_issue', 14, 4)->nullable()->after('control_number');
                }
                if (!Schema::hasColumn('credits', 'is_indexed')) {
                    $table->boolean('is_indexed')->default(true)->after('exchange_rate_issue');
                }
                if (!Schema::hasColumn('credits', 'early_payment_discount_pct')) {
                    $table->decimal('early_payment_discount_pct', 5, 2)->default(0)->after('is_indexed');
                }
                if (!Schema::hasColumn('credits', 'early_payment_expires_at')) {
                    $table->date('early_payment_expires_at')->nullable()->after('early_payment_discount_pct');
                }
                if (!Schema::hasColumn('credits', 'withheld_iva_amount_usd')) {
                    $table->decimal('withheld_iva_amount_usd', 14, 2)->default(0)->after('early_payment_expires_at');
                }
                if (!Schema::hasColumn('credits', 'withheld_islr_amount_usd')) {
                    $table->decimal('withheld_islr_amount_usd', 14, 2)->default(0)->after('withheld_iva_amount_usd');
                }
                if (!Schema::hasColumn('credits', 'sales_rep_id')) {
                    $table->unsignedBigInteger('sales_rep_id')->nullable()->index()->after('withheld_islr_amount_usd');
                }
                if (!Schema::hasColumn('credits', 'collector_user_id')) {
                    $table->unsignedBigInteger('collector_user_id')->nullable()->index()->after('sales_rep_id');
                }
            });
        }

        if (Schema::hasTable('credit_payments')) {
            Schema::table('credit_payments', function (Blueprint $table) {
                if (!Schema::hasColumn('credit_payments', 'status')) {
                    $table->string('status', 30)->default('approved')->index()->after('payment_date');
                }
                if (!Schema::hasColumn('credit_payments', 'credit_id')) {
                    $table->unsignedBigInteger('credit_id')->nullable()->index()->after('status');
                }
                if (!Schema::hasColumn('credit_payments', 'reference_number')) {
                    $table->string('reference_number', 100)->nullable()->after('credit_id');
                }
                if (!Schema::hasColumn('credit_payments', 'bank_name')) {
                    $table->string('bank_name', 100)->nullable()->after('reference_number');
                }
                if (!Schema::hasColumn('credit_payments', 'receipt_photo_url')) {
                    $table->string('receipt_photo_url', 255)->nullable()->after('bank_name');
                }
                if (!Schema::hasColumn('credit_payments', 'exchange_rate_applied')) {
                    $table->decimal('exchange_rate_applied', 14, 4)->nullable()->after('receipt_photo_url');
                }
                if (!Schema::hasColumn('credit_payments', 'amount_usd_equivalent')) {
                    $table->decimal('amount_usd_equivalent', 14, 2)->default(0)->after('exchange_rate_applied');
                }
                if (!Schema::hasColumn('credit_payments', 'source_channel')) {
                    $table->string('source_channel', 50)->default('internal')->index()->after('amount_usd_equivalent');
                }
                if (!Schema::hasColumn('credit_payments', 'external_tenant_id')) {
                    $table->string('external_tenant_id', 100)->nullable()->index()->after('source_channel');
                }
                if (!Schema::hasColumn('credit_payments', 'external_invoice_payment_id')) {
                    $table->unsignedBigInteger('external_invoice_payment_id')->nullable()->index()->after('external_tenant_id');
                }
                if (!Schema::hasColumn('credit_payments', 'verified_by')) {
                    $table->unsignedBigInteger('verified_by')->nullable()->after('external_invoice_payment_id');
                }
                if (!Schema::hasColumn('credit_payments', 'verified_at')) {
                    $table->timestamp('verified_at')->nullable()->after('verified_by');
                }
                if (!Schema::hasColumn('credit_payments', 'rejection_reason')) {
                    $table->text('rejection_reason')->nullable()->after('verified_at');
                }
                if (!Schema::hasColumn('credit_payments', 'withheld_iva_amount')) {
                    $table->decimal('withheld_iva_amount', 14, 2)->default(0)->after('rejection_reason');
                }
                if (!Schema::hasColumn('credit_payments', 'withheld_islr_amount')) {
                    $table->decimal('withheld_islr_amount', 14, 2)->default(0)->after('withheld_iva_amount');
                }
                if (!Schema::hasColumn('credit_payments', 'retention_voucher_number')) {
                    $table->string('retention_voucher_number', 100)->nullable()->after('withheld_islr_amount');
                }
                if (!Schema::hasColumn('credit_payments', 'retention_voucher_photo_url')) {
                    $table->string('retention_voucher_photo_url', 255)->nullable()->after('retention_voucher_number');
                }
            });
        }

        if (!Schema::hasTable('credit_payment_allocations')) {
            Schema::create('credit_payment_allocations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('credit_payment_id')->index();
                $table->unsignedBigInteger('credit_id')->index();
                $table->decimal('amount_allocated_usd', 14, 2)->default(0);
                $table->decimal('amount_allocated_original', 14, 2)->default(0);
                $table->string('currency', 10)->default('USD');
                $table->decimal('early_payment_discount_applied', 14, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_payment_allocations');

        if (Schema::hasTable('credits')) {
            Schema::table('credits', function (Blueprint $table) {
                $columns = [
                    'invoice_number',
                    'control_number',
                    'exchange_rate_issue',
                    'is_indexed',
                    'early_payment_discount_pct',
                    'early_payment_expires_at',
                    'withheld_iva_amount_usd',
                    'withheld_islr_amount_usd',
                    'sales_rep_id',
                    'collector_user_id',
                ];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('credits', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('credit_payments')) {
            Schema::table('credit_payments', function (Blueprint $table) {
                $columns = [
                    'status',
                    'credit_id',
                    'reference_number',
                    'bank_name',
                    'receipt_photo_url',
                    'exchange_rate_applied',
                    'amount_usd_equivalent',
                    'source_channel',
                    'external_tenant_id',
                    'external_invoice_payment_id',
                    'verified_by',
                    'verified_at',
                    'rejection_reason',
                    'withheld_iva_amount',
                    'withheld_islr_amount',
                    'retention_voucher_number',
                    'retention_voucher_photo_url',
                ];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('credit_payments', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
