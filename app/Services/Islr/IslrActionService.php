<?php

namespace App\Services\Islr;

use App\Models\IslrDeclaration;
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
    public function createDeclaration(array $data): IslrDeclaration
    {
        $year = $data['year'];

        if (IslrDeclaration::forYear($year)->exists()) {
            throw new \Exception("Ya existe una declaración para el año {$year}. No se puede crear otra.");
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
            return IslrDeclaration::create([
                'year' => $data['year'],
                'amount' => $data['amount'],
                'status' => $data['status'] ?? 'unpaid',
                'declaration_date' => $data['declaration_date'] ?? now(),
            ]);
        });
    }

    /**
     * Actualiza una declaración existente
     * 
     * @param int $id
     * @param array $data
     * @return IslrDeclaration
     * @throws \Exception
     */
    public function updateDeclaration(int $id, array $data): IslrDeclaration
    {
        $declaration = IslrDeclaration::findOrFail($id);

        if (isset($data['year']) && $data['year'] != $declaration->year) {
            if (IslrDeclaration::forYear($data['year'])->exists()) {
                throw new \Exception("Ya existe una declaración para el año {$data['year']}.");
            }
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($declaration, $data) {
            $declaration->update(array_filter([
                'year' => $data['year'] ?? null,
                'amount' => $data['amount'] ?? null,
                'status' => $data['status'] ?? null,
                'declaration_date' => $data['declaration_date'] ?? null,
            ], fn($value) => $value !== null));
        });

        return $declaration->fresh();
    }

    /**
     * Marca una declaración como pagada
     * 
     * @param int $id
     * @return IslrDeclaration
     */
    public function markDeclarationAsPaid(int $id): IslrDeclaration
    {
        $declaration = IslrDeclaration::findOrFail($id);

        \Illuminate\Support\Facades\DB::transaction(function () use ($declaration) {
            $declaration->markAsPaid();
        });

        return $declaration->fresh();
    }

    /**
     * Marca una declaración como no pagada
     * 
     * @param int $id
     * @return IslrDeclaration
     */
    public function markDeclarationAsUnpaid(int $id): IslrDeclaration
    {
        $declaration = IslrDeclaration::findOrFail($id);

        \Illuminate\Support\Facades\DB::transaction(function () use ($declaration) {
            $declaration->markAsUnpaid();
        });

        return $declaration->fresh();
    }

    /**
     * Elimina una declaración
     * 
     * @param int $id
     * @return bool
     */
    public function deleteDeclaration(int $id): bool
    {
        $declaration = IslrDeclaration::findOrFail($id);

        return \Illuminate\Support\Facades\DB::transaction(function () use ($declaration) {
            return $declaration->delete();
        });
    }
}
