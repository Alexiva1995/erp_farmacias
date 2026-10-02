<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chronic_patient_contacts') && !Schema::hasColumn('chronic_patient_contacts', 'user_id')) {
            Schema::table('chronic_patient_contacts', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->after('product_id');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('chronic_patient_contacts') && Schema::hasColumn('chronic_patient_contacts', 'user_id')) {
            Schema::table('chronic_patient_contacts', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            });
        }
    }
};
