<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $unitPrice = fake()->randomFloat(2, 10, 100);
        $qty = fake()->numberBetween(1, 4);

        return [
            'tenant_id' => Tenant::factory(),
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'product_name' => fake()->words(3, true),
            'unit_price' => $unitPrice,
            'quantity' => $qty,
            'subtotal' => round($unitPrice * $qty, 2),
        ];
    }
}
