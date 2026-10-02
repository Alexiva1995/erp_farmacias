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
        Schema::create('supplier_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['ftp', 'sftp', 'http', 'api']);
            $table->string('host');
            $table->string('port')->nullable();
            $table->string('username')->nullable();
            $table->text('password')->nullable(); // Encriptada
            $table->string('path')->nullable();   // Ruta del archivo o endpoint
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_connections');
    }
};
