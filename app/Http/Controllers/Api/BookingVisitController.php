<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookingVisit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingVisitController extends Controller
{
    /**
     * Registrar una nueva visita (IP + User Agent) a la sección de reservas.
     */
    public function store(Request $request): JsonResponse
    {
        $ipAddress = $request->ip();
        
        // Evitar registrar visitas duplicadas de la misma IP en los últimos 5 minutos
        $exists = BookingVisit::where('ip_address', $ipAddress)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->exists();

        if (!$exists) {
            BookingVisit::create([
                'ip_address' => $ipAddress,
                'user_agent' => $request->userAgent(),
                'converted' => false,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Visita registrada.'
        ]);
    }
}
