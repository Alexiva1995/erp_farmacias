<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\ModifierGroup;
use Illuminate\Database\Eloquent\Collection;

interface ModifierGroupServiceInterface
{
    /**
     * Obtener todos los grupos con sus opciones.
     */
    public function getAll(): Collection;

    /**
     * Obtener un grupo por ID.
     */
    public function find(int $id): ?ModifierGroup;

    /**
     * Crear un grupo de modificadores con sus opciones.
     */
    public function create(array $data): ModifierGroup;

    /**
     * Actualizar un grupo y sincronizar sus opciones.
     */
    public function update(ModifierGroup $group, array $data): ModifierGroup;

    /**
     * Eliminar un grupo de modificadores.
     */
    public function delete(ModifierGroup $group): bool;
}
