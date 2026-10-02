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
        Schema::table('employee_settlements', function (Blueprint $blueprint) {
            $blueprint->string('signed_document_path')->nullable()->after('employee_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_settlements', function (Blueprint $blueprint) {
            $blueprint->dropColumn('signed_document_path');
        });
    }
};
