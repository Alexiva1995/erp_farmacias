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
        Schema::table('expenses', function (Blueprint $table) {
            //
            $table->string("file_name", 255)->nullable();
            $table->string("extension_file", 5)->nullable();
            $table->text("url_file")->nullable();
            $table->date("date_upload")->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            //
            $table->dropColumn("file_name");
            $table->dropColumn("extension_file");
            $table->dropColumn("url_file");
            $table->dropColumn("date_upload");
        });
    }
};
