<?php
# CodeContent: Database\Factories\ProductFactory.php
# Author: Antigravity

namespace Database\Factories;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->sentence(2);
        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'active_ingredient' => $this->faker->word(),
            'unit_cost' => $this->faker->numberBetween(1000, 20000),
            'sale_price' => $this->faker->numberBetween(5000, 50000),
            'iva' => false,
            'is_colombian_origin' => true,
            'psychotropic' => false,
            'barcode' => $this->faker->unique()->ean13(),
            'photo_url' => $this->faker->imageUrl(),
        ];
    }
}
