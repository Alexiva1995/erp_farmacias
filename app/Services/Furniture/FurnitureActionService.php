<?php

declare(strict_types=1);

namespace App\Services\Furniture;

use App\Models\Furniture;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class FurnitureActionService
{
    /**
     * Crea un nuevo mobiliario
     * 
     * @param array $data
     * @return Furniture
     */
    public function createFurniture(array $data): Furniture
    {
        return Furniture::create($data);
    }

    /**
     * Actualiza un mobiliario existente
     * 
     * @param Furniture $furniture
     * @param array $data
     * @return Furniture
     */
    public function updateFurniture(Furniture $furniture, array $data): Furniture
    {
        $furniture->update($data);

        return $furniture->fresh();
    }

    /**
     * Elimina un mobiliario
     * 
     * @param Furniture $furniture
     * @return bool
     * @throws \Exception
     */
    public function deleteFurniture(Furniture $furniture): bool
    {
        try {
            return (bool) $furniture->delete();
        } catch (\Exception $e) {
            throw new \Exception('No se pudo eliminar el mobiliario: ' . $e->getMessage());
        }
    }

    /**
     * Busca un mobiliario por ID
     * 
     * @param int $id
     * @return Furniture
     * @throws ModelNotFoundException
     */
    public function findFurnitureById(int $id): Furniture
    {
        return Furniture::findOrFail($id);
    }
}
