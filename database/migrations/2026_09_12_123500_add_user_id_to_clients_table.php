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
        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('company_id')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->index('user_id');
            $table->index('updated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['updated_by']);
            $table->dropColumn(['user_id', 'updated_by']);
        });
    }
};
