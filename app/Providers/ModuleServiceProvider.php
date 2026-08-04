<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Registro de servicios condicionales basados en configuración de módulos.
     */
    public function register(): void
    {
        $enabledModules = $this->getEnabledModules();

        // Módulo de Farmacia / Salud
        if (in_array('pharmacy', $enabledModules)) {
            $this->registerPharmacyModule();
        }

        // Módulo de Restaurante
        if (in_array('restaurant', $enabledModules)) {
            $this->registerRestaurantModule();
        }

        // Módulo de Lotería
        if (in_array('lottery', $enabledModules)) {
            $this->registerLotteryModule();
        }

        // Módulo de Reservas
        if (in_array('reservation', $enabledModules)) {
            $this->registerReservationModule();
        }
    }

    /**
     * Obtiene los módulos habilitados de la configuración o variables de entorno.
     */
    public static function getEnabledModules(): array
    {
        $modulesString = env('ENABLED_MODULES');
        if (empty($modulesString) || app()->environment('testing')) {
            $modulesString = 'pharmacy,restaurant,lottery,reservation,sports_rental,minimarket';
        }
        return array_values(array_unique(array_map('trim', explode(',', strtolower((string)$modulesString)))));
    }

    /**
     * Registrar bindings del módulo de Farmacia.
     */
    private function registerPharmacyModule(): void
    {
        $this->app->when(\App\Http\Controllers\Api\DoctorController::class)
            ->needs(\App\Contracts\Doctor::class)
            ->give(\App\Services\DoctorServices::class);

        $this->app->when(\App\Http\Controllers\Api\DoctorController::class)
            ->needs(\App\Contracts\Specialty::class)
            ->give(\App\Services\SpecialtyServices::class);

        $this->app->when(\App\Http\Controllers\Api\SpecialtyController::class)
            ->needs(\App\Contracts\Specialty::class)
            ->give(\App\Services\SpecialtyServices::class);

        $this->app->when(\App\Http\Controllers\Api\LaboratoryController::class)
            ->needs(\App\Contracts\Laboratory::class)
            ->give(\App\Services\LaboratoryServices::class);
    }

    /**
     * Registrar bindings del módulo de Restaurante.
     */
    private function registerRestaurantModule(): void
    {
        $this->app->bind(\App\Contracts\Dish::class, \App\Services\DishServices::class);
    }

    /**
     * Registrar bindings del módulo de Lotería.
     */
    private function registerLotteryModule(): void
    {
        $this->app->bind(\App\Contracts\Lottery::class, \App\Services\LotteryServices::class);
    }

    /**
     * Registrar bindings del módulo de Reservas.
     */
    private function registerReservationModule(): void
    {
        $this->app->bind(
            \App\Contracts\Repositories\ReservationRepositoryInterface::class,
            \App\Repositories\ReservationRepository::class
        );
    }
}
