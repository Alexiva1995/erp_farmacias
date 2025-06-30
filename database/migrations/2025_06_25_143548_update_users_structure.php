<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'username')) {
                $table->string('username')->unique()->after('id');
            }

            if (Schema::hasColumn('users', 'password')) {
                $table->renameColumn('password', 'password_hash');
            }

            $table->string('email')->nullable()->change();
            $table->boolean('is_active')->nullable()->default(1)->change();

            $columnsToDrop = [
                'first_name', 'last_name', 'cedula', 'email_verified_at', 'photo',
                'token_login', 'salary', 'currency_salary', 'active_product_units',
                'remember_token', 'is_admin'
            ];

            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }

            $table->index('is_active', 'idx_user_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_user_active');

            $table->dropColumn('username');

            if (Schema::hasColumn('users', 'password_hash')) {
                $table->renameColumn('password_hash', 'password');
            }

            $table->string('email')->nullable(false)->change();
            $table->unique('email', 'users_email_unique');

            $table->boolean('is_active')->nullable(false)->default(1)->change();
            
            $table->string('first_name')->after('id');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('cedula')->nullable()->after('last_name');
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->string('photo')->nullable()->after('password');
            $table->boolean('is_admin')->default(0)->after('photo');
            $table->string('token_login')->nullable()->after('is_admin');
            $table->decimal('salary', 15, 2)->nullable()->after('token_login');
            $table->string('currency_salary')->nullable()->after('salary');
            $table->string('active_product_units')->nullable()->after('currency_salary');
            $table->string('remember_token', 100)->nullable()->after('active_product_units');

            $table->unique('cedula', 'users_cedula_unique');
        });
    }
};
