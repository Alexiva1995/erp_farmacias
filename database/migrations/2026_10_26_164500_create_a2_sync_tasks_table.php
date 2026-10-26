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
        if (!Schema::hasTable('a2_sync_tasks')) {
            Schema::create('a2_sync_tasks', function (Blueprint $table) {
                $table->id();
                $table->string('section', 50)->default('all');
                $table->integer('year')->default(2026);
                $table->string('status', 30)->default('pending'); // pending, processing, completed, imported, failed
                $table->json('extracted_data')->nullable();
                $table->json('preview_data')->nullable();
                $table->text('error_message')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('a2_sync_tasks');
    }
};
