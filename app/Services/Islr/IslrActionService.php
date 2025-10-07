<?php

namespace App\Services\Islr;

use App\Models\TaxUnit;
use Carbon\Carbon;

class IslrActionService
{
    /**
     * Crea o actualiza una unidad tributaria
     * Desactiva todas las anteriores automáticamente
     * 
     * @param array $data
     * @return TaxUnit
     */
    public function createOrUpdateTaxUnit(array $data): TaxUnit
    {
        TaxUnit::deactivateAll();

        $taxUnit = TaxUnit::create([
            'value' => $data['value'],
            'effective_date' => $data['effective_date'] ?? Carbon::now(),
            'notes' => $data['notes'] ?? null,
            'is_active' => true
        ]);

        return $taxUnit;
    }

    /**
     * Desactiva una unidad tributaria específica
     * 
     * @param int $taxUnitId
     * @return bool
     */
    public function deactivateTaxUnit(int $taxUnitId): bool
    {
        $taxUnit = TaxUnit::find($taxUnitId);

        if (!$taxUnit) {
            return false;
        }

        $taxUnit->is_active = false;
        $taxUnit->save();

        return true;
    }

    /**
     * Activa una unidad tributaria existente
     * Desactiva todas las demás
     * 
     * @param int $taxUnitId
     * @return TaxUnit|null
     */
    public function activateTaxUnit(int $taxUnitId): ?TaxUnit
    {
        $taxUnit = TaxUnit::find($taxUnitId);

        if (!$taxUnit) {
            return null;
        }

        TaxUnit::deactivateAll();

        $taxUnit->is_active = true;
        $taxUnit->save();

        return $taxUnit;
    }

    /**
     * Elimina una unidad tributaria del historial
     * Solo permite eliminar si no está activa
     * 
     * @param int $taxUnitId
     * @return bool
     */
    public function deleteTaxUnit(int $taxUnitId): bool
    {
        $taxUnit = TaxUnit::find($taxUnitId);

        if (!$taxUnit) {
            return false;
        }

        if ($taxUnit->is_active) {
            return false;
        }

        $taxUnit->delete();
        return true;
    }
}
