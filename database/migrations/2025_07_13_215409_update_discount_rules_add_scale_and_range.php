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
        Schema::table('discount_rules', function (Blueprint $table) {
            $table->enum('scale_type', ['units', 'amount'])->after('supplier_laboratory_id')->default('units');

            $table->decimal('max_amount', 12, 2)->nullable()->after('min_amount');
            $table->integer('max_quantity')->nullable()->after('min_quantity');

            $table->index('max_amount', 'idx_discount_max_amount');
            $table->index('max_quantity', 'idx_discount_max_quantity');
            $table->index('scale_type', 'idx_discount_scale_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
