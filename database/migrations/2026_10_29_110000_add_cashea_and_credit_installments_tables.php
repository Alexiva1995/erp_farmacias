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
        // 1. Campos de configuración para Cashea
        Schema::table('general_settings', function (Blueprint $table) {
            $table->boolean('enable_cashea')->default(false)->after('enable_zoom');
            $table->string('cashea_mode')->default('manual')->after('enable_cashea'); // manual | api
            $table->string('cashea_environment')->default('sandbox')->after('cashea_mode'); // sandbox | production
            $table->string('cashea_client_id')->nullable()->after('cashea_environment');
            $table->string('cashea_public_key')->nullable()->after('cashea_client_id');
            $table->text('cashea_private_key')->nullable()->after('cashea_public_key');
            $table->unsignedTinyInteger('cashea_installments_count')->default(3)->after('cashea_private_key');
            $table->unsignedSmallInteger('cashea_installment_days')->default(14)->after('cashea_installments_count');
        });

        // 2. Columna informativa en CashClosings para arqueo de ventas Cashea
        Schema::table('cash_closing', function (Blueprint $table) {
            $table->decimal('usd_cashea_credit', 14, 2)->default(0)->after('usd_credit');
        });

        // 3. Modificaciones en tabla de créditos para soporte de tipo de crédito y referencia Cashea
        Schema::table('credits', function (Blueprint $table) {
            $table->string('credit_type')->default('standard')->after('status'); // standard | cashea
            $table->string('cashea_order_reference')->nullable()->after('credit_type');
            $table->decimal('initial_payment_usd', 12, 2)->default(0)->after('cashea_order_reference');
            $table->unsignedTinyInteger('installments_count')->default(1)->after('initial_payment_usd');
            $table->date('next_due_date')->nullable()->after('installments_count');

            $table->index('credit_type', 'idx_credit_type');
        });

        // 4. Tabla de cuotas programadas de créditos
        Schema::create('credit_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_id')->constrained('credits')->onDelete('cascade');
            $table->unsignedTinyInteger('installment_number');
            $table->decimal('amount_usd', 12, 2);
            $table->decimal('paid_amount_usd', 12, 2)->default(0);
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('status')->default('Pending'); // Pending | Paid | Overdue
            $table->timestamps();

            $table->index(['credit_id', 'status'], 'idx_installment_credit_status');
            $table->index('due_date', 'idx_installment_due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_installments');

        Schema::table('credits', function (Blueprint $table) {
            $table->dropIndex('idx_credit_type');
            $table->dropColumn([
                'credit_type',
                'cashea_order_reference',
                'initial_payment_usd',
                'installments_count',
                'next_due_date',
            ]);
        });

        Schema::table('cash_closing', function (Blueprint $table) {
            $table->dropColumn('usd_cashea_credit');
        });

        Schema::table('general_settings', function (Blueprint $table) {
            $table->dropColumn([
                'enable_cashea',
                'cashea_mode',
                'cashea_environment',
                'cashea_client_id',
                'cashea_public_key',
                'cashea_private_key',
                'cashea_installments_count',
                'cashea_installment_days',
            ]);
        });
    }
};
