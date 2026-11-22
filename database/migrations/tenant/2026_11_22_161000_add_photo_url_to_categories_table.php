<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('categories') && !Schema::hasColumn('categories', 'photo_url')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->string('photo_url')->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('categories') && Schema::hasColumn('categories', 'photo_url')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropColumn('photo_url');
            });
        }
    }
};
