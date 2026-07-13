<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->decimal('discount_percentage', 5, 2)->nullable()->after('unit_cost');
            $table->string('discount_type')->nullable()->after('discount_percentage'); // 'doctor', 'company', 'prescription'
            $table->unsignedBigInteger('discount_source_id')->nullable()->after('discount_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->dropColumn(['discount_percentage', 'discount_type', 'discount_source_id']);
        });
    }
};
