<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'customer_id' => Customer::factory(),
            'order_number' => 'ORD-' . strtoupper(fake()->bothify('##??-####')),
            'status' => OrderStatus::COMPLETED,
            'subtotal' => 100.00,
            'tax_amount' => 10.00,
            'discount_amount' => 0.00,
            'total_amount' => 110.00,
            'notes' => fake()->sentence(),
            'ordered_at' => now(),
        ];
    }
}
