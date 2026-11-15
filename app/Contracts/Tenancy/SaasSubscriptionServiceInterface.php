<?php

declare(strict_types=1);

namespace App\Contracts\Tenancy;

use App\Models\SaasPlan;
use App\Models\SaasSubscription;
use App\Models\SaasSubscriptionPayment;
use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface SaasSubscriptionServiceInterface
{
    /**
     * Obtener catálogo de planes SaaS.
     *
     * @param bool $onlyActive
     * @return Collection<int, SaasPlan>
     */
    public function getPlans(bool $onlyActive = false): Collection;

    /**
     * Crear o actualizar un plan en el catálogo.
     *
     * @param array<string, mixed> $data
     * @param int|null $id
     */
    public function savePlan(array $data, ?int $id = null): SaasPlan;

    /**
     * Eliminar un plan del catálogo.
     */
    public function deletePlan(int $id): bool;

    /**
     * Garantizar que un Tenant tenga su registro de suscripción y llave de licencia.
     *
     * @param Tenant $tenant
     * @param array<string, mixed> $overrides
     */
    public function syncTenantSubscription(Tenant $tenant, array $overrides = []): SaasSubscription;

    /**
     * Obtener métricas financieras y operativas de la consola SaaS (MRR, ARR, vencimientos).
     *
     * @return array<string, mixed>
     */
    public function getDashboardMetrics(): array;

    /**
     * Registrar un pago de licencia y renovar automáticamente el periodo si está aprobado.
     *
     * @param array<string, mixed> $data
     * @param int|null $userId
     */
    public function registerPaymentAndRenew(array $data, ?int $userId = null): SaasSubscriptionPayment;

    /**
     * Aprobar un pago reportado por un cliente y extender la vigencia de su licencia.
     */
    public function approvePayment(int $paymentId, ?int $userId = null): SaasSubscriptionPayment;

    /**
     * Rechazar un pago reportado.
     */
    public function rejectPayment(int $paymentId, ?string $notes = null): SaasSubscriptionPayment;

    /**
     * Cambiar el estado operativo de la licencia de un tenant (activar, suspender, etc.).
     */
    public function changeSubscriptionStatus(string $tenantId, string $status): SaasSubscription;

    /**
     * Regenerar la llave de licencia de un tenant.
     */
    public function regenerateLicenseKey(string $tenantId): SaasSubscription;

    /**
     * Verificar validez de una llave de licencia (API pública / clientes externos).
     *
     * @return array<string, mixed>
     */
    public function verifyLicenseKey(string $licenseKey, ?string $domain = null, ?string $tenantId = null): array;

    /**
     * Procesar vencimientos automáticos, periodos de gracia y suspensiones por mora.
     *
     * @return array<string, int>
     */
    public function processExpirationsAndSuspensions(): array;

    /**
     * Obtener historial paginado de pagos de licencias.
     *
     * @param array<string, mixed> $filters
     * @param int $perPage
     */
    public function getPaginatedPayments(array $filters = [], int $perPage = 25): LengthAwarePaginator;
}
