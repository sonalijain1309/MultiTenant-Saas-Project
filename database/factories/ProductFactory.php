<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $price = fake()->randomFloat(2, 10, 500);
        return [
            'tenant_id' => Tenant::factory(),
            'sku' => 'SKU-' . strtoupper(Str::random(8)),
            'name' => fake()->words(3, true),
            'description' => fake()->paragraph(),
            'price' => $price,
            'cost_price' => round($price * 0.6, 2),
            'stock' => fake()->numberBetween(5, 200),
            'is_available' => true,
        ];
    }
}
