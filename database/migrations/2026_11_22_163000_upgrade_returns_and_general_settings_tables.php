<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones.
     */
    public function up(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            if (!Schema::hasColumn('returns', 'approved_by_id')) {
                $table->foreignId('approved_by_id')
                    ->nullable()
                    ->after('generated_by_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('returns', 'refund_method')) {
                $table->string('refund_method', 30)->default('balance')->after('amount_refunded');
            }
            if (!Schema::hasColumn('returns', 'restock_inventory')) {
                $table->boolean('restock_inventory')->default(true)->after('refund_method');
            }
            if (!Schema::hasColumn('returns', 'reason')) {
                $table->string('reason')->nullable()->after('restock_inventory');
            }
            if (!Schema::hasColumn('returns', 'fiscal_command_id')) {
                $table->foreignId('fiscal_command_id')
                    ->nullable()
                    ->after('status')
                    ->constrained('fiscal_commands')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('returns', 'credit_note_number')) {
                $table->string('credit_note_number', 50)->nullable()->after('fiscal_command_id');
            }
            if (!Schema::hasColumn('returns', 'fiscal_status')) {
                $table->string('fiscal_status', 30)->default('not_applicable')->after('credit_note_number');
            }
        });

        Schema::table('general_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('general_settings', 'return_window_days')) {
                $table->unsignedSmallInteger('return_window_days')->default(2)->after('enable_lots');
            }
            if (!Schema::hasColumn('general_settings', 'require_return_approval')) {
                $table->boolean('require_return_approval')->default(true)->after('return_window_days');
            }
        });
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            if (Schema::hasColumn('returns', 'fiscal_command_id')) {
                $table->dropForeign(['fiscal_command_id']);
                $table->dropColumn('fiscal_command_id');
            }
            if (Schema::hasColumn('returns', 'approved_by_id')) {
                $table->dropForeign(['approved_by_id']);
                $table->dropColumn('approved_by_id');
            }
            $columns = array_filter([
                Schema::hasColumn('returns', 'refund_method') ? 'refund_method' : null,
                Schema::hasColumn('returns', 'restock_inventory') ? 'restock_inventory' : null,
                Schema::hasColumn('returns', 'reason') ? 'reason' : null,
                Schema::hasColumn('returns', 'credit_note_number') ? 'credit_note_number' : null,
                Schema::hasColumn('returns', 'fiscal_status') ? 'fiscal_status' : null,
            ]);
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('general_settings', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('general_settings', 'return_window_days') ? 'return_window_days' : null,
                Schema::hasColumn('general_settings', 'require_return_approval') ? 'require_return_approval' : null,
            ]);
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
