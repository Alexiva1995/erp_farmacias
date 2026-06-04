<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dish\StoreDishRequest;
use App\Http\Requests\Dish\UpdateDishRequest;
use App\Http\Resources\DishResource;
use App\Contracts\Dish as DishContract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;

class DishController extends Controller
{
    /**
     * @var DishContract
     */
    protected $dishService;

    /**
     * Constructor.
     */
    public function __construct(DishContract $dishService)
    {
        $this->dishService = $dishService;
    }

    /**
     * Obtener listado de platos.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $dishes = $this->dishService->getAll($request->all());
        return DishResource::collection($dishes);
    }

    /**
     * Crear un nuevo plato.
     */
    public function store(StoreDishRequest $request): DishResource
    {
        $dish = $this->dishService->create($request->validated());
        return new DishResource($dish);
    }

    /**
     * Obtener un plato específico.
     */
    public function show(int $id): DishResource
    {
        $dish = $this->dishService->find($id);
        if (!$dish) {
            abort(404, 'Plato no encontrado');
        }
        return new DishResource($dish);
    }

    /**
     * Actualizar un plato.
     */
    public function update(UpdateDishRequest $request, int $id): DishResource
    {
        $dish = $this->dishService->find($id);
        if (!$dish) {
            abort(404, 'Plato no encontrado');
        }
        $updatedDish = $this->dishService->update($dish, $request->validated());
        return new DishResource($updatedDish);
    }

    /**
     * Eliminar un plato.
     */
    public function destroy(int $id): JsonResponse
    {
        $dish = $this->dishService->find($id);
        if (!$dish) {
            return response()->json(['message' => 'Plato no encontrado.'], 404);
        }

        $this->dishService->delete($dish);

        return response()->json([
            'message' => 'Plato eliminado con éxito.'
        ]);
    }
}
