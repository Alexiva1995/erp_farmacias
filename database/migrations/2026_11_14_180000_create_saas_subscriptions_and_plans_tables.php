<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar las migraciones para el Administrador de Suscripciones SaaS y Licencias.
     */
    public function up(): void
    {
        if (Schema::hasTable('general_settings') && DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE general_settings MODIFY COLUMN business_type ENUM('pharmacy', 'restaurant', 'sports_rental', 'minimarket', 'drogueria', 'saas_admin') NOT NULL DEFAULT 'pharmacy'");
        }

        if (!Schema::hasTable('saas_plans')) {
            Schema::create('saas_plans', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('name', 120);
                $table->text('description')->nullable();
                $table->string('billing_cycle', 30)->default('monthly');
                $table->decimal('price_usd', 12, 2)->default(29.00);
                $table->decimal('setup_fee_usd', 12, 2)->default(0.00);
                $table->unsignedInteger('trial_days')->default(14);
                $table->unsignedInteger('grace_days')->default(5);
                $table->unsignedInteger('max_users')->default(5);
                $table->unsignedInteger('max_branches')->default(1);
                $table->json('included_modules')->nullable();
                $table->json('features')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            // Sembrar catálogo base de planes SaaS
            DB::table('saas_plans')->insert([
                [
                    'code' => 'basic',
                    'name' => 'Plan Básico PyME',
                    'description' => 'Licencia esencial para una sede con punto de venta, control de inventario y facturación.',
                    'billing_cycle' => 'monthly',
                    'price_usd' => 29.00,
                    'setup_fee_usd' => 0.00,
                    'trial_days' => 14,
                    'grace_days' => 5,
                    'max_users' => 5,
                    'max_branches' => 1,
                    'included_modules' => json_encode(['tpv', 'inventory', 'crm', 'finances']),
                    'features' => json_encode([
                        '1 Sede / Sucursal activa',
                        'Hasta 5 usuarios concurrentes',
                        'TPV y Control de Inventario',
                        'Soporte estándar vía ticket',
                    ]),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'code' => 'pro',
                    'name' => 'Plan PRO Corporativo',
                    'description' => 'Licencia avanzada con inteligencia de negocios (BI), asistente IA, multisede y automatizaciones.',
                    'billing_cycle' => 'monthly',
                    'price_usd' => 49.00,
                    'setup_fee_usd' => 50.00,
                    'trial_days' => 14,
                    'grace_days' => 7,
                    'max_users' => 20,
                    'max_branches' => 5,
                    'included_modules' => json_encode(['tpv', 'inventory', 'crm', 'finances', 'bi', 'rrhh', 'ia_assistant', 'telegram']),
                    'features' => json_encode([
                        'Hasta 5 Sedes / Sucursales',
                        'Hasta 20 usuarios concurrentes',
                        'Módulos BI, RRHH y Asistente IA incluidos',
                        'Alertas en tiempo real por Telegram',
                    ]),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'code' => 'enterprise',
                    'name' => 'Plan Enterprise B2B / Cadenas',
                    'description' => 'Licencia ilimitada para cadenas de farmacias, droguerías mayoristas y franquicias gastronómicas.',
                    'billing_cycle' => 'annual',
                    'price_usd' => 499.00,
                    'setup_fee_usd' => 150.00,
                    'trial_days' => 30,
                    'grace_days' => 10,
                    'max_users' => 999,
                    'max_branches' => 50,
                    'included_modules' => json_encode(['tpv', 'inventory', 'crm', 'finances', 'bi', 'rrhh', 'ia_assistant', 'telegram', 'wholesale', 'ecommerce']),
                    'features' => json_encode([
                        'Hasta 50 Sedes interconectadas',
                        'Usuarios ilimitados',
                        'Puente B2B Inter-Tenant y E-commerce',
                        'Soporte prioritario 24/7 y onboarding dedicado',
                    ]),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }

        if (!Schema::hasTable('saas_subscriptions')) {
            Schema::create('saas_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id', 100)->nullable()->index();
                $table->string('company_name', 150);
                $table->string('contact_name', 150)->nullable();
                $table->string('contact_email', 150)->nullable();
                $table->string('contact_phone', 50)->nullable();
                $table->string('domain', 150)->nullable();
                $table->string('vertical', 50)->default('pharmacy');
                $table->foreignId('saas_plan_id')->nullable()->constrained('saas_plans')->nullOnDelete();
                $table->string('plan_code', 50)->default('basic');
                $table->string('billing_cycle', 30)->default('monthly');
                $table->string('license_key', 100)->unique();
                $table->string('status', 30)->default('active')->index();
                $table->decimal('price_agreed_usd', 12, 2)->default(29.00);
                $table->date('starts_at')->nullable();
                $table->date('trial_ends_at')->nullable();
                $table->date('current_period_ends_at')->nullable()->index();
                $table->date('grace_period_ends_at')->nullable();
                $table->timestamp('suspended_at')->nullable();
                $table->boolean('is_remote')->default(false);
                $table->boolean('auto_renew')->default(true);
                $table->unsignedInteger('max_users')->default(5);
                $table->unsignedInteger('max_branches')->default(1);
                $table->string('seller_name', 100)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('saas_subscription_payments')) {
            Schema::create('saas_subscription_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('saas_subscription_id')->constrained('saas_subscriptions')->cascadeOnDelete();
                $table->string('tenant_id', 100)->nullable()->index();
                $table->string('plan_code', 50)->nullable();
                $table->string('receipt_number', 50)->unique();
                $table->decimal('amount_usd', 12, 2);
                $table->decimal('exchange_rate', 14, 4)->default(1.0000);
                $table->decimal('amount_paid_currency', 14, 2)->nullable();
                $table->string('currency', 10)->default('USD');
                $table->string('payment_method', 50)->default('transfer');
                $table->string('reference_number', 100)->nullable();
                $table->string('status', 30)->default('approved')->index();
                $table->date('period_starts_at')->nullable();
                $table->date('period_ends_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->string('reported_by', 100)->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Revertir las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists('saas_subscription_payments');
        Schema::dropIfExists('saas_subscriptions');
        Schema::dropIfExists('saas_plans');

        if (Schema::hasTable('general_settings') && DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE general_settings MODIFY COLUMN business_type ENUM('pharmacy', 'restaurant', 'sports_rental', 'minimarket', 'drogueria') NOT NULL DEFAULT 'pharmacy'");
        }
    }
};
