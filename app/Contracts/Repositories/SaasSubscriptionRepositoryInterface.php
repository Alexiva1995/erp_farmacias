<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\SaasPlan;
use App\Models\SaasSubscription;
use App\Models\SaasSubscriptionPayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface SaasSubscriptionRepositoryInterface
{
    /**
     * Obtener todos los planes SaaS registrados.
     *
     * @param bool $onlyActive
     * @return Collection<int, SaasPlan>
     */
    public function getAllPlans(bool $onlyActive = false): Collection;

    /**
     * Buscar un plan por ID o código.
     */
    public function findPlanByCodeOrId(string|int $codeOrId): ?SaasPlan;

    /**
     * Crear o actualizar un plan SaaS.
     *
     * @param array<string, mixed> $data
     * @param int|null $id
     */
    public function savePlan(array $data, ?int $id = null): SaasPlan;

    /**
     * Eliminar un plan SaaS.
     */
    public function deletePlan(int $id): bool;

    /**
     * Obtener suscripción por tenant_id.
     */
    public function findByTenantId(string $tenantId): ?SaasSubscription;

    /**
     * Obtener suscripción por llave de licencia.
     */
    public function findByLicenseKey(string $licenseKey): ?SaasSubscription;

    /**
     * Obtener suscripción por ID.
     */
    public function findById(int $id): ?SaasSubscription;

    /**
     * Crear o actualizar suscripción asociada a un tenant.
     *
     * @param string $tenantId
     * @param array<string, mixed> $attributes
     */
    public function upsertForTenant(string $tenantId, array $attributes): SaasSubscription;

    /**
     * Obtener todas las suscripciones con sus planes cargados.
     *
     * @return Collection<int, SaasSubscription>
     */
    public function getAllSubscriptions(): Collection;

    /**
     * Registrar un pago de suscripción.
     *
     * @param array<string, mixed> $data
     */
    public function createPayment(array $data): SaasSubscriptionPayment;

    /**
     * Obtener listado paginado de pagos de suscripciones.
     *
     * @param array<string, mixed> $filters
     * @param int $perPage
     */
    public function paginatePayments(array $filters = [], int $perPage = 25): LengthAwarePaginator;

    /**
     * Buscar pago por ID.
     */
    public function findPaymentById(int $paymentId): ?SaasSubscriptionPayment;
}
