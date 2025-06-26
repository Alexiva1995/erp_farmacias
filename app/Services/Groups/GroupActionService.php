<?php

namespace App\Services\Groups;

use App\Models\GroupsProduct;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class GroupActionService
{
    /**
     * Almacena un nuevo grupo en la base de datos.
     */
    public function createGroup(array $validatedData): GroupsProduct
    {
        return GroupsProduct::create($validatedData);
    }

    /**
     * Actualiza un grupo existente.
     */
    public function updateGroup(GroupsProduct $group, array $validatedData): GroupsProduct
    {
        $group->update($validatedData);
        return $group->fresh();
    }

    /**
     * Elimina un grupo y desasigna los productos asociados.
     */
    public function deleteGroup(GroupsProduct $group): void
    {
        // Usamos una transacción para asegurar que ambas operaciones se completen con éxito.
        DB::transaction(function () use ($group) {
            Product::where('group_id', $group->id)->update(['group_id' => null]);

            $group->delete();
        });
    }
}
