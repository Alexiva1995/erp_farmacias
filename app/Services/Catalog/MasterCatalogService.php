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
                'description'       => $product->description,
                'presentation'      => $product->presentation,
                'unit_of_measure'   => $product->unit_of_measure,
                'psychotropic'      => (bool) ($product->psychotropic ?? false),
                'iva'               => (float) ($product->iva ?? 0),
                'photo_url'         => $product->photo_url,
            ],
        ];
    }

    /**
     * Buscar múltiples productos en el catálogo maestro por lista de códigos de barra (Bulk Lookup).
     */
    public function lookupBulk(array $barcodes): array
    {
        $cleanBarcodes = array_values(array_filter(array_unique(array_map('trim', $barcodes))));
        if (empty($cleanBarcodes)) {
            return [];
        }

        $products = Product::withoutGlobalScope('not_deleted')
            ->withTrashed()
            ->with(['laboratory:id,name', 'category:id,name', 'origin:id,name'])
            ->whereIn('barcode', $cleanBarcodes)
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->where('name', '!=', '.')
            ->get();

        $results = [];
        foreach ($products as $product) {
            $results[$product->barcode] = [
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
                'description'       => $product->description,
                'presentation'      => $product->presentation,
                'unit_of_measure'   => $product->unit_of_measure,
                'psychotropic'      => (bool) ($product->psychotropic ?? false),
                'iva'               => (float) ($product->iva ?? 0),
                'photo_url'         => $product->photo_url,
            ];
        }

        return $results;
    }

    /**
     * Registrar o asegurar un producto en el Catálogo Maestro y devolver su ID oficial.
     */
    public function registerMasterProduct(array $data): array
    {
        $barcode = !empty($data['barcode']) ? trim((string) $data['barcode']) : null;

        // 1. Homologar laboratorio si viene el nombre o validar id
        $laboratoryId = null;
        if (!empty($data['laboratory_name'])) {
            $lab = Laboratory::firstOrCreate(
                ['name' => trim($data['laboratory_name'])],
                ['created_at' => now(), 'updated_at' => now()]
            );
            $laboratoryId = $lab->id;
        } elseif (!empty($data['laboratory_id']) && Laboratory::where('id', $data['laboratory_id'])->exists()) {
            $laboratoryId = (int) $data['laboratory_id'];
        }

        // 2. Homologar origen si viene el nombre o validar id
        $originId = null;
        if (!empty($data['origin_name'])) {
            $orig = Origin::firstOrCreate(
                ['name' => trim($data['origin_name'])],
                ['created_at' => now(), 'updated_at' => now()]
            );
            $originId = $orig->id;
        } elseif (!empty($data['origin_id']) && Origin::where('id', $data['origin_id'])->exists()) {
            $originId = (int) $data['origin_id'];
        }

        // 3. Homologar categoría si viene el nombre o validar id
        $categoryId = null;
        if (!empty($data['category_name'])) {
            $cat = Category::firstOrCreate(
                ['name' => trim($data['category_name'])],
                ['created_at' => now(), 'updated_at' => now()]
            );
            $categoryId = $cat->id;
        } elseif (!empty($data['category_id']) && Category::where('id', $data['category_id'])->exists()) {
            $categoryId = (int) $data['category_id'];
        }

        // 4. Si ya existe un producto con este código de barras, enriquecer sus datos faltantes
        if ($barcode) {
            $existing = Product::withoutGlobalScope('not_deleted')
                ->withTrashed()
                ->with(['laboratory', 'category', 'origin'])
                ->where('barcode', $barcode)
                ->first();

            if ($existing) {
                $hasUpdates = false;

                if (empty($existing->laboratory_id) && $laboratoryId) {
                    $existing->laboratory_id = $laboratoryId;
                    $hasUpdates = true;
                }
                if (empty($existing->origin_id) && $originId) {
                    $existing->origin_id = $originId;
                    $hasUpdates = true;
                }
                if (empty($existing->category_id) && $categoryId) {
                    $existing->category_id = $categoryId;
                    $hasUpdates = true;
                }
                if (empty($existing->active_ingredient) && !empty($data['active_ingredient'])) {
                    $existing->active_ingredient = $data['active_ingredient'];
                    $hasUpdates = true;
                }
                if (empty($existing->description) && !empty($data['description'])) {
                    $existing->description = $data['description'];
                    $hasUpdates = true;
                }
                if (empty($existing->photo_url) && !empty($data['photo_url'])) {
                    $existing->photo_url = $data['photo_url'];
                    $hasUpdates = true;
                }
                if (empty($existing->presentation) && !empty($data['presentation'])) {
                    $existing->presentation = $data['presentation'];
                    $hasUpdates = true;
                }
                if (empty($existing->unit_of_measure) && !empty($data['unit_of_measure'])) {
                    $existing->unit_of_measure = $data['unit_of_measure'];
                    $hasUpdates = true;
                }

                if ($hasUpdates) {
                    $existing->save();
                    $existing->load(['laboratory', 'category', 'origin']);
                }

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

        // 5. Crear el nuevo producto en el Master obteniendo el siguiente ID oficial
        // Se crea con is_deleted = 1 / is_active = 0 para que exista en el catálogo de la BD
        // pero NO aparezca en las vistas de inventario/POS de la farmacia Master
        $product = Product::create([
            'name'              => trim($data['name'] ?? ''),
            'barcode'           => $barcode,
            'active_ingredient' => $data['active_ingredient'] ?? null,
            'laboratory_id'     => $laboratoryId,
            'category_id'       => $categoryId,
            'origin_id'         => $originId,
            'unit_cost'         => (float) ($data['unit_cost'] ?? 0),
            'sale_price'        => (float) ($data['sale_price'] ?? 0),
            'description'       => $data['description'] ?? null,
            'presentation'      => $data['presentation'] ?? null,
            'unit_of_measure'   => $data['unit_of_measure'] ?? null,
            'photo_url'         => $data['photo_url'] ?? null,
            'psychotropic'      => (bool) ($data['psychotropic'] ?? false),
            'iva'               => is_numeric($data['iva'] ?? null) ? (float) $data['iva'] : ((bool) ($data['iva'] ?? false) ? 16.0 : 0.0),
            'is_deleted'        => true,
            'is_active'         => false,
            'deleted_at'        => now(),
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

    /**
     * Registrar o asegurar una categoría en el Catálogo Maestro.
     */
    public function registerMasterCategory(array $data): array
    {
        $name = trim($data['name'] ?? '');
        if (empty($name)) {
            throw new \InvalidArgumentException('El nombre de la categoría es requerido.');
        }

        $existing = Category::where('name', $name)->first();
        if ($existing) {
            return [
                'created' => false,
                'category' => [
                    'id'   => $existing->id,
                    'name' => $existing->name,
                ],
            ];
        }

        $category = Category::create([
            'name' => $name,
        ]);

        return [
            'created' => true,
            'category' => [
                'id'   => $category->id,
                'name' => $category->name,
            ],
        ];
    }

    /**
     * Exporta en bloque laboratorios, orígenes, grupos, categorías y proveedores del Master.
     */
    public function exportEntities(array $entities): array
    {
        $result = [];

        if (in_array('groups_laboratories', $entities) || in_array('laboratories', $entities)) {
            if (\Illuminate\Support\Facades\Schema::hasTable('groups_laboratories')) {
                $q = DB::table('groups_laboratories');
                if (\Illuminate\Support\Facades\Schema::hasColumn('groups_laboratories', 'deleted_at')) {
                    $q->whereNull('deleted_at');
                }
                $result['groups_laboratories'] = $q->orderBy('id')->get()->toArray();
            }
        }

        if (in_array('laboratories', $entities)) {
            $q = DB::table('laboratories');
            if (\Illuminate\Support\Facades\Schema::hasColumn('laboratories', 'deleted_at')) {
                $q->whereNull('deleted_at');
            }
            $result['laboratories'] = $q->orderBy('id')->get()->toArray();
        }

        if (in_array('origins', $entities)) {
            $q = DB::table('origins');
            if (\Illuminate\Support\Facades\Schema::hasColumn('origins', 'deleted_at')) {
                $q->whereNull('deleted_at');
            }
            $result['origins'] = $q->orderBy('id')->get()->toArray();
        }

        if (in_array('groups', $entities) || in_array('groups_products', $entities)) {
            $q = DB::table('groups_products');
            if (\Illuminate\Support\Facades\Schema::hasColumn('groups_products', 'deleted_at')) {
                $q->whereNull('deleted_at');
            }
            $result['groups'] = $q->orderBy('id')->get()->toArray();
        }

        if (in_array('categories', $entities)) {
            $q = DB::table('categories');
            if (\Illuminate\Support\Facades\Schema::hasColumn('categories', 'deleted_at')) {
                $q->whereNull('deleted_at');
            }
            $result['categories'] = $q->orderBy('id')->get()->toArray();
        }

        if (in_array('suppliers', $entities)) {
            $q = DB::table('suppliers');
            if (\Illuminate\Support\Facades\Schema::hasColumn('suppliers', 'deleted_at')) {
                $q->whereNull('deleted_at');
            }
            $result['suppliers'] = $q->orderBy('id')->get()->toArray();
        }

        return $result;
    }
}

