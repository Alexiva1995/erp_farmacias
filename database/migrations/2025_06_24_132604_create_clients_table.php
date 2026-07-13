<?php

use App\Models\Client;
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
            $table->id(); // BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY

            // Campos de identificación
            $table->string('identification', 100)->unique(); // VARCHAR(100) NOT NULL UNIQUE
            // $table->enum('identification_type', ['V-', 'J-', 'G-', 'E-'])->default('V-'); // ENUM
            $table->enum('identification_type', [
                Client::IDENTIFICATION_TYPE_VENEZOLANO,
                Client::IDENTIFICATION_TYPE_GOBIERNO,
                Client::IDENTIFICATION_TYPE_JURIDICO,
                Client::IDENTIFICATION_TYPE_EXTRANJERO,
            ])->default(Client::IDENTIFICATION_TYPE_VENEZOLANO); // ENUM

            // Datos personales
            $table->string('name', 255)->nullable(false); // VARCHAR(255) NOT NULL
            $table->string('last_name', 255)->nullable(); // VARCHAR(255) NOT NULL
            $table->string('email', 255)->nullable(); // VARCHAR(255) NULL
            $table->string('phone', 50)->nullable(); // VARCHAR(50) NULL
            $table->text('address')->nullable(); // TEXT NULL
            $table->date("birthdate")->nullable();

            // Relación con companies
            $table->unsignedBigInteger("company_id")->nullable();
            $table->foreign('company_id')->references("id")->on("companies")->onDelete("cascade")->onUpdate("cascade");

            // Timestamps
            $table->timestamps(); // created_at y updated_at

            $table->softDeletes(); // softDeletes

            // Índices adicionales
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
