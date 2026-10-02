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
            if (!Schema::hasColumn('general_settings', 'enabled_supplier_views')) {
                $table->json('enabled_supplier_views')->nullable();
            }
            if (!Schema::hasColumn('general_settings', 'enabled_supplier_types')) {
                $table->json('enabled_supplier_types')->nullable();
            }
            if (!Schema::hasColumn('general_settings', 'supplier_form_fields')) {
                $table->json('supplier_form_fields')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('general_settings', function (Blueprint $table) {
            if (Schema::hasColumn('general_settings', 'enabled_supplier_views')) {
                $table->dropColumn('enabled_supplier_views');
            }
            if (Schema::hasColumn('general_settings', 'enabled_supplier_types')) {
                $table->dropColumn('enabled_supplier_types');
            }
            if (Schema::hasColumn('general_settings', 'supplier_form_fields')) {
                $table->dropColumn('supplier_form_fields');
            }
        });
    }
};
