<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AutoReplenishmentRepositoryInterface;
use App\Models\AutoReplenishmentConfig;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Artisan;

class AutoReplenishmentService
{
    public function __construct(
        protected AutoReplenishmentRepositoryInterface $repository
    ) {}

    /**
     * Listar configuraciones con relaciones cargadas.
     */
    public function listConfigs(): Collection
    {
        return $this->repository->getAllWithSupplier();
    }

    /**
     * Guardar una nueva regla de automatización.
     */
    public function createConfig(array $data): AutoReplenishmentConfig
    {
        $config = $this->repository->create($data);
        return $config->load('supplier:id,name');
    }

    /**
     * Actualizar una regla existente.
     */
    public function updateConfig(AutoReplenishmentConfig $config, array $data): AutoReplenishmentConfig
    {
        return $this->repository->update($config, $data);
    }

    /**
     * Eliminar regla de reposición.
     */
    public function deleteConfig(AutoReplenishmentConfig $config): bool
    {
        return $this->repository->delete($config);
    }

    /**
     * Ejecutar proceso de automatización para una regla dada.
     */
    public function runConfig(AutoReplenishmentConfig $config): array
    {
        $exitCode = Artisan::call('replenishment:run', [
            '--config' => $config->id,
        ]);

        $config->refresh();

        return [
            'success'           => $exitCode === 0,
            'message'           => $exitCode === 0 ? 'Ejecución completada.' : 'La ejecución terminó con errores. Revisa los logs.',
            'last_run_at'       => $config->last_run_at,
            'last_run_products' => $config->last_run_products,
            'last_run_orders'   => $config->last_run_orders,
        ];
    }
}
