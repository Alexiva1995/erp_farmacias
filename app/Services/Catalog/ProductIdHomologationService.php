<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductIdHomologationService
{
    public const TEMP_OFFSET = 100000000;

    /**
     * Lista base de relaciones conocidas hacia products.id (respaldo si information_schema está restringido).
     *
     * @var array<int, array{table: string, column: string}>
     */
    private const BASE_RELATIONS = [
        ['table' => 'product_lots', 'column' => 'product_id'],
        ['table' => 'order_details', 'column' => 'product_id'],
        ['table' => 'invoice_details', 'column' => 'product_id'],
        ['table' => 'inventory_movements', 'column' => 'product_id'],
        ['table' => 'auto_order_details', 'column' => 'product_id'],
        ['table' => 'inventory_book_entries', 'column' => 'product_id'],
        ['table' => 'quotation_products', 'column' => 'product_id'],
        ['table' => 'product_suppliers', 'column' => 'product_id'],
        ['table' => 'product_profitability', 'column' => 'product_id'],
        ['table' => 'product_variants', 'column' => 'product_id'],
        ['table' => 'product_pack_items', 'column' => 'product_id'],
        ['table' => 'product_counts', 'column' => 'product_id'],
        ['table' => 'product_failures', 'column' => 'product_id'],
        ['table' => 'product_stockouts', 'column' => 'product_id'],
        ['table' => 'returns', 'column' => 'product_id'],
        ['table' => 'invoice_returns', 'column' => 'product_id'],
        ['table' => 'dish_ingredients', 'column' => 'product_id'],
        ['table' => 'dishes', 'column' => 'output_product_id'],
        ['table' => 'production_orders', 'column' => 'output_product_id'],
        ['table' => 'kitchen_wastes', 'column' => 'product_id'],
        ['table' => 'modifier_options', 'column' => 'product_id'],
        ['table' => 'modifier_groups', 'column' => 'base_product_id'],
        ['table' => 'order_detail_modifiers', 'column' => 'product_id'],
        ['table' => 'order_detail_modifiers', 'column' => 'base_product_id'],
        ['table' => 'groups_products', 'column' => 'product_id'],
        ['table' => 'groups_products', 'column' => 'related_product_id'],
        ['table' => 'ecommerce_order_items', 'column' => 'product_id'],
        ['table' => 'employee_product', 'column' => 'product_id'],
        ['table' => 'expirations', 'column' => 'product_id'],
        ['table' => 'expired_logs', 'column' => 'product_id'],
        ['table' => 'fiscal_history_details', 'column' => 'product_id'],
        ['table' => 'individual_offers', 'column' => 'product_id'],
        ['table' => 'invoices_counts', 'column' => 'product_id'],
        ['table' => 'price_adjustment_logs', 'column' => 'product_id'],
        ['table' => 'psychotropic_controls', 'column' => 'product_id'],
        ['table' => 'sales_counts', 'column' => 'product_id'],
        ['table' => 'supplier_ai_match_rejections', 'column' => 'product_id'],
        ['table' => 'chronic_patient_contacts', 'column' => 'product_id'],
    ];

    /**
     * Descubre dinámicamente todas las tablas y columnas en la conexión que referencian products.id.
     *
     * @return array<int, array{table: string, column: string}>
     */
    public function discoverProductForeignRelations(string $conn): array
    {
        $relations = [];
        $seen = [];

        $addRelation = function (string $table, string $column) use (&$relations, &$seen): void {
            if ($table === 'products') {
                return;
            }
            $key = "{$table}.{$column}";
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $relations[] = ['table' => $table, 'column' => $column];
            }
        };

        $dbName = DB::connection($conn)->getDatabaseName();

        try {
            // 1. Todas las llaves foráneas reales que apuntan a products.id
            $fkRows = DB::connection($conn)->select("
                SELECT TABLE_NAME as table_name, COLUMN_NAME as column_name
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = ?
                  AND REFERENCED_TABLE_NAME = 'products'
                  AND REFERENCED_COLUMN_NAME = 'id'
            ", [$dbName]);

            foreach ($fkRows as $row) {
                $addRelation((string) $row->table_name, (string) $row->column_name);
            }

            // 2. Columnas convencionales que pueden no tener FK física declarada
            $colRows = DB::connection($conn)->select("
                SELECT TABLE_NAME as table_name, COLUMN_NAME as column_name
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = ?
                  AND COLUMN_NAME IN ('product_id', 'related_product_id', 'output_product_id', 'base_product_id')
            ", [$dbName]);

            foreach ($colRows as $row) {
                $addRelation((string) $row->table_name, (string) $row->column_name);
            }
        } catch (\Throwable) {
            // Continuar con relaciones base si information_schema no está disponible
        }

        // 3. Respaldo con relaciones conocidas verificando existencia física
        foreach (self::BASE_RELATIONS as $rel) {
            if (Schema::connection($conn)->hasTable($rel['table']) && Schema::connection($conn)->hasColumn($rel['table'], $rel['column'])) {
                $addRelation($rel['table'], $rel['column']);
            }
        }

        return $relations;
    }

    /**
     * Obtiene el próximo ID libre seguro por encima de los IDs actuales locales y del Master.
     */
    public function findNextSafeFreeId(string $conn): int
    {
        $localMax = (int) (DB::connection($conn)->table('products')->max('id') ?? 0);
        $masterMax = 0;

        try {
            if (Schema::connection('master_catalog')->hasTable('products')) {
                $masterMax = (int) (DB::connection('master_catalog')->table('products')->max('id') ?? 0);
            }
        } catch (\Throwable) {
            // Ignorar si master_catalog no está disponible
        }

        return max($localMax, $masterMax) + 1;
    }

    /**
     * Reubica un producto individual de $oldId hacia $newId actualizando en cascada toda su trazabilidad.
     * Si $newId ya existe en el tenant, fusiona las relaciones sin perder historial y elimina $oldId.
     */
    public function relocateProductId(string $conn, int $oldId, int $newId): bool
    {
        if ($oldId <= 0 || $newId <= 0 || $oldId === $newId) {
            return false;
        }

        if (!DB::connection($conn)->table('products')->where('id', $oldId)->exists()) {
            return false;
        }

        $relations = $this->discoverProductForeignRelations($conn);
        $targetExists = DB::connection($conn)->table('products')->where('id', $newId)->exists();

        DB::connection($conn)->statement('SET FOREIGN_KEY_CHECKS = 0');

        try {
            foreach ($relations as $rel) {
                $table = $rel['table'];
                $column = $rel['column'];

                DB::connection($conn)->update(
                    "UPDATE IGNORE `{$table}` SET `{$column}` = ? WHERE `{$column}` = ?",
                    [$newId, $oldId]
                );

                // Si quedaron filas sin mover por restricción UNIQUE (ej. product_suppliers o product_profitability), limpiar remanente
                DB::connection($conn)->table($table)->where($column, $oldId)->delete();
            }

            if ($targetExists) {
                DB::connection($conn)->table('products')->where('id', $oldId)->delete();
            } else {
                DB::connection($conn)->table('products')->where('id', $oldId)->update(['id' => $newId]);
            }
        } finally {
            DB::connection($conn)->statement('SET FOREIGN_KEY_CHECKS = 1');
        }

        return true;
    }

    /**
     * Aplica un remapeo masivo de products.id libre de colisiones destructivas y actualizando todas las relaciones.
     *
     * @param array<int, int> $map [old_id => master_id]
     * @return array{remapped: int, displaced: int, merged_duplicates: int}
     */
    public function applyBulkProductMapping(string $conn, array $map): array
    {
        if (empty($map)) {
            return ['remapped' => 0, 'displaced' => 0, 'merged_duplicates' => 0];
        }

        $relations = $this->discoverProductForeignRelations($conn);
        $displaced = 0;

        // 1. Detectar IDs destino ocupados por productos NO incluidos en $map y moverlos primero a IDs seguros
        $targetIds = array_values(array_unique(array_map('intval', $map)));
        $occupants = [];

        foreach (array_chunk($targetIds, 1000) as $chunk) {
            $found = DB::connection($conn)->table('products')
                ->whereIn('id', $chunk)
                ->pluck('id')
                ->toArray();

            foreach ($found as $fid) {
                $fidInt = (int) $fid;
                if (!isset($map[$fidInt])) {
                    $occupants[] = $fidInt;
                }
            }
        }

        if (!empty($occupants)) {
            $nextFreeId = $this->findNextSafeFreeId($conn);
            foreach ($occupants as $occupantId) {
                while (DB::connection($conn)->table('products')->where('id', $nextFreeId)->exists()) {
                    $nextFreeId++;
                }
                if ($this->relocateProductId($conn, $occupantId, $nextFreeId)) {
                    $displaced++;
                    $nextFreeId++;
                }
            }
        }

        // 2. Ejecutar remapeo en 2 fases mediante tabla temporal
        $offset = self::TEMP_OFFSET;

        DB::connection($conn)->statement('DROP TEMPORARY TABLE IF EXISTS temp_product_id_map');
        DB::connection($conn)->statement('
            CREATE TEMPORARY TABLE temp_product_id_map (
                old_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
                new_id BIGINT UNSIGNED NOT NULL
            ) ENGINE=InnoDB
        ');

        $mergedDuplicates = 0;

        try {
            $insertData = [];
            foreach ($map as $oldId => $newId) {
                $insertData[] = ['old_id' => (int) $oldId, 'new_id' => (int) $newId];
                if (count($insertData) >= 500) {
                    DB::connection($conn)->table('temp_product_id_map')->insert($insertData);
                    $insertData = [];
                }
            }
            if (!empty($insertData)) {
                DB::connection($conn)->table('temp_product_id_map')->insert($insertData);
            }

            // Fase A: Tablas relacionales -> new_id + offset
            foreach ($relations as $rel) {
                $table = $rel['table'];
                $col = $rel['column'];

                DB::connection($conn)->update("
                    UPDATE IGNORE `{$table}` t
                    INNER JOIN temp_product_id_map m ON t.`{$col}` = m.old_id
                    SET t.`{$col}` = m.new_id + {$offset}
                ");

                // Limpiar solo remanentes que no pudieron moverse por restricción UNIQUE antes de bajar del offset
                DB::connection($conn)->delete("
                    DELETE t FROM `{$table}` t
                    INNER JOIN temp_product_id_map m ON t.`{$col}` = m.old_id
                ");
            }

            // Fase B: Tablas relacionales -> definitivo (new_id)
            foreach ($relations as $rel) {
                $table = $rel['table'];
                $col = $rel['column'];

                DB::connection($conn)->update("
                    UPDATE IGNORE `{$table}` t
                    SET t.`{$col}` = t.`{$col}` - {$offset}
                    WHERE t.`{$col}` >= {$offset}
                ");
            }

            // 3. Resolver duplicados locales en products (cuando múltiples old_id apuntan al mismo new_id o new_id ya existía)
            $existingTargetIds = [];
            foreach (array_chunk($targetIds, 1000) as $chunk) {
                $found = DB::connection($conn)->table('products')
                    ->whereIn('id', $chunk)
                    ->pluck('id')
                    ->toArray();
                foreach ($found as $id) {
                    $existingTargetIds[(int) $id] = true;
                }
            }

            $redundantOldIds = [];
            $claimedNewIds = [];

            foreach ($map as $oldId => $newId) {
                $oldIdInt = (int) $oldId;
                $newIdInt = (int) $newId;

                $targetAlreadyExistsUnmoved = isset($existingTargetIds[$newIdInt]) && !isset($map[$newIdInt]);
                $alreadyClaimed = isset($claimedNewIds[$newIdInt]);

                if ($targetAlreadyExistsUnmoved || $alreadyClaimed) {
                    $redundantOldIds[] = $oldIdInt;
                } else {
                    $claimedNewIds[$newIdInt] = $oldIdInt;
                }
            }

            if (!empty($redundantOldIds)) {
                $mergedDuplicates = count($redundantOldIds);
                foreach (array_chunk($redundantOldIds, 500) as $chunk) {
                    DB::connection($conn)->table('products')->whereIn('id', $chunk)->delete();
                    DB::connection($conn)->table('temp_product_id_map')->whereIn('old_id', $chunk)->delete();
                }
            }

            // Fase C: Tabla products -> 2 fases libre de colisiones
            DB::connection($conn)->update("
                UPDATE `products` p
                INNER JOIN temp_product_id_map m ON p.`id` = m.old_id
                SET p.`id` = m.new_id + {$offset}
            ");

            DB::connection($conn)->update("
                UPDATE `products` p
                SET p.`id` = p.`id` - {$offset}
                WHERE p.`id` >= {$offset}
            ");
        } finally {
            DB::connection($conn)->statement('DROP TEMPORARY TABLE IF EXISTS temp_product_id_map');
        }

        return [
            'remapped' => count($map) - $mergedDuplicates,
            'displaced' => $displaced,
            'merged_duplicates' => $mergedDuplicates,
        ];
    }
}
