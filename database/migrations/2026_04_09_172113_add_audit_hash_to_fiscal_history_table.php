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
        Schema::table('fiscal_history', function (Blueprint $table) {
            $table->string('audit_hash', 64)->nullable()->after('is_queued');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fiscal_history', function (Blueprint $table) {
            $table->dropColumn('audit_hash');
        });
    }
};
