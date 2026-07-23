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
        Schema::table('ecommerce_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('ecommerce_orders', 'customer_document_type')) {
                $table->string('customer_document_type', 10)->nullable()->after('customer_phone');
            }
            if (!Schema::hasColumn('ecommerce_orders', 'customer_document_number')) {
                $table->string('customer_document_number', 30)->nullable()->after('customer_document_type');
            }
            if (!Schema::hasColumn('ecommerce_orders', 'currency')) {
                $table->string('currency', 10)->default('USD')->after('total_amount');
            }
            if (!Schema::hasColumn('ecommerce_orders', 'total_in_currency')) {
                $table->decimal('total_in_currency', 12, 2)->nullable()->after('currency');
            }
            if (!Schema::hasColumn('ecommerce_orders', 'tpv_order_id')) {
                $table->unsignedBigInteger('tpv_order_id')->nullable()->after('total_in_currency');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ecommerce_orders', function (Blueprint $table) {
            $columnsToDrop = [];
            foreach ([
                'customer_document_type',
                'customer_document_number',
                'currency',
                'total_in_currency'
            ] as $column) {
                if (Schema::hasColumn('ecommerce_orders', $column)) {
                    $columnsToDrop[] = $column;
                }
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
