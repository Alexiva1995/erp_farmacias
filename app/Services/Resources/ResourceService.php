<?php

namespace App\Services\Resources;

use App\Models\Category;
use App\Models\Laboratory;
use App\Models\Origin;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class ResourceService
{
    /**
     * Obtiene una lista de todos los laboratorios, utilizando caché.
     */
    public function getLaboratories(): Collection
    {
        // 'Cache::remember' revisa si la clave 'resources.laboratories' existe en la caché.
        // Si existe, la devuelve.
        // Si no, ejecuta la función, guarda el resultado en la caché por 24 horas y lo devuelve.
        return Cache::remember('resources.laboratories', now()->addDay(), function () {
            return Laboratory::orderBy('name')->get(['id', 'name']);
        });
    }

    /**
     * Obtiene una lista de todos los orígenes, utilizando caché.
     */
    public function getOrigins(): Collection
    {
        return Cache::remember('resources.origins', now()->addDay(), function () {
            return Origin::orderBy('name')->get(['id', 'name']);
        });
    }

    /**
     * Obtiene una lista de todos los proveedores, utilizando caché.
     */
    public function getSuppliers(): Collection
    {
        return Cache::remember('resources.suppliers', now()->addDay(), function () {
            return Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']);
        });
    }

    /**
     * Obtiene una lista de todas las categorías, utilizando caché.
     */
    public function getCategories(): Collection
    {
        return Cache::remember('resources.categories', now()->addDay(), function () {
            return Category::orderBy('name')->get(['id', 'name']);
        });
    }
}
