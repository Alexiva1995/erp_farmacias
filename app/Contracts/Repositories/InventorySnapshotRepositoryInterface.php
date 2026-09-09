<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\InventorySnapshot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Interface InventorySnapshotRepositoryInterface
 * @package App\Contracts\Repositories
 */
interface InventorySnapshotRepositoryInterface
{
    /**
     * Obtener lista paginada de snapshots históricos.
     *
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function paginateSnapshots(array $filters): LengthAwarePaginator;

    /**
     * Obtener un snapshot por ID con sus ítems paginados/filtrados.
     *
     * @param int $snapshotId
     * @param array $filters
     * @return array
     */
    public function getSnapshotWithItems(int $snapshotId, array $filters): array;

    /**
     * Guardar un nuevo snapshot con sus ítems en base de datos.
     *
     * @param array $headerData
     * @param array $itemsData
     * @return InventorySnapshot
     */
    public function createSnapshot(array $headerData, array $itemsData): InventorySnapshot;

    /**
     * Eliminar un snapshot histórico por ID.
     *
     * @param int $snapshotId
     * @return bool
     */
    public function deleteSnapshot(int $snapshotId): bool;

    /**
     * Obtener todos los ítems de un snapshot para exportación.
     *
     * @param int $snapshotId
     * @return Collection
     */
    public function getSnapshotItemsForExport(int $snapshotId): Collection;
}
