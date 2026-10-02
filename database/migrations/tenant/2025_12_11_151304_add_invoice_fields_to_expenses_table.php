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
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('invoice_number', 100)->nullable()->after('has_invoice');
            $table->date('invoice_date')->nullable()->after('invoice_number');
            $table->string('control_number', 100)->nullable()->after('invoice_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn(['invoice_number', 'invoice_date', 'control_number']);
        });
    }
};
