<?php

declare(strict_types=1);

namespace App\Services\Groups;

use App\Models\GroupsProduct;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GroupActionService
{
    /**
     * Almacena un nuevo grupo en la base de datos.
     */
    public function createGroup(array $validatedData): GroupsProduct
    {
        if (config('catalog.role') === 'slave') {
            try {
                $masterClient = app(\App\Services\Catalog\MasterCatalogClientService::class);
                $masterGroup = $masterClient->registerGroupInMaster($validatedData);
                if (!empty($masterGroup['id'])) {
                    $validatedData['id'] = (int) $masterGroup['id'];
                }
            } catch (\Throwable $e) {
                Log::warning('No se pudo sincronizar grupo con Master Catalog: ' . $e->getMessage());
            }
        }

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

    public function associateProducts(GroupsProduct $group, array $productIds): void
    {
        $productIds = $productIds['productIds'];

        DB::transaction(function () use ($group, $productIds) {
            Product::where('group_id', $group->id)
                ->whereNotIn('id', $productIds)
                ->update(['group_id' => null]);

            if (!empty($productIds)) {
                Product::whereIn('id', $productIds)
                    ->update(['group_id' => $group->id]);
            }
        });
    }
}
