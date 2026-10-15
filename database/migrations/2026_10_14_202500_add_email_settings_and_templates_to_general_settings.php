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
            if (!Schema::hasColumn('general_settings', 'mail_mailer')) {
                $table->string('mail_mailer')->default('smtp')->after('gmail_sync_last_status');
            }
            if (!Schema::hasColumn('general_settings', 'mail_host')) {
                $table->string('mail_host')->nullable()->after('mail_mailer');
            }
            if (!Schema::hasColumn('general_settings', 'mail_port')) {
                $table->integer('mail_port')->default(587)->after('mail_host');
            }
            if (!Schema::hasColumn('general_settings', 'mail_username')) {
                $table->string('mail_username')->nullable()->after('mail_port');
            }
            if (!Schema::hasColumn('general_settings', 'mail_password')) {
                $table->text('mail_password')->nullable()->after('mail_username');
            }
            if (!Schema::hasColumn('general_settings', 'mail_encryption')) {
                $table->string('mail_encryption')->default('tls')->after('mail_password');
            }
            if (!Schema::hasColumn('general_settings', 'mail_from_address')) {
                $table->string('mail_from_address')->nullable()->after('mail_encryption');
            }
            if (!Schema::hasColumn('general_settings', 'mail_from_name')) {
                $table->string('mail_from_name')->nullable()->after('mail_from_address');
            }
            if (!Schema::hasColumn('general_settings', 'ecommerce_email_templates')) {
                $table->json('ecommerce_email_templates')->nullable()->after('mail_from_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('general_settings', function (Blueprint $table) {
            $columns = [
                'mail_mailer',
                'mail_host',
                'mail_port',
                'mail_username',
                'mail_password',
                'mail_encryption',
                'mail_from_address',
                'mail_from_name',
                'ecommerce_email_templates',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('general_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
