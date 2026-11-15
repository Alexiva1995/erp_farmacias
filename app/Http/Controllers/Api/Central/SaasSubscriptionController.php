<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central;

use App\Contracts\Tenancy\SaasSubscriptionServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\StoreSaasPaymentRequest;
use App\Http\Requests\Central\StoreSaasPlanRequest;
use App\Http\Requests\Central\VerifyLicenseRequest;
use App\Http\Resources\Central\SaasPlanResource;
use App\Http\Resources\Central\SaasSubscriptionPaymentResource;
use App\Http\Resources\Central\SaasSubscriptionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaasSubscriptionController extends Controller
{
    public function __construct(
        protected SaasSubscriptionServiceInterface $subscriptionService
    ) {}

    /**
     * Obtener métricas financieras y operativas de suscripciones (MRR, ARR, vencimientos).
     */
    public function metrics(): JsonResponse
    {
        return response()->json([
            'data' => $this->subscriptionService->getDashboardMetrics(),
        ]);
    }

    /**
     * Obtener catálogo de planes SaaS.
     */
    public function plans(Request $request): JsonResponse
    {
        $onlyActive = $request->boolean('only_active', false);
        $plans = $this->subscriptionService->getPlans($onlyActive);

        return response()->json([
            'data' => SaasPlanResource::collection($plans),
        ]);
    }

    /**
     * Crear un nuevo plan SaaS.
     */
    public function storePlan(StoreSaasPlanRequest $request): JsonResponse
    {
        $plan = $this->subscriptionService->savePlan($request->validated());

        return response()->json([
            'message' => 'Plan de suscripción creado exitosamente.',
            'data' => new SaasPlanResource($plan),
        ], 201);
    }

    /**
     * Actualizar un plan SaaS existente.
     */
    public function updatePlan(StoreSaasPlanRequest $request, int $id): JsonResponse
    {
        $plan = $this->subscriptionService->savePlan($request->validated(), $id);

        return response()->json([
            'message' => 'Plan de suscripción actualizado exitosamente.',
            'data' => new SaasPlanResource($plan),
        ]);
    }

    /**
     * Eliminar un plan SaaS.
     */
    public function destroyPlan(int $id): JsonResponse
    {
        $this->subscriptionService->deletePlan($id);

        return response()->json([
            'message' => 'Plan eliminado exitosamente.',
        ]);
    }

    /**
     * Obtener listado paginado de pagos y renovaciones.
     */
    public function payments(Request $request): JsonResponse
    {
        $filters = [
            'tenant_id' => $request->input('tenant_id'),
            'status' => $request->input('status'),
            'search' => $request->input('search'),
        ];
        $perPage = (int) $request->input('per_page', 25);

        $paginator = $this->subscriptionService->getPaginatedPayments($filters, $perPage);

        return SaasSubscriptionPaymentResource::collection($paginator)->response();
    }

    /**
     * Registrar un pago de licencia y renovar automáticamente el periodo.
     */
    public function storePayment(StoreSaasPaymentRequest $request): JsonResponse
    {
        $userId = $request->user()?->id ? (int) $request->user()->id : null;
        $payment = $this->subscriptionService->registerPaymentAndRenew($request->validated(), $userId);

        return response()->json([
            'message' => 'Pago registrado y suscripción renovada exitosamente.',
            'data' => new SaasSubscriptionPaymentResource($payment),
        ], 201);
    }

    /**
     * Aprobar un pago reportado por un cliente y renovar su licencia.
     */
    public function approvePayment(Request $request, int $paymentId): JsonResponse
    {
        $userId = $request->user()?->id ? (int) $request->user()->id : null;
        $payment = $this->subscriptionService->approvePayment($paymentId, $userId);

        return response()->json([
            'message' => 'Pago aprobado y licencia renovada exitosamente.',
            'data' => new SaasSubscriptionPaymentResource($payment),
        ]);
    }

    /**
     * Rechazar un pago reportado.
     */
    public function rejectPayment(Request $request, int $paymentId): JsonResponse
    {
        $notes = $request->input('notes') ? (string) $request->input('notes') : null;
        $payment = $this->subscriptionService->rejectPayment($paymentId, $notes);

        return response()->json([
            'message' => 'Pago rechazado.',
            'data' => new SaasSubscriptionPaymentResource($payment),
        ]);
    }

    /**
     * Cambiar estado operativo de la licencia de un tenant (activar, suspender, etc.).
     */
    public function toggleStatus(Request $request, string $tenantId): JsonResponse
    {
        $status = (string) $request->input('status', 'active');
        $subscription = $this->subscriptionService->changeSubscriptionStatus($tenantId, $status);

        return response()->json([
            'message' => 'Estado de la licencia actualizado exitosamente.',
            'data' => new SaasSubscriptionResource($subscription),
        ]);
    }

    /**
     * Regenerar la llave de licencia de un tenant.
     */
    public function regenerateKey(string $tenantId): JsonResponse
    {
        $subscription = $this->subscriptionService->regenerateLicenseKey($tenantId);

        return response()->json([
            'message' => 'Llave de licencia regenerada exitosamente.',
            'data' => new SaasSubscriptionResource($subscription),
        ]);
    }

    /**
     * Ejecutar corte y verificación manual de vencimientos.
     */
    public function processExpirations(): JsonResponse
    {
        $result = $this->subscriptionService->processExpirationsAndSuspensions();

        return response()->json([
            'message' => "Corte procesado: {$result['moved_to_past_due']} en periodo de gracia, {$result['moved_to_suspended']} suspendidas.",
            'data' => $result,
        ]);
    }

    /**
     * Endpoint público para verificar la validez de una llave de licencia (clientes remotos / híbridos).
     */
    public function verifyLicense(VerifyLicenseRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $result = $this->subscriptionService->verifyLicenseKey(
            (string) $validated['license_key'],
            $validated['domain'] ?? null,
            $validated['tenant_id'] ?? null
        );

        return response()->json([
            'data' => $result,
        ], $result['valid'] ? 200 : 422);
    }

    /**
     * Obtener el estado de suscripción actual del tenant autenticado (Portal del Cliente).
     */
    public function mySubscription(Request $request): JsonResponse
    {
        $tenantId = (function_exists('tenancy') && tenancy()->initialized && tenancy()->tenant)
            ? (string) tenancy()->tenant->id
            : (string) $request->input('tenant_id', '');

        if (empty($tenantId)) {
            return response()->json(['data' => null]);
        }

        $tenant = \App\Models\Tenant::find($tenantId);
        if (!$tenant) {
            return response()->json(['data' => null]);
        }

        $subscription = $this->subscriptionService->syncTenantSubscription($tenant);
        $subscription->load(['plan', 'payments' => fn ($q) => $q->orderBy('created_at', 'desc')->limit(10)]);

        return response()->json([
            'data' => new SaasSubscriptionResource($subscription),
        ]);
    }

    /**
     * Reportar un pago de mensualidad o upgrade desde el portal del tenant.
     */
    public function reportMyPayment(StoreSaasPaymentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $tenantId = (function_exists('tenancy') && tenancy()->initialized && tenancy()->tenant)
            ? (string) tenancy()->tenant->id
            : (string) ($data['tenant_id'] ?? '');

        $data['tenant_id'] = $tenantId;
        $data['auto_approve'] = false;
        $data['reported_by'] = $request->user()?->username ?? $request->user()?->email ?? 'Cliente Tenant';

        $payment = $this->subscriptionService->registerPaymentAndRenew($data);

        return response()->json([
            'message' => 'Pago reportado exitosamente. Será verificado por administración para extender su licencia.',
            'data' => new SaasSubscriptionPaymentResource($payment),
        ], 201);
    }

    /**
     * Enviar correo de cobro de mensualidad al tenant usando su plan, precio y fecha de vencimiento.
     */
    public function sendBillingEmail(Request $request, string $tenantId): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['nullable', 'email', 'max:150'],
            'rate' => ['nullable', 'numeric', 'min:1'],
        ]);

        $tenant = \App\Models\Tenant::with('domains')->findOrFail($tenantId);
        $subscription = $this->subscriptionService->syncTenantSubscription($tenant);

        $recipient = !empty($validated['email']) ? (string) $validated['email'] : (string) ($subscription->contact_email ?? '');
        if (empty($recipient)) {
            return response()->json([
                'message' => 'El tenant no tiene un correo de contacto configurado. Ingresa un correo para enviar el cobro.',
            ], 422);
        }

        // Persistir el correo en la suscripción del tenant si fue ingresado o actualizado
        if (!empty($validated['email']) && $validated['email'] !== $subscription->contact_email) {
            $subscription->update(['contact_email' => $recipient]);
        }

        $rate = (float) ($validated['rate'] ?? 3400);
        $data = $this->buildBillingData($tenant, $subscription, $rate);

        try {
            $this->configureDynamicMailer();
            \Illuminate\Support\Facades\Mail::to($recipient)->send(new \App\Mail\CobroMensualidadMail($data));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Error enviando correo de cobro al tenant {$tenantId} ({$recipient}): " . $e->getMessage());

            return response()->json([
                'message' => 'No se pudo enviar el correo de cobro: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => "Correo de cobro enviado exitosamente a {$recipient}.",
        ]);
    }

    /**
     * Webhook público (firmado) para que el tenant informe que pagará su mensualidad en efectivo.
     */
    public function reportCashPayment(Request $request, string $tenantId)
    {
        if (!$request->hasValidSignature()) {
            return response('Enlace inválido o expirado.', 403);
        }

        $tenant = \App\Models\Tenant::with('domains')->findOrFail($tenantId);
        $subscription = $this->subscriptionService->syncTenantSubscription($tenant);
        $domain = $tenant->domains->first()?->domain ?? "{$tenant->id}.tovaerp.com";
        $usdAmount = (float) ($tenant->monthly_price > 0
            ? $tenant->monthly_price
            : ($subscription->price_agreed_usd ?: ($subscription->plan?->price_usd ?: 29.0)));
        $planName = $subscription->plan?->name ?? strtoupper((string) ($subscription->plan_code ?: 'basic'));

        try {
            $this->configureDynamicMailer();
            $adminEmail = trim((string) (\App\Models\GeneralSetting::first()?->gmail_sync_email ?? ''));
            $notifyTo = $adminEmail !== '' ? $adminEmail : 'Alexisjose95@gmail.com';

            $body = "El cliente del sitio {$domain} (Tenant: {$tenant->id}) ha informado que realizará el pago de su mensualidad en EFECTIVO.\n\n"
                . "Plan: {$planName}\n"
                . "Monto: " . number_format($usdAmount, 2, '.', ',') . " USD\n"
                . "Correo de contacto: " . ($subscription->contact_email ?: 'No registrado') . "\n"
                . "Fecha de solicitud: " . now()->format('d/m/Y H:i');

            \Illuminate\Support\Facades\Mail::raw($body, function ($message) use ($notifyTo, $domain) {
                $message->to($notifyTo)
                    ->subject("💵 Solicitud de Pago en Efectivo — {$domain}");
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Error notificando pago en efectivo para {$tenantId}: " . $e->getMessage());
            return response('No se pudo procesar la notificación en este momento. Intenta más tarde.', 500);
        }

        return response(
            '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Pago en Efectivo Informado</title></head>'
            . '<body style="font-family:sans-serif;background:#f0f2f5;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;">'
            . '<div style="background:#fff;padding:32px;border-radius:14px;max-width:460px;text-align:center;box-shadow:0 4px 20px rgba(0,0,0,0.08);">'
            . '<h2 style="color:#7A0099;margin-top:0;">¡Solicitud Recibida!</h2>'
            . '<p style="color:#4b5563;line-height:1.5;">Hemos notificado a administración tu solicitud de pago en <strong>efectivo</strong> para <strong>' . e($domain) . '</strong>.</p>'
            . '</div></body></html>',
            200,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }

    /**
     * Construye los datos del correo de cobro, incluyendo la tasa más alta entre EUR y BINANCE para Bs.
     */
    public function buildBillingData(\App\Models\Tenant $tenant, \App\Models\SaasSubscription $subscription, float $rate = 3400): array
    {
        $planPriceUsd = (float) ($subscription->plan?->price_usd ?? 0);
        $usdAmount = (float) ($tenant->monthly_price > 0
            ? $tenant->monthly_price
            : ($subscription->price_agreed_usd ?: ($planPriceUsd ?: 29.0)));
        $discountUsd = $planPriceUsd > $usdAmount ? round($planPriceUsd - $usdAmount, 2) : 0.0;
        $planName = $subscription->plan?->name ?? strtoupper((string) ($subscription->plan_code ?: 'basic'));
        $domain = $tenant->domains->first()?->domain ?? "{$tenant->id}.tovaerp.com";

        $dueDate = $subscription->current_period_ends_at
            ? \Carbon\Carbon::parse($subscription->current_period_ends_at)
            : \Carbon\Carbon::today()->addMonth();

        $periodStart = (clone $dueDate)->subMonth()->format('d/m/Y');
        $periodEnd = $dueDate->format('d/m/Y');

        // Obtener la tasa más alta entre EUR y BINANCE
        $eurRate = (float) (\App\Models\ExchangeRate::where('currency_code', 'EUR')->orderByDesc('id')->value('rate') ?? 0);
        $binanceRate = (float) (\App\Models\ExchangeRate::where('currency_code', 'BINANCE')->orderByDesc('id')->value('rate') ?? 0);
        $bsRate = max($eurRate, $binanceRate);
        $bsRateSource = $binanceRate >= $eurRate && $binanceRate > 0 ? 'Binance' : 'EUR';

        return [
            'site_url' => "https://{$domain}/",
            'site_name' => $domain,
            'plan_name' => $planName,
            'plan_price_usd' => $planPriceUsd,
            'discount_usd' => $discountUsd,
            'discount_cop' => $discountUsd * $rate,
            'period' => "{$periodStart} al {$periodEnd}",
            'usd_amount' => $usdAmount,
            'rate' => $rate,
            'cop_amount' => $usdAmount * $rate,
            'bs_rate' => $bsRate,
            'bs_rate_source' => $bsRateSource,
            'bs_amount' => $usdAmount * $bsRate,
            'bs_banco' => 'Banco de Venezuela',
            'bs_cuenta' => '01020828990000161732',
            'bs_titular' => 'Alexis Varela',
            'bs_cedula' => 'V-24.150.980',
            'banco_cuenta' => '91201530453',
            'banco_titular' => 'Estefanny Torrado',
            'banco_cc' => '1148456331',
            'binance_email' => 'Alexisjose95@gmail.com',
            'cash_report_url' => \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'saas.report-cash-payment',
                now()->addDays(30),
                ['tenantId' => $tenant->id]
            ),
        ];
    }

    /**
     * Configura dinámicamente el mailer SMTP usando las credenciales Gmail de GeneralSetting si existen.
     */
    public function configureDynamicMailer(): void
    {
        $settings = \App\Models\GeneralSetting::first();
        $syncEmail = trim((string) ($settings?->gmail_sync_email ?? ''));
        $rawSyncPass = (string) ($settings?->gmail_sync_password ?? '');

        if ($syncEmail !== '' && $rawSyncPass !== '') {
            try {
                $syncPass = \App\Helpers\FtpCrypt::decrypt($rawSyncPass);
            } catch (\Throwable) {
                $syncPass = $rawSyncPass;
            }

            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => 'smtp.gmail.com',
                'mail.mailers.smtp.port' => 465,
                'mail.mailers.smtp.encryption' => 'ssl',
                'mail.mailers.smtp.username' => $syncEmail,
                'mail.mailers.smtp.password' => $syncPass,
                'mail.mailers.smtp.verify_peer' => false,
                'mail.from.address' => $syncEmail,
                'mail.from.name' => $settings?->app_name ?: config('mail.from.name', 'TOVA ERP'),
            ]);

            \Illuminate\Support\Facades\Mail::purge('smtp');
        }
    }
}
