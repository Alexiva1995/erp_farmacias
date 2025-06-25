// database/migrations/YYYY_MM_DD_XXXXXX_create_donative_logs_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('donative_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('donation_batch_uuid');
            $table->string('institution_name');
            $table->foreignId('expired_log_id')->constrained('expired_logs')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donative_logs');
    }
};
