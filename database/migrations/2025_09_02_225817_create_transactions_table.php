<?php

use App\TransactionCurrency;
use App\TransactionMovementType;
use App\TransactionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->foreignId('exchange_rate_id')->nullable()->constrained()->onDelete('cascade');
            $table->string("description");
            $table->enum('currency', array_column(TransactionCurrency::cases(), 'value'));
            $table->enum('type', array_column(TransactionType::cases(), 'value'));
            $table->decimal('amount', 15, 2);
            $table->enum('movement_type', array_column(TransactionMovementType::cases(), 'value'));
            $table->date('transaction_date');
            $table->timestamps();

            $table->index('user_id');
            $table->index('category_id');
            $table->index('transaction_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
