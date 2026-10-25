<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Contracts\RestaurantSimulatorServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class RestaurantSimulatorController extends Controller
{
    public function __construct(
        protected RestaurantSimulatorServiceInterface $simulatorService
    ) {}

    public function status(): JsonResponse
    {
        return response()->json($this->simulatorService->getStatus());
    }

    public function triggerOrder(): JsonResponse
    {
        $res = $this->simulatorService->triggerSingleOrder();
        return response()->json($res);
    }

    public function advanceKitchen(): JsonResponse
    {
        $res = $this->simulatorService->advanceKitchenCycle();
        return response()->json($res);
    }

    public function triggerGuestAction(): JsonResponse
    {
        $res = $this->simulatorService->triggerGuestAction();
        return response()->json($res);
    }

    public function triggerCycle(): JsonResponse
    {
        $res = $this->simulatorService->runFullCycle();
        return response()->json($res);
    }

    public function reset(): JsonResponse
    {
        $res = $this->simulatorService->resetSimulation();
        return response()->json($res);
    }
}
