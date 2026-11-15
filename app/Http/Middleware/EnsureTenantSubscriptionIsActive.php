<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\SaasSubscriptionStatusEnum;
use App\Models\SaasSubscription;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantSubscriptionIsActive
{
    /**
     * Verifica que la licencia del tenant activo no esté suspendida al intentar ejecutar operaciones de escritura.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!function_exists('tenancy') || !tenancy()->initialized || !tenancy()->tenant) {
            return $next($request);
        }

        // Permitir consultas de lectura y rutas de autenticación / reporte de pago de suscripción
        if (
            in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)
            || $request->is('api/login', 'api/logout', 'api/two-factor-challenge', 'api/public/*', 'api/my-subscription*')
        ) {
            return $next($request);
        }

        $tenantId = (string) tenancy()->tenant->id;

        $status = Cache::remember("saas_subscription_state:{$tenantId}", 300, function () use ($tenantId) {
            try {
                $centralConn = (string) config('tenancy.database.central_connection', config('database.default'));
                if (!Schema::connection($centralConn)->hasTable('saas_subscriptions')) {
                    return SaasSubscriptionStatusEnum::ACTIVE->value;
                }

                $sub = SaasSubscription::where('tenant_id', $tenantId)->select(['id', 'status'])->first();
                return $sub?->status ?: SaasSubscriptionStatusEnum::ACTIVE->value;
            } catch (\Throwable) {
                return SaasSubscriptionStatusEnum::ACTIVE->value;
            }
        });

        $statusEnum = SaasSubscriptionStatusEnum::tryFrom((string) $status) ?? SaasSubscriptionStatusEnum::ACTIVE;

        if (!$statusEnum->canOperate()) {
            return response()->json([
                'status' => 'error',
                'code' => 'SUBSCRIPTION_SUSPENDED',
                'subscription_status' => $statusEnum->value,
                'message' => 'La licencia de suscripción de esta empresa se encuentra suspendida por vencimiento de pago. Reporte su renovación para reactivar el sistema.',
            ], 402);
        }

        return $next($request);
    }
}
