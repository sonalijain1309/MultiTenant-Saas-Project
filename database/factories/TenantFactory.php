<?php

namespace Database\Factories;

use App\Enums\TenantStatus;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();
        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(5),
            'domain' => null,
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'status' => TenantStatus::ACTIVE,
            'plan_id' => Plan::factory(),
            'trial_ends_at' => null,
            'settings' => ['timezone' => 'UTC', 'currency' => 'USD'],
        ];
    }
}
