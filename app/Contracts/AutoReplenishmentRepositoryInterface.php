<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\AutoReplenishmentConfig;
use Illuminate\Database\Eloquent\Collection;

interface AutoReplenishmentRepositoryInterface
{
    /**
     * Obtener todas las configuraciones con relaciones cargadas de forma eficiente.
     */
    public function getAllWithSupplier(): Collection;

    /**
     * Crear una nueva configuración de reposición automática.
     */
    public function create(array $data): AutoReplenishmentConfig;

    /**
     * Actualizar una configuración existente.
     */
    public function update(AutoReplenishmentConfig $config, array $data): AutoReplenishmentConfig;

    /**
     * Eliminar una configuración.
     */
    public function delete(AutoReplenishmentConfig $config): bool;
}
