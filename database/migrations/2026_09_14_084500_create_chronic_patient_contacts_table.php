<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('chronic_patient_contacts')) {
            Schema::create('chronic_patient_contacts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id');
                $table->unsignedBigInteger('product_id');
                $table->string('consumption_type', 50)->default('chronic');
                $table->timestamp('last_contacted_at');
                $table->timestamp('next_reminder_at')->nullable();
                $table->timestamp('order_date_at_contact')->nullable();
                $table->timestamps();

                $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
                $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
                $table->unique(['client_id', 'product_id'], 'uniq_client_product_contact');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chronic_patient_contacts');
    }
};
