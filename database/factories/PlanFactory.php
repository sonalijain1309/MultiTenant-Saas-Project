<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Starter', 'Growth', 'Enterprise', 'Scale', 'Business']);
        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'price' => fake()->randomElement([0.00, 19.00, 49.00, 99.00]),
            'billing_interval' => 'month',
            'max_users' => 5,
            'max_customers' => 50,
            'max_products' => 30,
            'max_orders_per_month' => 100,
            'features' => ['api_access', 'export_reports', 'email_support'],
            'is_active' => true,
        ];
    }
}
