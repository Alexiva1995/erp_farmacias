<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega campos de estado de aprobación y foto de evidencia a la tabla kitchen_wastes.
     */
    public function up(): void
    {
        if (!Schema::hasTable('kitchen_wastes')) {
            return;
        }

        Schema::table('kitchen_wastes', function (Blueprint $table) {
            if (!Schema::hasColumn('kitchen_wastes', 'status')) {
                $table->string('status', 20)->default('pending')->after('notes')->index();
            }

            if (!Schema::hasColumn('kitchen_wastes', 'photo_path')) {
                $table->string('photo_path')->nullable()->after('status');
            }

            if (!Schema::hasColumn('kitchen_wastes', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('photo_path')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('kitchen_wastes', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }

            if (!Schema::hasColumn('kitchen_wastes', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('approved_at');
            }
        });
    }

    /**
     * Revierte los campos agregados a kitchen_wastes.
     */
    public function down(): void
    {
        if (!Schema::hasTable('kitchen_wastes')) {
            return;
        }

        Schema::table('kitchen_wastes', function (Blueprint $table) {
            if (Schema::hasColumn('kitchen_wastes', 'approved_by')) {
                $table->dropForeign(['approved_by']);
            }

            $columnsToDrop = array_filter(
                ['status', 'photo_path', 'approved_by', 'approved_at', 'rejection_reason'],
                fn (string $column) => Schema::hasColumn('kitchen_wastes', $column)
            );

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
