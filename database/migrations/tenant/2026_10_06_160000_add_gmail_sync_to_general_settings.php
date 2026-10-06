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
            if (!Schema::hasColumn('general_settings', 'gmail_sync_email')) {
                $table->string('gmail_sync_email')->nullable()->after('enabled_bi_views');
            }
            if (!Schema::hasColumn('general_settings', 'gmail_sync_password')) {
                $table->text('gmail_sync_password')->nullable()->after('gmail_sync_email');
            }
            if (!Schema::hasColumn('general_settings', 'gmail_sync_host')) {
                $table->string('gmail_sync_host')->default('imap.gmail.com')->after('gmail_sync_password');
            }
            if (!Schema::hasColumn('general_settings', 'gmail_sync_port')) {
                $table->integer('gmail_sync_port')->default(993)->after('gmail_sync_host');
            }
            if (!Schema::hasColumn('general_settings', 'gmail_sync_folder')) {
                $table->string('gmail_sync_folder')->default('INBOX')->after('gmail_sync_port');
            }
            if (!Schema::hasColumn('general_settings', 'gmail_sync_enabled')) {
                $table->boolean('gmail_sync_enabled')->default(false)->after('gmail_sync_folder');
            }
            if (!Schema::hasColumn('general_settings', 'gmail_sync_last_tested_at')) {
                $table->timestamp('gmail_sync_last_tested_at')->nullable()->after('gmail_sync_enabled');
            }
            if (!Schema::hasColumn('general_settings', 'gmail_sync_last_status')) {
                $table->string('gmail_sync_last_status')->nullable()->after('gmail_sync_last_tested_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('general_settings', function (Blueprint $table) {
            $table->dropColumn([
                'gmail_sync_email',
                'gmail_sync_password',
                'gmail_sync_host',
                'gmail_sync_port',
                'gmail_sync_folder',
                'gmail_sync_enabled',
                'gmail_sync_last_tested_at',
                'gmail_sync_last_status',
            ]);
        });
    }
};
