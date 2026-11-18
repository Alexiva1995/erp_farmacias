<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            if (!Schema::hasColumn('suppliers', 'indexing_start_day')) {
                $table->unsignedSmallInteger('indexing_start_day')->default(0)->after('is_indexed');
            }
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            if (Schema::hasColumn('suppliers', 'indexing_start_day')) {
                $table->dropColumn('indexing_start_day');
            }
        });
    }
};
