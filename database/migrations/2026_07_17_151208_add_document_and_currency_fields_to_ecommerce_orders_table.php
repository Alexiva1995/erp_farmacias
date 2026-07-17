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
            $table->string('customer_document_type', 10)->nullable()->after('customer_phone');
            $table->string('customer_document_number', 30)->nullable()->after('customer_document_type');
            $table->string('currency', 10)->default('USD')->after('total_amount');
            $table->decimal('total_in_currency', 12, 2)->nullable()->after('currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ecommerce_orders', function (Blueprint $table) {
            $table->dropColumn([
                'customer_document_type',
                'customer_document_number',
                'currency',
                'total_in_currency'
            ]);
        });
    }
};
