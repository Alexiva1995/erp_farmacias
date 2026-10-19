<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Role as RoleContract;
use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;

class RoleRepository implements RoleContract
{
    /**
     * Listar todos los roles con su conteo de usuarios asociados.
     */
    public function list(): Collection
    {
        $columns = ['id', 'name', 'created_at', 'updated_at'];
        if (\Illuminate\Support\Facades\Schema::hasColumn('roles', 'menu_permissions')) {
            $columns[] = 'menu_permissions';
        }

        return Role::query()
            ->select($columns)
            ->withCount('users')
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Buscar un rol por su identificador.
     */
    public function findById(int $id): ?Role
    {
        return Role::query()
            ->withCount('users')
            ->find($id);
    }

    /**
     * Crear un nuevo rol en la base de datos.
     */
    public function create(array $data): Role
    {
        return Role::create($data);
    }

    /**
     * Actualizar los atributos de un rol.
     */
    public function update(int $id, array $data): Role
    {
        $role = Role::findOrFail($id);
        $role->update($data);

        return $role->fresh()->loadCount('users');
    }

    /**
     * Eliminar un rol de la base de datos.
     */
    public function delete(int $id): bool
    {
        $role = Role::findOrFail($id);

        return (bool) $role->delete();
    }
}
