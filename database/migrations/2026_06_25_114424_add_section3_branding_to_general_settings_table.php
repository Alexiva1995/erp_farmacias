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
            $table->string('section3_title')->nullable();
            $table->text('section3_subtitle')->nullable();
            $table->string('section3_tagline')->nullable();
            $table->string('section3_image')->nullable();
            $table->string('section3_button_text')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('general_settings', function (Blueprint $table) {
            $table->dropColumn([
                'section3_title',
                'section3_subtitle',
                'section3_tagline',
                'section3_image',
                'section3_button_text'
            ]);
        });
    }
};
