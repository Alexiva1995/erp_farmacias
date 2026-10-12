<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Role as RoleContract;
use App\Models\Role;
use App\Repositories\RoleRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class RoleServices implements RoleContract
{
    public function __construct(
        protected RoleRepository $roleRepository
    ) {
    }

    /**
     * Listar todos los roles con su información y conteo de usuarios.
     */
    public function list(): Collection
    {
        return $this->roleRepository->list();
    }

    /**
     * Obtener rol por ID.
     */
    public function findById(int $id): ?Role
    {
        return $this->roleRepository->findById($id);
    }

    /**
     * Crear un nuevo rol.
     */
    public function create(array $data): Role
    {
        return $this->roleRepository->create($data);
    }

    /**
     * Actualizar los datos de un rol existente.
     */
    public function update(int $id, array $data): Role
    {
        return $this->roleRepository->update($id, $data);
    }

    /**
     * Eliminar un rol garantizando la integridad de datos.
     *
     * @throws ValidationException
     */
    public function delete(int $id): bool
    {
        $role = $this->roleRepository->findById($id);

        if (!$role) {
            throw ValidationException::withMessages([
                'id' => ['El rol solicitado no existe.'],
            ]);
        }

        // Impedir eliminación si hay usuarios asignados a este rol
        if ($role->users_count > 0) {
            throw ValidationException::withMessages([
                'id' => ["No se puede eliminar el rol '{$role->name}' porque tiene {$role->users_count} usuario(s) asignado(s). Reasigna los usuarios a otro rol primero."],
            ]);
        }

        return $this->roleRepository->delete($id);
    }

    /**
     * Actualizar específicamente los permisos de visualización del menú para un rol.
     */
    public function updateMenuPermissions(int $id, ?array $menuPermissions): Role
    {
        return $this->roleRepository->update($id, [
            'menu_permissions' => $menuPermissions,
        ]);
    }
}
