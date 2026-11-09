<?php

namespace App\Contracts\Repositories;

use App\Models\KitchenWaste;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface KitchenWasteRepositoryInterface
{
    /**
     * Obtiene el listado paginado de mermas con relaciones precargadas.
     */
    public function paginate(array $filters = []): LengthAwarePaginator;

    /**
     * Obtiene métricas resumidas de mermas (pendientes, aprobadas, pérdida total).
     */
    public function getSummaryMetrics(array $filters = []): array;

    /**
     * Crea un nuevo registro de merma.
     */
    public function create(array $data): KitchenWaste;

    /**
     * Actualiza un registro de merma existente.
     */
    public function update(KitchenWaste $waste, array $data): KitchenWaste;
}
