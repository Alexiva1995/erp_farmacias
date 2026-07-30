<?php

namespace App\Services;

use App\Contracts\Product;
use App\Http\Requests\InventoryStockFilterRequest;
use DateTime;
use DateTimeZone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class InventoryStockService
{
    public function __construct(
        protected Product $product
    ) {
    }

    /**
     * Extrae y normaliza los parámetros de filtrado desde el FormRequest.
     */
    public function extractFilters(InventoryStockFilterRequest $request): array
    {
        $validated = $request->validated();
        $filtros = [];

        if (isset($validated['itemsPerPage'])) {
            $filtros['itemsPerPage'] = $validated['itemsPerPage'];
        }
        if (isset($validated['page'])) {
            $filtros['page'] = $validated['page'];
        }
        if (!empty($validated['q'])) {
            $filtros['q'] = $validated['q'];
        }
        if (isset($validated['hasStock'])) {
            $filtros['hasStock'] = $validated['hasStock'];
        }
        if (isset($validated['laboratoryId'])) {
            $filtros['laboratoryId'] = $validated['laboratoryId'];
        }
        if (isset($validated['viewType'])) {
            $filtros['viewType'] = $validated['viewType'];
        }
        if (isset($validated['stock'])) {
            $filtros['stock'] = $validated['stock'];
        }
        if (isset($validated['expProd'])) {
            $filtros['expProd'] = $validated['expProd'];
        }
        if (!empty($validated['startDate']) && !empty($validated['endDate'])) {
            $filtros['startDate'] = $validated['startDate'];
            $filtros['endDate'] = $validated['endDate'];
        }
        if (!empty($validated['orderBy']) && !empty($validated['sortBy'])) {
            $filtros['orderBy'] = $validated['orderBy'];
            $filtros['sortBy'] = $validated['sortBy'];
        }
        if (isset($validated['days'])) {
            $timeZone = new DateTimeZone(config('app.timezone'));
            $dateToday = new DateTime('now', $timeZone);
            $filtros['days'] = $validated['days'];
            $previousDate = new DateTime('now', $timeZone);
            $previousDate->modify('-' . $filtros['days'] . ' days');
            $filtros['dateToday'] = $dateToday->format('Y-m-d H:i:s');
            $filtros['previousDate'] = $previousDate->format('Y-m-d');
        }
        if (isset($validated['isStrictSearch'])) {
            $filtros['isStrictSearch'] = (bool) $validated['isStrictSearch'];
        }
        $filtros['tipo_filtracion'] = $validated['tipo_filtracion'] ?? 'average';

        if (isset($validated['isColombian'])) {
            $filtros['isColombian'] = (bool) $validated['isColombian'];
        }

        return $filtros;
    }

    public function getFilteredStock(array $filtros): LengthAwarePaginator
    {
        return $this->product->filtrarStock($filtros);
    }

    public function getFilteredStockWithoutPaginate(array $filtros): Collection
    {
        return $this->product->filtrarStockWithoutPaginate($filtros);
    }
}
