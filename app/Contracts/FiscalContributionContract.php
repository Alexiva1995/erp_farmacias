<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\FiscalContribution;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface FiscalContributionContract
{
    /**
     * Obtiene el listado paginado y filtrado de contribuciones fiscales.
     */
    public function getPaginated(array $filters, int $perPage): LengthAwarePaginator;

    /**
     * Obtiene las métricas y KPIs de compromisos fiscales.
     */
    public function getKpis(array $filters): array;

    /**
     * Crea una nueva contribución fiscal individual.
     */
    public function create(array $data): FiscalContribution;

    /**
     * Actualiza una contribución fiscal existente.
     */
    public function update(int $id, array $data): FiscalContribution;

    /**
     * Elimina una contribución fiscal.
     */
    public function delete(int $id): bool;

    /**
     * Importa o actualiza en lote múltiples contribuciones (Smart Paste o Bot).
     */
    public function batchUpsert(array $items, string $source = 'smart_paste', ?int $userId = null): array;

    /**
     * Alterna o establece el estado de pago de una contribución.
     */
    public function togglePaymentStatus(int $id, array $paymentData): FiscalContribution;
}
