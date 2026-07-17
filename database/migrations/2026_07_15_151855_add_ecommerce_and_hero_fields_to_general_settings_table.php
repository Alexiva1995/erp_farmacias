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
            if (!Schema::hasColumn('general_settings', 'ecommerce_menu')) {
                $table->json('ecommerce_menu')->nullable()->after('default_currency');
            }
            
            // Hero Section
            if (!Schema::hasColumn('general_settings', 'hero_title')) {
                $table->string('hero_title')->nullable()->after('ecommerce_menu');
            }
            if (!Schema::hasColumn('general_settings', 'hero_subtitle')) {
                $table->text('hero_subtitle')->nullable()->after('hero_title');
            }
            if (!Schema::hasColumn('general_settings', 'hero_tagline')) {
                $table->string('hero_tagline')->nullable()->after('hero_subtitle');
            }
            if (!Schema::hasColumn('general_settings', 'hero_image')) {
                $table->string('hero_image')->nullable()->after('hero_tagline');
            }
            if (!Schema::hasColumn('general_settings', 'hero_button_text')) {
                $table->string('hero_button_text')->nullable()->after('hero_image');
            }
            
            // Section 2
            if (!Schema::hasColumn('general_settings', 'section2_title')) {
                $table->string('section2_title')->nullable()->after('hero_button_text');
            }
            if (!Schema::hasColumn('general_settings', 'section2_subtitle')) {
                $table->text('section2_subtitle')->nullable()->after('section2_title');
            }
            if (!Schema::hasColumn('general_settings', 'section2_tagline')) {
                $table->string('section2_tagline')->nullable()->after('section2_subtitle');
            }
            if (!Schema::hasColumn('general_settings', 'section2_image')) {
                $table->string('section2_image')->nullable()->after('section2_tagline');
            }
            if (!Schema::hasColumn('general_settings', 'section2_button_text')) {
                $table->string('section2_button_text')->nullable()->after('section2_image');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('general_settings', function (Blueprint $table) {
            $columnsToDrop = [];
            foreach ([
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
            ] as $column) {
                if (Schema::hasColumn('general_settings', $column)) {
                    $columnsToDrop[] = $column;
                }
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
