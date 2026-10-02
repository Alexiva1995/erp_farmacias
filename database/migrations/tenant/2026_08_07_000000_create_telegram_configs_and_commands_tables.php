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
        Schema::create('telegram_configs', function (Blueprint $table) {
            $table->id();
            $table->string('bot_token')->nullable();
            $table->string('chat_id')->nullable();
            $table->string('admin_chat_id')->nullable();
            $table->string('webhook_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('telegram_commands', function (Blueprint $table) {
            $table->id();
            $table->string('module'); // farmacia, restaurante, cosmeticos, alquileres, system
            $table->string('command'); // ej: /pagos, /registrar_factura
            $table->string('alias'); // Nombre legible
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('payload_template')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_commands');
        Schema::dropIfExists('telegram_configs');
    }
};
