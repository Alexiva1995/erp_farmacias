<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\GroupsProduct;
use App\Models\Laboratory;
use App\Models\Origin;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MasterCatalogService
{
    /**
     * Buscar un producto en el catálogo maestro por código de barra.
     */
    public function lookupByBarcode(string $barcode): array
    {
        $barcode = trim($barcode);
        if (empty($barcode)) {
            return ['found' => false, 'product' => null];
        }

        $product = Product::withoutGlobalScope('not_deleted')
            ->withTrashed()
            ->with(['laboratory', 'category', 'origin'])
            ->where('barcode', $barcode)
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->where('name', '!=', '.')
            ->first();

        if (!$product) {
            return ['found' => false, 'product' => null];
        }

        return [
            'found' => true,
            'product' => [
                'id'                => $product->id,
                'name'              => $product->name,
                'barcode'           => $product->barcode,
                'active_ingredient' => $product->active_ingredient,
                'laboratory_id'     => $product->laboratory_id,
                'laboratory_name'   => $product->laboratory?->name,
                'category_id'       => $product->category_id,
                'category_name'     => $product->category?->name,
                'origin_id'         => $product->origin_id,
                'origin_name'       => $product->origin?->name,
                'is_fractionable'   => (bool) ($product->is_fractionable ?? false),
                'fraction_name'     => $product->fraction_name ?? null,
                'units_per_fraction'=> $product->units_per_fraction ?? null,
                'psychotropic'      => (bool) ($product->psychotropic ?? false),
                'iva'               => (float) ($product->iva ?? 0),
                'photo_url'         => $product->photo_url,
            ],
        ];
    }

    /**
     * Registrar o asegurar un producto en el Catálogo Maestro y devolver su ID oficial.
     */
    public function registerMasterProduct(array $data): array
    {
        $barcode = !empty($data['barcode']) ? trim((string) $data['barcode']) : null;

        // 1. Si ya existe un producto con este código de barras, devolver el existente
        if ($barcode) {
            $existing = Product::withoutGlobalScope('not_deleted')
                ->withTrashed()
                ->with(['laboratory', 'category', 'origin'])
                ->where('barcode', $barcode)
                ->first();

            if ($existing) {
                return [
                    'created' => false,
                    'product' => [
                        'id'                => $existing->id,
                        'name'              => $existing->name,
                        'barcode'           => $existing->barcode,
                        'active_ingredient' => $existing->active_ingredient,
                        'laboratory_id'     => $existing->laboratory_id,
                        'laboratory_name'   => $existing->laboratory?->name,
                        'category_id'       => $existing->category_id,
                        'origin_id'         => $existing->origin_id,
                    ],
                ];
            }
        }

        // 2. Homologar laboratorio si viene el nombre
        $laboratoryId = $data['laboratory_id'] ?? null;
        if (!empty($data['laboratory_name'])) {
            $lab = Laboratory::firstOrCreate(
                ['name' => trim($data['laboratory_name'])],
                ['created_at' => now(), 'updated_at' => now()]
            );
            $laboratoryId = $lab->id;
        }

        // 3. Crear el nuevo producto en el Master obteniendo el siguiente ID oficial
        // Se crea con is_deleted = 1 / is_active = 0 para que exista en el catálogo de la BD
        // pero NO aparezca en las vistas de inventario/POS de la farmacia Master
        $product = Product::create([
            'name'              => trim($data['name'] ?? ''),
            'barcode'           => $barcode,
            'active_ingredient' => $data['active_ingredient'] ?? null,
            'laboratory_id'     => $laboratoryId,
            'category_id'       => $data['category_id'] ?? null,
            'origin_id'         => $data['origin_id'] ?? null,
            'unit_cost'         => (float) ($data['unit_cost'] ?? 0),
            'sale_price'        => (float) ($data['sale_price'] ?? 0),
            'is_fractionable'   => (bool) ($data['is_fractionable'] ?? false),
            'fraction_name'     => $data['fraction_name'] ?? null,
            'units_per_fraction'=> $data['units_per_fraction'] ?? null,
            'psychotropic'      => (bool) ($data['psychotropic'] ?? false),
            'iva'               => (float) ($data['iva'] ?? 0),
            'is_deleted'        => true,
            'is_active'         => false,
        ]);

        $product->load(['laboratory', 'category', 'origin']);

        return [
            'created' => true,
            'product' => [
                'id'                => $product->id,
                'name'              => $product->name,
                'barcode'           => $product->barcode,
                'active_ingredient' => $product->active_ingredient,
                'laboratory_id'     => $product->laboratory_id,
                'laboratory_name'   => $product->laboratory?->name,
                'category_id'       => $product->category_id,
                'origin_id'         => $product->origin_id,
            ],
        ];
    }

    /**
     * Registrar o asegurar un laboratorio en el Catálogo Maestro.
     */
    public function registerMasterLaboratory(array $data): array
    {
        $name = trim($data['name'] ?? '');
        if (empty($name)) {
            throw new \InvalidArgumentException('El nombre del laboratorio es requerido.');
        }

        $existing = Laboratory::where('name', $name)->first();
        if ($existing) {
            return [
                'created' => false,
                'laboratory' => [
                    'id'   => $existing->id,
                    'name' => $existing->name,
                ],
            ];
        }

        $lab = Laboratory::create([
            'name'      => $name,
            'group_id'  => $data['group_id'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
        ]);

        return [
            'created' => true,
            'laboratory' => [
                'id'   => $lab->id,
                'name' => $lab->name,
            ],
        ];
    }

    /**
     * Registrar o asegurar un grupo en el Catálogo Maestro.
     */
    public function registerMasterGroup(array $data): array
    {
        $name = trim($data['name'] ?? '');
        if (empty($name)) {
            throw new \InvalidArgumentException('El nombre del grupo es requerido.');
        }

        $existing = GroupsProduct::where('name', $name)->first();
        if ($existing) {
            return [
                'created' => false,
                'group' => [
                    'id'   => $existing->id,
                    'name' => $existing->name,
                ],
            ];
        }

        $group = GroupsProduct::create([
            'name' => $name,
        ]);

        return [
            'created' => true,
            'group' => [
                'id'   => $group->id,
                'name' => $group->name,
            ],
        ];
    }

    /**
     * Registrar o asegurar un proveedor en el Catálogo Maestro.
     */
    public function registerMasterSupplier(array $data): array
    {
        $name = trim($data['name'] ?? '');
        $rif = !empty($data['rif']) ? trim($data['rif']) : null;

        // Buscar por RIF o Nombre
        $query = Supplier::query();
        if ($rif) {
            $query->where('rif', $rif);
        } else {
            $query->where('name', $name);
        }

        $existing = $query->first();
        if ($existing) {
            return [
                'created' => false,
                'supplier' => [
                    'id'            => $existing->id,
                    'name'          => $existing->name,
                    'rif'           => $existing->rif,
                    'social_reason' => $existing->social_reason,
                ],
            ];
        }

        $supplier = Supplier::create([
            'name'             => $name,
            'social_reason'    => $data['social_reason'] ?? $name,
            'rif'              => $rif,
            'sales_phone'      => $data['sales_phone'] ?? null,
            'collections_phone'=> $data['collections_phone'] ?? null,
            'credit_days'      => (int) ($data['credit_days'] ?? 0),
            'dispatch_days'    => $data['dispatch_days'] ?? [],
            'order_days'       => $data['order_days'] ?? [],
            'payment_method'   => $data['payment_method'] ?? 'Bs',
            'cash_payment'     => (bool) ($data['cash_payment'] ?? true),
            'charges_igtf'     => (bool) ($data['charges_igtf'] ?? false),
        ]);

        return [
            'created' => true,
            'supplier' => [
                'id'            => $supplier->id,
                'name'          => $supplier->name,
                'rif'           => $supplier->rif,
                'social_reason' => $supplier->social_reason,
            ],
        ];
    }

    /**
     * Registrar o asegurar un origen en el Catálogo Maestro.
     */
    public function registerMasterOrigin(array $data): array
    {
        $name = trim($data['name'] ?? '');
        if (empty($name)) {
            throw new \InvalidArgumentException('El nombre del origen es requerido.');
        }

        $existing = Origin::where('name', $name)->first();
        if ($existing) {
            return [
                'created' => false,
                'origin'  => [
                    'id'   => $existing->id,
                    'name' => $existing->name,
                ],
            ];
        }

        $origin = Origin::create([
            'name' => $name,
        ]);

        return [
            'created' => true,
            'origin'  => [
                'id'   => $origin->id,
                'name' => $origin->name,
            ],
        ];
    }
}
