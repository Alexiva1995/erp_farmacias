<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Contracts\DiningReservationServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurant\StoreDiningReservationRequest;
use App\Http\Resources\Restaurant\DiningReservationResource;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiningReservationController extends Controller
{
    public function __construct(
        protected DiningReservationServiceInterface $reservationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $res = $this->reservationService->listReservations($request->all());
        return response()->json($res);
    }

    public function store(StoreDiningReservationRequest $request): JsonResponse
    {
        $reservation = $this->reservationService->createReservation($request->validated());

        return response()->json([
            'message' => 'Reserva creada exitosamente.',
            'reservation' => new DiningReservationResource($reservation),
        ], 201);
    }

    public function checkIn(Request $request, Reservation $reservation): JsonResponse
    {
        $sellerId = $request->user()?->id ?? 1;
        $order = $this->reservationService->checkIn($reservation, $sellerId);

        return response()->json([
            'message' => 'Check-in realizado. Mesa ocupada y comanda abierta.',
            'order_id' => $order->id,
        ]);
    }

    public function cancel(Reservation $reservation): JsonResponse
    {
        $this->reservationService->cancelReservation($reservation);
        return response()->json(['message' => 'Reserva cancelada correctamente.']);
    }
}
