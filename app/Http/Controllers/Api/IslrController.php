<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Islr\IslrQueryService;
use App\Services\Islr\IslrActionService;
use App\Http\Requests\Islr\UpdateTaxUnitRequest;
use App\Http\Requests\Islr\StoreIslrDeclarationRequest;
use App\Http\Requests\Islr\UpdateIslrDeclarationRequest;
use App\Http\Resources\Islr\IslrSummaryResource;
use App\Http\Resources\Islr\TaxUnitResource;
use Illuminate\Http\Request;

class IslrController extends Controller
{
    public function __construct(
        private IslrQueryService $islrQueryService,
        private IslrActionService $islrActionService
    ) {
    }

    public function getIslrSummary(Request $request)
    {
        $year = (int) $request->input('year', now()->year);

        $summaryData = $this->islrQueryService->getSummaryData($year);

        return (new IslrSummaryResource($summaryData))
            ->additional(['message' => 'Resumen ISLR calculado con éxito.']);
    }

    public function getGrossIncome(Request $request)
    {
        $year = (int) $request->input('year', now()->year);
        $grossIncome = $this->islrQueryService->calculateGrossIncome($year);

        return response()->json([
            'data' => [
                'total_value' => $grossIncome,
                'year' => $year,
                'currency' => 'VES',
                'calculated_at' => now()->toISOString()
            ],
            'message' => 'Renta bruta calculada con éxito.'
        ], 200);
    }

    public function getDeductions(Request $request)
    {
        $year = (int) $request->input('year', now()->year);
        $deductions = $this->islrQueryService->calculateDeductions($year);

        return response()->json([
            'data' => [
                'total_value' => $deductions,
                'year' => $year,
                'currency' => 'VES',
                'calculated_at' => now()->toISOString()
            ],
            'message' => 'Deducciones calculadas con éxito.'
        ], 200);
    }

    /**
     * Obtiene la unidad tributaria activa actual
     */
    public function getTaxUnit()
    {
        $taxUnit = $this->islrQueryService->getActiveTaxUnit();

        if (!$taxUnit) {
            return response()->json([
                'data' => [
                    'value' => 0,
                    'effective_date' => null,
                    'notes' => null,
                    'currency' => 'VES',
                ],
                'message' => 'No se encontró unidad tributaria activa.'
            ]);
        }

        return (new TaxUnitResource($taxUnit))
            ->additional(['message' => 'Unidad tributaria obtenida con éxito.']);
    }

    /**
     * Actualiza o crea una nueva unidad tributaria
     */
    public function updateTaxUnit(UpdateTaxUnitRequest $request)
    {
        $validated = $request->validated();

        $taxUnit = $this->islrActionService->createOrUpdateTaxUnit($validated);

        return (new TaxUnitResource($taxUnit))
            ->additional(['message' => 'Unidad tributaria actualizada con éxito.']);
    }
    public function getDeclaration(Request $request)
    {
        $year = $request->input('year', now()->year);

        $declaration = $this->islrQueryService->getDeclarationByYear($year);

        if (!$declaration) {
            return response()->json([
                'data' => null,
                'message' => "No se encontró declaración para el año {$year}."
            ]);
        }

        return response()->json([
            'data' => [
                'id' => $declaration->id,
                'year' => $declaration->year,
                'amount' => $declaration->amount,
                'status' => $declaration->status,
                'status_text' => $declaration->status_text,
                'status_color' => $declaration->status_color,
                'declaration_date' => $declaration->declaration_date->format('Y-m-d'),
                'is_paid' => $declaration->isPaid(),
            ],
            'message' => 'Declaración obtenida con éxito.'
        ], 200);
    }

    /**
     * Obtiene la última declaración
     */
    public function getLatestDeclaration()
    {
        $declaration = $this->islrQueryService->getLatestDeclaration();

        if (!$declaration) {
            return response()->json([
                'data' => null,
                'message' => 'No se encontraron declaraciones.'
            ], 404);
        }

        return response()->json([
            'data' => [
                'id' => $declaration->id,
                'year' => $declaration->year,
                'amount' => $declaration->amount,
                'status' => $declaration->status,
                'status_text' => $declaration->status_text,
                'status_color' => $declaration->status_color,
                'declaration_date' => $declaration->declaration_date->format('Y-m-d'),
                'is_paid' => $declaration->isPaid(),
            ],
            'message' => 'Última declaración obtenida con éxito.'
        ], 200);
    }

    /**
     * Crea una nueva declaración
     */
    public function createDeclaration(StoreIslrDeclarationRequest $request)
    {
        $validated = $request->validated();

        try {
            $declaration = $this->islrActionService->createDeclaration($validated);

            return response()->json([
                'data' => [
                    'id' => $declaration->id,
                    'year' => $declaration->year,
                    'amount' => $declaration->amount,
                    'status' => $declaration->status,
                    'status_text' => $declaration->status_text,
                    'declaration_date' => $declaration->declaration_date->format('Y-m-d'),
                ],
                'message' => 'Declaración creada con éxito.'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Actualiza una declaración existente
     */
    public function updateDeclaration(UpdateIslrDeclarationRequest $request, $id)
    {
        $validated = $request->validated();

        try {
            $declaration = $this->islrActionService->updateDeclaration($id, $validated);

            return response()->json([
                'data' => [
                    'id' => $declaration->id,
                    'year' => $declaration->year,
                    'amount' => $declaration->amount,
                    'status' => $declaration->status,
                    'status_text' => $declaration->status_text,
                    'declaration_date' => $declaration->declaration_date->format('Y-m-d'),
                ],
                'message' => 'Declaración actualizada con éxito.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Marca una declaración como pagada
     */
    public function markAsPaid($id)
    {
        try {
            $declaration = $this->islrActionService->markDeclarationAsPaid($id);

            return response()->json([
                'data' => [
                    'id' => $declaration->id,
                    'status' => $declaration->status,
                    'status_text' => $declaration->status_text,
                ],
                'message' => 'Declaración marcada como pagada.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Marca una declaración como no pagada
     */
    public function markAsUnpaid($id)
    {
        try {
            $declaration = $this->islrActionService->markDeclarationAsUnpaid($id);

            return response()->json([
                'data' => [
                    'id' => $declaration->id,
                    'status' => $declaration->status,
                    'status_text' => $declaration->status_text,
                ],
                'message' => 'Declaración marcada como no pagada.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Elimina una declaración
     */
    public function deleteDeclaration($id)
    {
        try {
            $this->islrActionService->deleteDeclaration($id);

            return response()->json([
                'message' => 'Declaración eliminada con éxito.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }
}
