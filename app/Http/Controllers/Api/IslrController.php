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
}
