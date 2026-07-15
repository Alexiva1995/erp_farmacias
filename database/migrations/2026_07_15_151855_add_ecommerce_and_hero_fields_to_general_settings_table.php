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
            $table->json('ecommerce_menu')->nullable()->after('default_currency');
            
            // Hero Section
            $table->string('hero_title')->nullable()->after('ecommerce_menu');
            $table->text('hero_subtitle')->nullable()->after('hero_title');
            $table->string('hero_tagline')->nullable()->after('hero_subtitle');
            $table->string('hero_image')->nullable()->after('hero_tagline');
            $table->string('hero_button_text')->nullable()->after('hero_image');
            
            // Section 2
            $table->string('section2_title')->nullable()->after('hero_button_text');
            $table->text('section2_subtitle')->nullable()->after('section2_title');
            $table->string('section2_tagline')->nullable()->after('section2_subtitle');
            $table->string('section2_image')->nullable()->after('section2_tagline');
            $table->string('section2_button_text')->nullable()->after('section2_image');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('general_settings', function (Blueprint $table) {
            $table->dropColumn([
                'ecommerce_menu',
                'hero_title',
                'hero_subtitle',
                'hero_tagline',
                'hero_image',
                'hero_button_text',
                'section2_title',
                'section2_subtitle',
                'section2_tagline',
                'section2_image',
                'section2_button_text'
            ]);
        });
    }
};
