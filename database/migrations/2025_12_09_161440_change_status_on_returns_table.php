<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            $table->string('status')->nullable()->change();
        });

        DB::table('returns')
            ->where('status', 'Active')
            ->update(['status' => 'Approved']);

        DB::table('returns')
            ->where('status', 'Paid')
            ->update(['status' => 'Rejected']);

        DB::table('returns')
            ->whereNotIn('status', ['Approved', 'Rejected'])
            ->update(['status' => null]);

        Schema::table('returns', function (Blueprint $table) {
            $table->enum('status', ['Approved', 'Rejected'])
                ->nullable()
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            $table->string('status')->nullable()->change();
        });

        DB::table('returns')
            ->where('status', 'Approved')
            ->update(['status' => 'Active']);

        DB::table('returns')
            ->where('status', 'Rejected')
            ->update(['status' => 'Paid']);

        Schema::table('returns', function (Blueprint $table) {
            $table->enum('status', ['Active', 'Paid'])
                ->default('Active')
                ->change();
        });
    }
};
