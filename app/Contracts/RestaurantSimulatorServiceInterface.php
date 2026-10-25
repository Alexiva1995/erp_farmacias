<?php

namespace App\Contracts;

interface RestaurantSimulatorServiceInterface
{
    public function triggerSingleOrder(): array;

    public function advanceKitchenCycle(): array;

    public function triggerGuestAction(): array;

    public function runFullCycle(): array;

    public function resetSimulation(): array;

    public function getStatus(): array;
}
