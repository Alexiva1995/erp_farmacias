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
        $usdAmount = (float) ($subscription->price_agreed_usd ?: $tenant->monthly_price ?: 29.0);
        $planName = $subscription->plan?->name ?? strtoupper((string) ($subscription->plan_code ?: 'basic'));
        $domain = $tenant->domains->first()?->domain ?? "{$tenant->id}.tovaerp.com";

        $dueDate = $subscription->current_period_ends_at
            ? \Carbon\Carbon::parse($subscription->current_period_ends_at)
            : \Carbon\Carbon::today()->addMonth();

        $periodStart = (clone $dueDate)->subMonth()->format('d/m/Y');
        $periodEnd = $dueDate->format('d/m/Y');

        $data = [
            'site_url' => "https://{$domain}/",
            'site_name' => $domain,
            'plan_name' => $planName,
            'period' => "{$periodStart} al {$periodEnd}",
            'usd_amount' => $usdAmount,
            'rate' => $rate,
            'cop_amount' => $usdAmount * $rate,
            'banco_cuenta' => '91201530453',
            'banco_titular' => 'Estefanny Torrado',
            'banco_cc' => '1148456331',
            'binance_email' => 'Alexisjose95@gmail.com',
        ];

        \Illuminate\Support\Facades\Mail::to($recipient)->send(new \App\Mail\CobroMensualidadMail($data));

        return response()->json([
            'message' => "Correo de cobro enviado exitosamente a {$recipient}.",
        ]);
    }
}
