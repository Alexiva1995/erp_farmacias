<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Islr\IslrQueryService;
use App\Services\Islr\IslrActionService;
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
        $year = $request->input('year', now()->year);

        $deductions = $this->islrQueryService->calculateDeductions($year);
        $grossIncome = $this->islrQueryService->calculateGrossIncome($year);
        $costs = $this->islrQueryService->calculateCosts($year);

        $startDate = \Carbon\Carbon::create($year, 1, 1)->startOfDay();
        $endDate = \Carbon\Carbon::create($year, 12, 31)->endOfDay();

        $fiscalTotal = \App\Models\FiscalHistory::whereBetween('invoice_date', [$startDate, $endDate])
            ->sum('total_amount');

        $netIncome = $fiscalTotal - $deductions;

        return response()->json([
            'data' => [
                'gross_income' => $grossIncome,
                'deductions' => $deductions,
                'net_income' => $netIncome,
                'ibg' => $fiscalTotal,
                'costs' => $costs,
                'year' => $year,
                'currency' => 'VES',
                'calculated_at' => now()->toISOString()
            ],
            'message' => 'Resumen ISLR calculado con éxito.'
        ], 200);
    }

    public function getGrossIncome(Request $request)
    {
        $year = $request->input('year', now()->year);
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
        $year = $request->input('year', now()->year);
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
                    'message' => 'No hay unidad tributaria configurada'
                ],
                'message' => 'No se encontró unidad tributaria activa.'
            ], 404);
        }

        return response()->json([
            'data' => [
                'id' => $taxUnit->id,
                'value' => $taxUnit->value,
                'effective_date' => $taxUnit->effective_date->format('Y-m-d'),
                'notes' => $taxUnit->notes,
                'currency' => 'VES'
            ],
            'message' => 'Unidad tributaria obtenida con éxito.'
        ], 200);
    }

    /**
     * Actualiza o crea una nueva unidad tributaria
     */
    public function updateTaxUnit(Request $request)
    {
        $validated = $request->validate([
            'value' => 'required|numeric|min:0',
            'effective_date' => 'nullable|date',
            'notes' => 'nullable|string|max:500'
        ]);

        $taxUnit = $this->islrActionService->createOrUpdateTaxUnit($validated);

        return response()->json([
            'data' => [
                'id' => $taxUnit->id,
                'value' => $taxUnit->value,
                'effective_date' => $taxUnit->effective_date->format('Y-m-d'),
                'notes' => $taxUnit->notes
            ],
            'message' => 'Unidad tributaria actualizada con éxito.'
        ], 200);
    }
    public function getDeclaration(Request $request)
    {
        $year = $request->input('year', now()->year);

        $declaration = $this->islrQueryService->getDeclarationByYear($year);

        if (!$declaration) {
            return response()->json([
                'data' => null,
                'message' => "No se encontró declaración para el año {$year}."
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
    public function createDeclaration(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:' . (now()->year + 1),
            'amount' => 'required|numeric|min:0',
            'status' => 'nullable|in:paid,unpaid',
            'declaration_date' => 'nullable|date',
        ]);

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
    public function updateDeclaration(Request $request, $id)
    {
        $validated = $request->validate([
            'year' => 'nullable|integer|min:2000|max:' . (now()->year + 1),
            'amount' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:paid,unpaid',
            'declaration_date' => 'nullable|date',
        ]);

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
