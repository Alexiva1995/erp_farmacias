<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Donation\DonationService;
use App\Http\Requests\Donation\CreateDonationRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Barryvdh\DomPDF\Facade\Pdf;

class DonationController extends Controller
{
    public function __construct(private DonationService $donationService)
    {
    }

    public function create(CreateDonationRequest $request)
    {
        $validated = $request->validated();

        try {
            $this->donationService->recordDonation($validated);
            return response()->json(['message' => 'Donación registrada con éxito.'], 201);
        } catch (\Exception $e) {
            \Log::error('Error al registrar la donación: ' . $e->getMessage());
            return response()->json(['message' => 'Error al registrar la donación.'], 500);
        }
    }

    public function getMonthlyDonationData(string $month)
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            return response()->json(['message' => 'Formato de mes inválido.'], 400);
        }

        $donationData = $this->donationService->getMonthlyDonationData($month);
        if (!$donationData) {
            return response()->json(['message' => 'No se encontró donación para este mes.'], 404);
        }

        return response()->json($donationData);
    }
}
