<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\CategoryTypeEnum;
use App\Http\Resources\Inventory\CategoryManagementResource;
use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class CategoryManagementService
{
    /**
     * Listar categorías paginadas con conteo de productos y platos, aplicando filtros por búsqueda y tipo.
     *
     * @param array<string, mixed> $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $hasTypeCol = Category::ensureTypeColumn();
        $hasPhotoCol = (new Category())->getConnection()->getSchemaBuilder()->hasColumn('categories', 'photo_url');
        $enabledTypes = config('branding.enabled_product_types', ['ingredients']);
        $hasIngredients = in_array('ingredients', (array) $enabledTypes, true);

        $selectColumns = ['id', 'name'];
        if ($hasTypeCol) {
            $selectColumns[] = 'type';
        }
        if ($hasPhotoCol) {
            $selectColumns[] = 'photo_url';
        }

        $query = Category::query()
            ->select($selectColumns)
            ->withCount(['products']);

        if ($hasIngredients) {
            $query->withCount(['dishes']);
        }

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where('name', 'like', "%{$search}%");
        }

        if ($hasTypeCol && !empty($filters['type']) && $filters['type'] !== 'all') {
            $filterType = (string) $filters['type'];
            if (in_array($filterType, ['inventory', 'products'], true)) {
                $query->forInventory();
            } elseif (in_array($filterType, ['dish', 'dishes'], true)) {
                $query->forDishes();
            } elseif ($filterType === CategoryTypeEnum::BOTH->value) {
                $query->where('type', CategoryTypeEnum::BOTH->value);
            }
        }

        $sortBy = (string) ($filters['sortBy'] ?? 'name');
        $orderBy = strtolower((string) ($filters['orderBy'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $itemsPerPage = (int) ($filters['itemsPerPage'] ?? 10);

        $allowedSort = ['id', 'name', 'type', 'products_count', 'dishes_count'];
        if (!in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'name';
        }

        if ($itemsPerPage === -1) {
            $itemsPerPage = Category::count() ?: 10;
        }

        $paginator = $query->orderBy($sortBy, $orderBy)->paginate($itemsPerPage);

        return $paginator->through(
            fn (Category $category) => (new CategoryManagementResource($category))->resolve()
        );
    }

    /**
     * Crear o actualizar una categoría con su tipo (inventory, dish o both).
     *
     * @param array<string, mixed> $data
     */
    public function save(array $data): Category
    {
        $hasTypeCol = Category::ensureTypeColumn();

        $attributes = [
            'name' => trim((string) $data['name']),
        ];

        if ($hasTypeCol) {
            if (!empty($data['type']) && in_array($data['type'], CategoryTypeEnum::values(), true)) {
                $attributes['type'] = $data['type'];
            } elseif (empty($data['id'])) {
                $attributes['type'] = CategoryTypeEnum::INVENTORY->value;
            }
        }

        $hasPhotoCol = (new Category())->getConnection()->getSchemaBuilder()->hasColumn('categories', 'photo_url');
        $existingCategory = !empty($data['id']) ? Category::find($data['id']) : null;

        if ($hasPhotoCol) {
            if (!empty($data['remove_photo']) && $existingCategory) {
                $rawOld = $existingCategory->getRawOriginal('photo_url');
                if ($rawOld && Storage::disk('public')->exists($rawOld)) {
                    Storage::disk('public')->delete($rawOld);
                }
                $attributes['photo_url'] = null;
            }

            if (isset($data['photo']) && $data['photo'] instanceof UploadedFile) {
                if ($existingCategory) {
                    $rawOld = $existingCategory->getRawOriginal('photo_url');
                    if ($rawOld && Storage::disk('public')->exists($rawOld)) {
                        Storage::disk('public')->delete($rawOld);
                    }
                }
                $attributes['photo_url'] = $data['photo']->store('categories', 'public');
            }
        }

        $category = Category::updateOrCreate(
            ['id' => $data['id'] ?? null],
            $attributes
        );

        Category::clearCategoriesCache();

        return $category;
    }

    /**
     * Eliminar una categoría y desvincular sus productos y platos asociados.
     */
    public function delete(Category $category): void
    {
        $category->products()->update(['category_id' => null]);
        $category->dishes()->update(['category_id' => null]);
        $category->delete();

        Category::clearCategoriesCache();
    }

    /**
     * Ejecutar categorización de productos de inventario mediante IA.
     *
     * @return array{exit_code: int, output: string}
     */
    public function runAiCategorization(): array
    {
        $exitCode = Artisan::call('products:ai-categorize', [
            '--limit' => 60,
            '--batch' => 30,
        ]);

        $output = trim(Artisan::output());
        Category::clearCategoriesCache();

        return [
            'exit_code' => $exitCode,
            'output' => $output,
        ];
    }
}
