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
            $table->boolean('enable_quotations')->default(true)->after('enable_dishes');
            $table->enum('quotation_style', ['pharmacy', 'restaurant'])->default('pharmacy')->after('enable_quotations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('general_settings', function (Blueprint $table) {
            $table->dropColumn(['enable_quotations', 'quotation_style']);
        });
    }
};
