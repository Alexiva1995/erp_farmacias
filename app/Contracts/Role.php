<?php

namespace App\Contracts;

use App\Models\Role as RoleModel;
use Illuminate\Database\Eloquent\Collection;

interface Role
{
    /**
     * Listar todos los roles con conteo de usuarios.
     */
    public function list(): Collection;

    /**
     * Obtener un rol por su ID.
     */
    public function findById(int $id): ?RoleModel;

    /**
     * Crear un nuevo rol.
     */
    public function create(array $data): RoleModel;

    /**
     * Actualizar un rol existente.
     */
    public function update(int $id, array $data): RoleModel;

    /**
     * Eliminar un rol.
     */
    public function delete(int $id): bool;
}
