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
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'is_expense')) {
                $table->boolean('is_expense')->default(false)->after('is_indexed')->index();
            }
            if (!Schema::hasColumn('invoices', 'has_islr_retention')) {
                $table->boolean('has_islr_retention')->default(false)->after('is_expense')->index();
            }
            if (!Schema::hasColumn('invoices', 'islr_retention_generated')) {
                $table->boolean('islr_retention_generated')->default(false)->after('has_islr_retention')->index();
            }
            if (!Schema::hasColumn('invoices', 'islr_retention_id')) {
                $table->unsignedBigInteger('islr_retention_id')->nullable()->after('retention_id')->index();
            }
        });

        Schema::table('retentions', function (Blueprint $table) {
            if (!Schema::hasColumn('retentions', 'type')) {
                $table->string('type', 20)->default('iva')->after('id')->index();
            }
            if (!Schema::hasColumn('retentions', 'concept_description')) {
                $table->string('concept_description')->nullable()->after('retention_percentage');
            }
            if (!Schema::hasColumn('retentions', 'subtrahend')) {
                $table->decimal('subtrahend', 15, 2)->default(0.00)->after('concept_description');
            }
        });

        try {
            Schema::table('invoices', function (Blueprint $table) {
                $table->foreign('islr_retention_id')->references('id')->on('retentions')->onDelete('set null');
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("FK invoices.islr_retention_id: " . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            try {
                $table->dropForeign(['islr_retention_id']);
            } catch (\Throwable $e) {}
            $table->dropColumn(['is_expense', 'has_islr_retention', 'islr_retention_generated', 'islr_retention_id']);
        });

        Schema::table('retentions', function (Blueprint $table) {
            $table->dropColumn(['type', 'concept_description', 'subtrahend']);
        });
    }
};
