<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\KitchenStation;
use Illuminate\Database\Eloquent\Collection;

interface KitchenStationServiceInterface
{
    /**
     * Obtener todas las estaciones de cocina activas.
     */
    public function getAll(): Collection;

    /**
     * Obtener una estación por ID.
     */
    public function find(int $id): ?KitchenStation;

    /**
     * Crear una nueva estación.
     */
    public function create(array $data): KitchenStation;

    /**
     * Actualizar una estación existente.
     */
    public function update(KitchenStation $station, array $data): KitchenStation;

    /**
     * Eliminar una estación.
     */
    public function delete(KitchenStation $station): bool;
}
