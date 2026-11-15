<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('general_settings') && !Schema::hasColumn('general_settings', 'show_product_photos')) {
            Schema::table('general_settings', function (Blueprint $table) {
                $table->boolean('show_product_photos')->default(false)->after('enable_product_types');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('general_settings') && Schema::hasColumn('general_settings', 'show_product_photos')) {
            Schema::table('general_settings', function (Blueprint $table) {
                $table->dropColumn('show_product_photos');
            });
        }
    }
};
