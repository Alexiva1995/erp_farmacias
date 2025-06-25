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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('identification', 100)->unique('identification');
            $table->enum('identification_type', ['V-', 'J-', 'G-', 'E-'])->default('V-');
            $table->string('name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();

            $table->timestamps();

            $table->index('identification', 'idx_client_identification');
            $table->index('name', 'idx_client_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
