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
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'is_corporate_admin')) {
                    $table->boolean('is_corporate_admin')->default(false)->after('role_id');
                }
                if (!Schema::hasColumn('users', 'allowed_branch_ids')) {
                    $table->json('allowed_branch_ids')->nullable()->after('is_corporate_admin');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'is_corporate_admin')) {
                    $table->dropColumn('is_corporate_admin');
                }
                if (Schema::hasColumn('users', 'allowed_branch_ids')) {
                    $table->dropColumn('allowed_branch_ids');
                }
            });
        }
    }
};
