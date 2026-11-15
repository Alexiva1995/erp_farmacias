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
    
    /**
     * Normaliza un RIF para comparación alfanumérica sin separadores.
     */
    public function cleanRifKey(?string $rif): string
    {
        if (empty($rif)) {
            return '';
        }

        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', trim($rif)));
    }

    /**
     * Buscar un proveedor en el catálogo maestro por RIF.
     */
    public function findSupplierByRif(string $rif): ?array
    {
        $rif = trim($rif);
        if (empty($rif)) {
            return null;
        }

        $conn = $this->getMasterConnection();
        if (!Schema::connection($conn)->hasTable('suppliers')) {
            return null;
        }

        $cleanRif = $this->cleanRifKey($rif);
        $supplier = DB::connection($conn)->table('suppliers')
            ->where(function ($q) use ($rif, $cleanRif) {
                $q->where('rif', $rif);
                if ($cleanRif !== '') {
                    $q->orWhereRaw("UPPER(REPLACE(REPLACE(REPLACE(rif, '-', ''), ' ', ''), '.', '')) = ?", [$cleanRif]);
                }
            })
            ->first();

        if (!$supplier) {
            return null;
        }

        $result = (array) $supplier;
        if (isset($result['business_types']) && is_string($result['business_types'])) {
            $result['business_types'] = json_decode($result['business_types'], true) ?: [];
        }

        return $result;
    }

    /**
     * Registrar, buscar o enriquecer un proveedor en el Catálogo Maestro.
     */
    public function registerMasterSupplier(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $rif = !empty($data['rif']) ? strtoupper(trim((string) $data['rif'])) : null;
        $cleanRif = $this->cleanRifKey($rif);
        $conn = $this->getMasterConnection();

        if (!Schema::connection($conn)->hasTable('suppliers')) {
            return ['created' => false, 'supplier' => null];
        }

        $query = DB::connection($conn)->table('suppliers');
        if ($cleanRif !== '') {
            $query->where(function ($q) use ($rif, $cleanRif) {
                $q->where('rif', $rif)
                    ->orWhereRaw("UPPER(REPLACE(REPLACE(REPLACE(rif, '-', ''), ' ', ''), '.', '')) = ?", [$cleanRif]);
            });
        } elseif ($name !== '') {
            $query->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)]);
        } else {
            return ['created' => false, 'supplier' => null];
        }

        $supCols = array_flip(Schema::connection($conn)->getColumnListing('suppliers'));
        $existing = $query->first();

        if ($existing) {
            // Enriquecer únicamente campos vacíos en el Master sin sobrescribir datos oficiales existentes
            $enrichData = [];
            $enrichableStringFields = [
                'logo_url',
                'social_reason',
                'address',
                'sales_phone',
                'collections_phone',
                'payment_email',
                'type',
            ];

            foreach ($enrichableStringFields as $field) {
                if (isset($supCols[$field]) && empty($existing->$field) && !empty($data[$field])) {
                    $enrichData[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
                }
            }

            if (isset($supCols['business_types']) && !empty($data['business_types']) && is_array($data['business_types'])) {
                $existingTypes = is_string($existing->business_types ?? null)
                    ? (json_decode($existing->business_types, true) ?: [])
                    : (is_array($existing->business_types ?? null) ? $existing->business_types : []);
                $mergedTypes = array_values(array_unique(array_merge($existingTypes, $data['business_types'])));
                if (count($mergedTypes) > count($existingTypes)) {
                    $enrichData['business_types'] = json_encode($mergedTypes);
                }
            }

            if (!empty($enrichData)) {
                if (isset($supCols['updated_at'])) {
                    $enrichData['updated_at'] = now();
                }
                DB::connection($conn)->table('suppliers')
                    ->where('id', $existing->id)
                    ->update($enrichData);
                $existing = DB::connection($conn)->table('suppliers')->where('id', $existing->id)->first();
            }

            return [
                'created' => false,
                'supplier' => $this->formatMasterSupplierRow($existing),
            ];
        }

        $typeVal = $data['type'] ?? 'drogueria';
        if ($typeVal instanceof \App\Enums\SupplierType) {
            $typeVal = $typeVal->value;
        }

        $businessTypes = $data['business_types'] ?? [];
        if (is_array($businessTypes)) {
            $businessTypesJson = json_encode(array_values(array_unique($businessTypes)));
        } else {
            $businessTypesJson = is_string($businessTypes) ? $businessTypes : json_encode([]);
        }

        $insertData = [
            'name'              => $name,
            'logo_url'          => !empty($data['logo_url']) ? trim((string) $data['logo_url']) : null,
            'social_reason'     => !empty($data['social_reason']) ? trim((string) $data['social_reason']) : $name,
            'rif'               => $rif,
            'address'           => !empty($data['address']) ? trim((string) $data['address']) : null,
            'sales_phone'       => !empty($data['sales_phone']) ? trim((string) $data['sales_phone']) : null,
            'collections_phone' => !empty($data['collections_phone']) ? trim((string) $data['collections_phone']) : null,
            'payment_email'     => !empty($data['payment_email']) ? trim((string) $data['payment_email']) : null,
            'type'              => $typeVal,
            'business_types'    => $businessTypesJson,
            'is_indexed'        => (bool) ($data['is_indexed'] ?? false),
            'is_fiscal'         => isset($data['is_fiscal']) ? (bool) $data['is_fiscal'] : true,
            'is_active'         => 1,
            'dispatch_days'     => json_encode([]),
            'order_days'        => json_encode([]),
            'created_at'        => now(),
            'updated_at'        => now(),
        ];
        $insertData = array_intersect_key($insertData, $supCols);

        $id = DB::connection($conn)->table('suppliers')->insertGetId($insertData);
        $createdRow = DB::connection($conn)->table('suppliers')->where('id', $id)->first();

        return [
            'created' => true,
            'supplier' => $this->formatMasterSupplierRow($createdRow),
        ];
    }

    /**
     * Promueve la estructura técnica de conexión de un proveedor hacia tovaerp_master
     * sin copiar credenciales locales y sin sobrescribir plantillas oficiales ya existentes.
     *
     * @param int $masterSupplierId
     * @param array<string, mixed> $connectionData
     * @param string|null $supplierName
     */
    public function promoteConnectionStructureToMaster(
        int $masterSupplierId,
        array $connectionData,
        ?string $supplierName = null
    ): void {
        if ($masterSupplierId <= 0) {
            return;
        }

        $conn = $this->getMasterConnection();
        if (!Schema::connection($conn)->hasTable('suppliers') || !Schema::connection($conn)->hasTable('supplier_connections')) {
            return;
        }

        $masterSup = DB::connection($conn)->table('suppliers')->where('id', $masterSupplierId)->first();
        if (!$masterSup) {
            return;
        }

        $type = $connectionData['type'] ?? 'file';
        if (str_contains((string) $type, 'bot')) {
            return;
        }
        $masterConnCols = array_flip(Schema::connection($conn)->getColumnListing('supplier_connections'));

        // Sanitizar estructura principal removiendo tokens de sesión privados
        $sanitizedStructure = $this->sanitizeStructurePayload($connectionData['structure'] ?? null);
        $sanitizedSecondary = $this->sanitizeStructurePayload($connectionData['secondary_structure'] ?? null);
        $sanitizedTertiary = $this->sanitizeStructurePayload($connectionData['tertiary_structure'] ?? null);
        $sanitizedInvoice = $this->sanitizeStructurePayload($connectionData['invoice_structure'] ?? null);

        if (
            empty($sanitizedStructure)
            && empty($sanitizedSecondary)
            && empty($sanitizedTertiary)
            && empty($sanitizedInvoice)
        ) {
            return;
        }

        $existingMasterConn = DB::connection($conn)->table('supplier_connections')
            ->where('supplier_id', $masterSupplierId)
            ->where('type', $type)
            ->first();

        $publicHost = in_array($type, ['file', 'email'], true)
            ? (($supplierName ?: $masterSup->name) . ' (Excel)')
            : ($connectionData['host'] ?? null);

        if ($existingMasterConn) {
            // Solo completar estructuras vacías en el Master para proteger plantillas oficiales
            $updatePayload = [];
            if (empty($existingMasterConn->structure) && !empty($sanitizedStructure) && isset($masterConnCols['structure'])) {
                $updatePayload['structure'] = json_encode($sanitizedStructure);
            }
            if (empty($existingMasterConn->secondary_structure) && !empty($sanitizedSecondary) && isset($masterConnCols['secondary_structure'])) {
                $updatePayload['secondary_structure'] = json_encode($sanitizedSecondary);
            }
            if (empty($existingMasterConn->tertiary_structure) && !empty($sanitizedTertiary) && isset($masterConnCols['tertiary_structure'])) {
                $updatePayload['tertiary_structure'] = json_encode($sanitizedTertiary);
            }
            if (empty($existingMasterConn->invoice_structure) && !empty($sanitizedInvoice) && isset($masterConnCols['invoice_structure'])) {
                $updatePayload['invoice_structure'] = json_encode($sanitizedInvoice);
            }
            if (empty($existingMasterConn->credit_days_cod_col) && !empty($connectionData['credit_days_cod_col']) && isset($masterConnCols['credit_days_cod_col'])) {
                $updatePayload['credit_days_cod_col'] = $connectionData['credit_days_cod_col'];
            }
            if (empty($existingMasterConn->credit_days_col) && !empty($connectionData['credit_days_col']) && isset($masterConnCols['credit_days_col'])) {
                $updatePayload['credit_days_col'] = $connectionData['credit_days_col'];
            }

            if (!empty($updatePayload)) {
                if (isset($masterConnCols['updated_at'])) {
                    $updatePayload['updated_at'] = now();
                }
                DB::connection($conn)->table('supplier_connections')
                    ->where('id', $existingMasterConn->id)
                    ->update($updatePayload);
            }

            return;
        }

        $insertConn = [
            'supplier_id' => $masterSupplierId,
            'type' => $type,
            'host' => $publicHost,
            'port' => $connectionData['port'] ?? null,
            'username' => null,
            'password' => null,
            'path' => null,
            'invoice_path' => null,
            'pasv' => (bool) ($connectionData['pasv'] ?? false),
            'has_header' => (bool) ($connectionData['has_header'] ?? false),
            'structure' => !empty($sanitizedStructure) ? json_encode($sanitizedStructure) : null,
            'secondary_structure' => !empty($sanitizedSecondary) ? json_encode($sanitizedSecondary) : null,
            'tertiary_structure' => !empty($sanitizedTertiary) ? json_encode($sanitizedTertiary) : null,
            'invoice_structure' => !empty($sanitizedInvoice) ? json_encode($sanitizedInvoice) : null,
            'credit_days_cod_col' => $connectionData['credit_days_cod_col'] ?? null,
            'credit_days_col' => $connectionData['credit_days_col'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $insertConn = array_intersect_key($insertConn, $masterConnCols);
        DB::connection($conn)->table('supplier_connections')->insert($insertConn);
    }

    /**
     * Limpia credenciales o tokens de sesión de un payload de estructura de conexión.
     *
     * @return array<mixed>|null
     */
    protected function sanitizeStructurePayload(mixed $raw): ?array
    {
        if (empty($raw)) {
            return null;
        }

        $data = is_string($raw) ? json_decode($raw, true) : (array) $raw;
        if (!is_array($data) || empty($data)) {
            return null;
        }

        unset($data['session_token'], $data['last_token_source'], $data['last_token_sync_at']);

        return empty($data) ? null : $data;
    }

    /**
     * Formatea una fila de proveedor maestro a un arreglo homogéneo.
     *
     * @return array<string, mixed>
     */
    protected function formatMasterSupplierRow(object $row): array
    {
        $businessTypes = [];
        if (!empty($row->business_types)) {
            $businessTypes = is_string($row->business_types)
                ? (json_decode($row->business_types, true) ?: [])
                : (is_array($row->business_types) ? $row->business_types : []);
        }

        return [
            'id'                => (int) $row->id,
            'name'              => $row->name,
            'logo_url'          => $row->logo_url ?? null,
            'rif'               => $row->rif,
            'social_reason'     => $row->social_reason,
            'address'           => $row->address ?? null,
            'sales_phone'       => $row->sales_phone ?? null,
            'collections_phone' => $row->collections_phone ?? null,
            'payment_email'     => $row->payment_email ?? null,
            'credit_days'       => isset($row->credit_days) ? (int) $row->credit_days : 0,
            'is_indexed'        => (bool) ($row->is_indexed ?? false),
            'is_fiscal'         => isset($row->is_fiscal) ? (bool) $row->is_fiscal : true,
            'type'              => $row->type ?? 'drogueria',
            'business_types'    => $businessTypes,
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
