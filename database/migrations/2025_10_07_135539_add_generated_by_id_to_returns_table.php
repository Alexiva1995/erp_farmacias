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
        Schema::table('returns', function (Blueprint $table) {
            if (!Schema::hasColumn('returns', 'generated_by_id')) {
                $table->unsignedBigInteger('generated_by_id')->nullable()->after('order_id');
                $table->foreign('generated_by_id')->references('id')->on('users')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            if (Schema::hasColumn('returns', 'generated_by_id')) {
                $table->dropForeign(['generated_by_id']);
                $table->dropColumn('generated_by_id');
            }
        });
    }
};
