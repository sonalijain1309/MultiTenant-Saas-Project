<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'Great for testing and micro teams getting started.',
                'price' => 0.00,
                'billing_interval' => 'month',
                'max_users' => 1,
                'max_customers' => 10,
                'max_products' => 5,
                'max_orders_per_month' => 20,
                'features' => [
                    'basic_analytics' => true,
                    'email_support' => false,
                    'api_access' => false,
                    'custom_domain' => false,
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'description' => 'Ideal for growing businesses that need solid capacity.',
                'price' => 29.00,
                'billing_interval' => 'month',
                'max_users' => 5,
                'max_customers' => 100,
                'max_products' => 50,
                'max_orders_per_month' => 250,
                'features' => [
                    'basic_analytics' => true,
                    'email_support' => true,
                    'api_access' => true,
                    'custom_domain' => false,
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'description' => 'For demanding operations with high volume and multiple teams.',
                'price' => 79.00,
                'billing_interval' => 'month',
                'max_users' => 25,
                'max_customers' => 1000,
                'max_products' => 500,
                'max_orders_per_month' => 2500,
                'features' => [
                    'basic_analytics' => true,
                    'advanced_analytics' => true,
                    'priority_support' => true,
                    'api_access' => true,
                    'custom_domain' => true,
                ],
                'is_active' => true,
            ],
        ];

        foreach ($plans as $planData) {
            Plan::updateOrCreate(
                ['slug' => $planData['slug']],
                $planData
            );
        }
    }
}
