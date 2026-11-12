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
        if (Schema::hasTable('tenants')) {
            Schema::table('tenants', function (Blueprint $table) {
                if (!Schema::hasColumn('tenants', 'parent_tenant_id')) {
                    $table->string('parent_tenant_id', 100)->nullable()->index()->after('id');
                }
                if (!Schema::hasColumn('tenants', 'company_name')) {
                    $table->string('company_name', 150)->nullable()->after('parent_tenant_id');
                }
                if (!Schema::hasColumn('tenants', 'branch_code')) {
                    $table->string('branch_code', 30)->nullable()->after('company_name');
                }
                if (!Schema::hasColumn('tenants', 'branch_address')) {
                    $table->string('branch_address', 255)->nullable()->after('branch_code');
                }
                if (!Schema::hasColumn('tenants', 'branch_phone')) {
                    $table->string('branch_phone', 50)->nullable()->after('branch_address');
                }
                if (!Schema::hasColumn('tenants', 'can_create_branches')) {
                    $table->boolean('can_create_branches')->default(false)->after('branch_phone');
                }
                if (!Schema::hasColumn('tenants', 'max_branches')) {
                    $table->unsignedInteger('max_branches')->default(5)->after('can_create_branches');
                }
                if (!Schema::hasColumn('tenants', 'is_branch_active')) {
                    $table->boolean('is_branch_active')->default(true)->after('max_branches');
                }
                if (!Schema::hasColumn('tenants', 'vertical')) {
                    $table->string('vertical', 50)->nullable()->after('is_branch_active');
                }
                if (!Schema::hasColumn('tenants', 'plan_id')) {
                    $table->string('plan_id', 50)->nullable()->after('vertical');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('tenants')) {
            Schema::table('tenants', function (Blueprint $table) {
                $columns = [
                    'parent_tenant_id',
                    'branch_code',
                    'branch_address',
                    'branch_phone',
                    'can_create_branches',
                    'max_branches',
                    'is_branch_active',
                ];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('tenants', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
