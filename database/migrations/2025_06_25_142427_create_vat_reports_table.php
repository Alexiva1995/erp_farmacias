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
        Schema::create('vat_reports', function (Blueprint $table) {
            $table->id();
            $table->date('report_month');
            $table->decimal('total_vat_paid', 15, 2);
            $table->string('payment_file_path')->nullable();
            $table->string('vat_file_path')->nullable();
            $table->timestamps();

            $table->index('report_month', 'idx_vat_month');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vat_reports');
    }
};
