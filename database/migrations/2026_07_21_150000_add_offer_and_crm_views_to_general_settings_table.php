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
        Schema::table('general_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('general_settings', 'enabled_offer_types')) {
                $table->json('enabled_offer_types')->nullable();
            }
            if (!Schema::hasColumn('general_settings', 'enabled_crm_views')) {
                $table->json('enabled_crm_views')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('general_settings', function (Blueprint $table) {
            if (Schema::hasColumn('general_settings', 'enabled_offer_types')) {
                $table->dropColumn('enabled_offer_types');
            }
            if (Schema::hasColumn('general_settings', 'enabled_crm_views')) {
                $table->dropColumn('enabled_crm_views');
            }
        });
    }
};
