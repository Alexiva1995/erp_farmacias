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
        Schema::table('company_offer_scales', function (Blueprint $table) {
            $table->dropIndex('idx_scale_volume');
            $table->dropColumn(['min_volume', 'max_volume']);
            $table->decimal('min_amount', 15, 2)
                ->after('company_offer_id')
                ->default(0)
                ->comment('Monto mínimo en USD');

            $table->decimal('max_amount', 15, 2)
                ->after('min_amount')
                ->nullable()
                ->comment('Monto máximo en USD (null si no tiene límite)');

            $table->index(['min_amount', 'max_amount'], 'idx_scale_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_offer_scales', function (Blueprint $table) {
            $table->dropIndex('idx_scale_amount');
            $table->dropColumn(['min_amount', 'max_amount']);
            
            $table->integer('min_volume')->after('company_offer_id');
            $table->integer('max_volume')->after('min_volume')->nullable();
            $table->index(['min_volume', 'max_volume'], 'idx_scale_volume');
        });
    }
};
