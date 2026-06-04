<?php

namespace App\Contracts;

use App\Models\Dish as ModelDish;
use Illuminate\Pagination\LengthAwarePaginator;

interface Dish
{
    public function getAll(array $filters): LengthAwarePaginator;
    public function find(int $id): ?ModelDish;
    public function create(array $data): ModelDish;
    public function update(ModelDish $dish, array $data): ModelDish;
    public function delete(ModelDish $dish): bool;
}
