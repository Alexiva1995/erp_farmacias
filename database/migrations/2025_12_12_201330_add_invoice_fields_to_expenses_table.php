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
        Schema::table('expenses', function (Blueprint $table) {
            if (!Schema::hasColumn('expenses', 'exempt_amount')) {
                $table->decimal('exempt_amount', 15, 2)->nullable()->default(0.00)->after('amount_bs');
            }
            if (!Schema::hasColumn('expenses', 'taxable_base')) {
                $table->decimal('taxable_base', 15, 2)->nullable()->default(0.00)->after('exempt_amount');
            }
            if (!Schema::hasColumn('expenses', 'tax_amount')) {
                $table->decimal('tax_amount', 15, 2)->nullable()->default(0.00)->after('taxable_base');
            }
            if (!Schema::hasColumn('expenses', 'exchange_rate')) {
                $table->decimal('exchange_rate', 10, 4)->nullable()->after('tax_amount');
            }
            if (!Schema::hasColumn('expenses', 'total_usd')) {
                $table->decimal('total_usd', 15, 2)->nullable()->default(0.00)->after('exchange_rate');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (Schema::hasColumn('expenses', 'exempt_amount')) {
                $table->dropColumn('exempt_amount');
            }
            if (Schema::hasColumn('expenses', 'taxable_base')) {
                $table->dropColumn('taxable_base');
            }
            if (Schema::hasColumn('expenses', 'tax_amount')) {
                $table->dropColumn('tax_amount');
            }
            if (Schema::hasColumn('expenses', 'exchange_rate')) {
                $table->dropColumn('exchange_rate');
            }
            if (Schema::hasColumn('expenses', 'total_usd')) {
                $table->dropColumn('total_usd');
            }
        });
    }
};
