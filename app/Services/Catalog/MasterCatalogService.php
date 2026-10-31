<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MasterCatalogService
{
    /**
     * Retorna el nombre de la conexión que apunta al catálogo maestro tovaerp_master.
     */
    protected function getMasterConnection(): string
    {
        $conn = 'master_catalog';
        try {
            if (Schema::connection($conn)->hasTable('products')) {
                return $conn;
            }
        } catch (\Throwable) {
            // Fallback a conexión central si aún no se inicializó tovaerp_master
        }
        return config('tenancy.database.central_connection', 'mysql');
    }

    /**
     * Buscar un producto en el catálogo maestro por código de barra.
     */
    public function lookupByBarcode(string $barcode): array
    {
        $barcode = trim($barcode);
        if (empty($barcode)) {
            return ['found' => false, 'product' => null];
        }

        $conn = $this->getMasterConnection();

        $product = DB::connection($conn)->table('products')
            ->where('barcode', $barcode)
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->where('name', '!=', '.')
            ->first();

        if (!$product) {
            return ['found' => false, 'product' => null];
        }

        $labName = null;
        if (!empty($product->laboratory_id) && Schema::connection($conn)->hasTable('laboratories')) {
            $labName = DB::connection($conn)->table('laboratories')->where('id', $product->laboratory_id)->value('name');
        }

        $catName = null;
        if (!empty($product->category_id) && Schema::connection($conn)->hasTable('categories')) {
            $catName = DB::connection($conn)->table('categories')->where('id', $product->category_id)->value('name');
        }

        $origName = null;
        if (!empty($product->origin_id) && Schema::connection($conn)->hasTable('origins')) {
            $origName = DB::connection($conn)->table('origins')->where('id', $product->origin_id)->value('name');
        }

        return [
            'found' => true,
            'product' => [
                'id'                => $product->id,
                'name'              => $product->name,
                'barcode'           => $product->barcode,
                'active_ingredient' => $product->active_ingredient,
                'laboratory_id'     => $product->laboratory_id,
                'laboratory_name'   => $labName,
                'category_id'       => $product->category_id,
                'category_name'     => $catName,
                'origin_id'         => $product->origin_id,
                'origin_name'       => $origName,
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

        $conn = $this->getMasterConnection();

        $products = DB::connection($conn)->table('products')
            ->whereIn('barcode', $cleanBarcodes)
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->where('name', '!=', '.')
            ->get();

        if ($products->isEmpty()) {
            return [];
        }

        $labIds = $products->pluck('laboratory_id')->filter()->unique()->toArray();
        $catIds = $products->pluck('category_id')->filter()->unique()->toArray();
        $origIds = $products->pluck('origin_id')->filter()->unique()->toArray();

        $labs = !empty($labIds) && Schema::connection($conn)->hasTable('laboratories')
            ? DB::connection($conn)->table('laboratories')->whereIn('id', $labIds)->pluck('name', 'id')->toArray()
            : [];
        $cats = !empty($catIds) && Schema::connection($conn)->hasTable('categories')
            ? DB::connection($conn)->table('categories')->whereIn('id', $catIds)->pluck('name', 'id')->toArray()
            : [];
        $origs = !empty($origIds) && Schema::connection($conn)->hasTable('origins')
            ? DB::connection($conn)->table('origins')->whereIn('id', $origIds)->pluck('name', 'id')->toArray()
            : [];

        $results = [];
        foreach ($products as $product) {
            $results[$product->barcode] = [
                'id'                => $product->id,
                'name'              => $product->name,
                'barcode'           => $product->barcode,
                'active_ingredient' => $product->active_ingredient,
                'laboratory_id'     => $product->laboratory_id,
                'laboratory_name'   => $labs[$product->laboratory_id] ?? null,
                'category_id'       => $product->category_id,
                'category_name'     => $cats[$product->category_id] ?? null,
                'origin_id'         => $product->origin_id,
                'origin_name'       => $origs[$product->origin_id] ?? null,
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
        $conn = $this->getMasterConnection();
        $barcode = !empty($data['barcode']) ? trim((string) $data['barcode']) : null;

        // 1. Homologar laboratorio
        $laboratoryId = null;
        $labName = !empty($data['laboratory_name']) ? trim($data['laboratory_name']) : null;
        if ($labName) {
            $lab = DB::connection($conn)->table('laboratories')->where('name', $labName)->first();
            if ($lab) {
                $laboratoryId = $lab->id;
            } else {
                $laboratoryId = DB::connection($conn)->table('laboratories')->insertGetId([
                    'name'       => $labName,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } elseif (!empty($data['laboratory_id']) && DB::connection($conn)->table('laboratories')->where('id', $data['laboratory_id'])->exists()) {
            $laboratoryId = (int) $data['laboratory_id'];
            $labName = DB::connection($conn)->table('laboratories')->where('id', $laboratoryId)->value('name');
        }

        // 2. Homologar origen
        $originId = null;
        $origName = !empty($data['origin_name']) ? trim($data['origin_name']) : null;
        if ($origName) {
            $orig = DB::connection($conn)->table('origins')->where('name', $origName)->first();
            if ($orig) {
                $originId = $orig->id;
            } else {
                $originId = DB::connection($conn)->table('origins')->insertGetId([
                    'name'       => $origName,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } elseif (!empty($data['origin_id']) && DB::connection($conn)->table('origins')->where('id', $data['origin_id'])->exists()) {
            $originId = (int) $data['origin_id'];
            $origName = DB::connection($conn)->table('origins')->where('id', $originId)->value('name');
        }

        // 3. Homologar categoría
        $categoryId = null;
        $catName = !empty($data['category_name']) ? trim($data['category_name']) : null;
        if ($catName) {
            $cat = DB::connection($conn)->table('categories')->where('name', $catName)->first();
            if ($cat) {
                $categoryId = $cat->id;
            } else {
                $categoryId = DB::connection($conn)->table('categories')->insertGetId([
                    'name'       => $catName,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } elseif (!empty($data['category_id']) && DB::connection($conn)->table('categories')->where('id', $data['category_id'])->exists()) {
            $categoryId = (int) $data['category_id'];
            $catName = DB::connection($conn)->table('categories')->where('id', $categoryId)->value('name');
        }

        // 4. Si ya existe un producto con este código de barras, enriquecer datos faltantes
        if ($barcode) {
            $existing = DB::connection($conn)->table('products')->where('barcode', $barcode)->first();

            if ($existing) {
                $updates = [];
                if (empty($existing->laboratory_id) && $laboratoryId) {
                    $updates['laboratory_id'] = $laboratoryId;
                }
                if (empty($existing->origin_id) && $originId) {
                    $updates['origin_id'] = $originId;
                }
                if (empty($existing->category_id) && $categoryId) {
                    $updates['category_id'] = $categoryId;
                }
                if (empty($existing->active_ingredient) && !empty($data['active_ingredient'])) {
                    $updates['active_ingredient'] = $data['active_ingredient'];
                }
                if (empty($existing->description) && !empty($data['description'])) {
                    $updates['description'] = $data['description'];
                }
                if (empty($existing->photo_url) && !empty($data['photo_url'])) {
                    $updates['photo_url'] = $data['photo_url'];
                }
                if (empty($existing->presentation) && !empty($data['presentation'])) {
                    $updates['presentation'] = $data['presentation'];
                }
                if (empty($existing->unit_of_measure) && !empty($data['unit_of_measure'])) {
                    $updates['unit_of_measure'] = $data['unit_of_measure'];
                }

                if (!empty($updates)) {
                    $updates['updated_at'] = now();
                    DB::connection($conn)->table('products')->where('id', $existing->id)->update($updates);
                }

                return [
                    'created' => false,
                    'product' => [
                        'id'                => $existing->id,
                        'name'              => $existing->name,
                        'barcode'           => $existing->barcode,
                        'active_ingredient' => $existing->active_ingredient ?? ($updates['active_ingredient'] ?? null),
                        'laboratory_id'     => $updates['laboratory_id'] ?? $existing->laboratory_id,
                        'laboratory_name'   => $labName,
                        'category_id'       => $updates['category_id'] ?? $existing->category_id,
                        'category_name'     => $catName,
                        'origin_id'         => $updates['origin_id'] ?? $existing->origin_id,
                        'origin_name'       => $origName,
                    ],
                ];
            }
        }

        // 5. Crear el nuevo producto en el Master obteniendo el ID oficial único
        $insertData = [
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
            'iva'               => (float) ($data['iva'] ?? 0),
            'is_deleted'        => false,
            'is_active'         => true,
            'created_at'        => now(),
            'updated_at'        => now(),
        ];

        // Filtrar columnas existentes en tabla products del master
        $prodCols = Schema::connection($conn)->getColumnListing('products');
        $insertData = array_intersect_key($insertData, array_flip($prodCols));

        $productId = DB::connection($conn)->table('products')->insertGetId($insertData);

        return [
            'created' => true,
            'product' => [
                'id'                => $productId,
                'name'              => $insertData['name'] ?? '',
                'barcode'           => $barcode,
                'active_ingredient' => $insertData['active_ingredient'] ?? null,
                'laboratory_id'     => $laboratoryId,
                'laboratory_name'   => $labName,
                'category_id'       => $categoryId,
                'category_name'     => $catName,
                'origin_id'         => $originId,
                'origin_name'       => $origName,
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

        $conn = $this->getMasterConnection();
        $existing = DB::connection($conn)->table('laboratories')->where('name', $name)->first();
        if ($existing) {
            return [
                'created' => false,
                'laboratory' => [
                    'id'   => $existing->id,
                    'name' => $existing->name,
                ],
            ];
        }

        $id = DB::connection($conn)->table('laboratories')->insertGetId([
            'name'       => $name,
            'group_id'   => $data['group_id'] ?? null,
            'parent_id'  => $data['parent_id'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'created' => true,
            'laboratory' => [
                'id'   => $id,
                'name' => $name,
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

        $conn = $this->getMasterConnection();
        $existing = DB::connection($conn)->table('groups_products')->where('name', $name)->first();
        if ($existing) {
            return [
                'created' => false,
                'group' => [
                    'id'   => $existing->id,
                    'name' => $existing->name,
                ],
            ];
        }

        $id = DB::connection($conn)->table('groups_products')->insertGetId([
            'name'       => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'created' => true,
            'group' => [
                'id'   => $id,
                'name' => $name,
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
        $conn = $this->getMasterConnection();

        $query = DB::connection($conn)->table('suppliers');
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

        $supCols = Schema::connection($conn)->getColumnListing('suppliers');
        $insertData = [
            'name'          => $name,
            'social_reason' => $data['social_reason'] ?? $name,
            'rif'           => $rif,
            'created_at'    => now(),
            'updated_at'    => now(),
        ];
        $insertData = array_intersect_key($insertData, array_flip($supCols));

        $id = DB::connection($conn)->table('suppliers')->insertGetId($insertData);

        return [
            'created' => true,
            'supplier' => [
                'id'            => $id,
                'name'          => $name,
                'rif'           => $rif,
                'social_reason' => $insertData['social_reason'] ?? $name,
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

        $conn = $this->getMasterConnection();
        $existing = DB::connection($conn)->table('origins')->where('name', $name)->first();
        if ($existing) {
            return [
                'created' => false,
                'origin'  => [
                    'id'   => $existing->id,
                    'name' => $existing->name,
                ],
            ];
        }

        $id = DB::connection($conn)->table('origins')->insertGetId([
            'name'       => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'created' => true,
            'origin'  => [
                'id'   => $id,
                'name' => $name,
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

        $conn = $this->getMasterConnection();
        $existing = DB::connection($conn)->table('categories')->where('name', $name)->first();
        if ($existing) {
            return [
                'created' => false,
                'category' => [
                    'id'   => $existing->id,
                    'name' => $existing->name,
                ],
            ];
        }

        $id = DB::connection($conn)->table('categories')->insertGetId([
            'name'       => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'created' => true,
            'category' => [
                'id'   => $id,
                'name' => $name,
            ],
        ];
    }

    /**
     * Exporta en bloque laboratorios, orígenes, grupos, categorías y proveedores del Master.
     */
    public function exportEntities(array $entities): array
    {
        $result = [];
        $conn = $this->getMasterConnection();

        if (in_array('groups_laboratories', $entities) || in_array('laboratories', $entities)) {
            if (Schema::connection($conn)->hasTable('groups_laboratories')) {
                $result['groups_laboratories'] = DB::connection($conn)->table('groups_laboratories')->orderBy('id')->get()->toArray();
            }
        }

        if (in_array('laboratories', $entities) && Schema::connection($conn)->hasTable('laboratories')) {
            $result['laboratories'] = DB::connection($conn)->table('laboratories')->orderBy('id')->get()->toArray();
        }

        if (in_array('origins', $entities) && Schema::connection($conn)->hasTable('origins')) {
            $result['origins'] = DB::connection($conn)->table('origins')->orderBy('id')->get()->toArray();
        }

        if ((in_array('groups', $entities) || in_array('groups_products', $entities)) && Schema::connection($conn)->hasTable('groups_products')) {
            $result['groups'] = DB::connection($conn)->table('groups_products')->orderBy('id')->get()->toArray();
        }

        if (in_array('categories', $entities) && Schema::connection($conn)->hasTable('categories')) {
            $result['categories'] = DB::connection($conn)->table('categories')->orderBy('id')->get()->toArray();
        }

        if (in_array('suppliers', $entities) && Schema::connection($conn)->hasTable('suppliers')) {
            $result['suppliers'] = DB::connection($conn)->table('suppliers')->orderBy('id')->get()->toArray();
        }

        return $result;
    }
}
