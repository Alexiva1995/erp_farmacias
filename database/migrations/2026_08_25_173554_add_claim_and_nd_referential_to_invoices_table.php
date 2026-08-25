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
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('claim_amount', 12, 2)->default(0)->after('total_amount_discount');
            $table->decimal('nd_referential_amount', 12, 2)->default(0)->after('claim_amount');
            $table->decimal('net_payable_amount', 12, 2)->nullable()->after('nd_referential_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['claim_amount', 'nd_referential_amount', 'net_payable_amount']);
        });
    }
};
