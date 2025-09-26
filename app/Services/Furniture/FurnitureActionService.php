<?php

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
        $validated = $this->validateFurnitureData($data);

        return Furniture::create($validated);
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

        $validated = $this->validateFurnitureData($data);
        $furniture->update($validated);

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
            return $furniture->delete();
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

    /**
     * Valida los datos del mobiliario
     * 
     * @param array $data
     * @return array
     */
    private function validateFurnitureData(array $data): array
    {
        // Validación básica de datos
        $validated = [
            'name' => trim($data['name']),
            'cost' => (float) $data['cost'],
            'acquisition_year' => (int) $data['acquisition_year'],
            'annual_depreciation_rate' => (float) $data['annual_depreciation_rate'],
        ];

        // Validaciones adicionales
        if (empty($validated['name'])) {
            throw new \InvalidArgumentException('El nombre del mobiliario es requerido');
        }

        if ($validated['cost'] <= 0) {
            throw new \InvalidArgumentException('El costo debe ser mayor a 0');
        }

        if ($validated['acquisition_year'] < 2000 || $validated['acquisition_year'] > date('Y')) {
            throw new \InvalidArgumentException('El año de adquisición debe estar entre 2000 y el año actual');
        }

        if ($validated['annual_depreciation_rate'] < 0 || $validated['annual_depreciation_rate'] > 100) {
            throw new \InvalidArgumentException('La tasa de depreciación debe estar entre 0% y 100%');
        }

        return $validated;
    }
}
