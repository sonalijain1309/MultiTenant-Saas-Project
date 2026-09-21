<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoTenantSeeder extends Seeder
{
    public function run(): void
    {
        $basicPlan = Plan::where('slug', 'basic')->firstOrFail();
        $freePlan = Plan::where('slug', 'free')->firstOrFail();

        // ---------------------------------------------------------------------
        // DEMO TENANT 1: Apex Dynamics (Basic Plan)
        // ---------------------------------------------------------------------
        $apex = Tenant::updateOrCreate(
            ['slug' => 'apex-dynamics'],
            [
                'name' => 'Apex Dynamics',
                'domain' => 'apex.saas-platform.test',
                'email' => 'contact@apex.test',
                'phone' => '+1-555-0101',
                'status' => TenantStatus::ACTIVE,
                'plan_id' => $basicPlan->id,
                'settings' => ['timezone' => 'America/New_York', 'currency' => 'USD'],
            ]
        );

        Subscription::updateOrCreate(
            ['tenant_id' => $apex->id, 'plan_id' => $basicPlan->id],
            [
                'status' => SubscriptionStatus::ACTIVE,
                'starts_at' => now()->subMonth(),
                'ends_at' => now()->addMonths(11),
            ]
        );

        // Apex Users
        $apexUsers = [
            [
                'name' => 'Apex Owner',
                'email' => 'owner@apex.test',
                'password' => Hash::make('password'),
                'role' => UserRole::TENANT_OWNER,
                'tenant_id' => $apex->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Apex Manager',
                'email' => 'manager@apex.test',
                'password' => Hash::make('password'),
                'role' => UserRole::MANAGER,
                'tenant_id' => $apex->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Apex Staff',
                'email' => 'staff@apex.test',
                'password' => Hash::make('password'),
                'role' => UserRole::STAFF,
                'tenant_id' => $apex->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        ];
        foreach ($apexUsers as $userData) {
            User::updateOrCreate(['email' => $userData['email']], $userData);
        }

        // Apex Customers
        $apexCustomersData = [
            ['name' => 'Acme Corp', 'email' => 'acme@client-example.com', 'phone' => '+1-555-1111', 'company_name' => 'Acme Corporation', 'city' => 'New York'],
            ['name' => 'Stellar Tech', 'email' => 'stellar@client-example.com', 'phone' => '+1-555-2222', 'company_name' => 'Stellar Tech Inc', 'city' => 'Boston'],
            ['name' => 'Nova Retail', 'email' => 'nova@client-example.com', 'phone' => '+1-555-3333', 'company_name' => 'Nova Retail LLC', 'city' => 'Chicago'],
        ];
        $apexCustomers = [];
        foreach ($apexCustomersData as $cData) {
            $apexCustomers[] = Customer::updateOrCreate(
                ['tenant_id' => $apex->id, 'email' => $cData['email']],
                array_merge($cData, ['status' => 'active'])
            );
        }

        // Apex Products
        $apexProductsData = [
            ['sku' => 'APX-CLD-01', 'name' => 'Cloud Workspace License', 'price' => 49.00, 'cost_price' => 15.00, 'stock' => 100, 'description' => 'Dedicated cloud workspace license.'],
            ['sku' => 'APX-SEC-02', 'name' => 'Security Shield Addon', 'price' => 19.00, 'cost_price' => 5.00, 'stock' => 50, 'description' => 'Advanced malware protection.'],
            ['sku' => 'APX-API-03', 'name' => 'Enterprise API Gateway', 'price' => 99.00, 'cost_price' => 30.00, 'stock' => 25, 'description' => 'High-throughput API reverse proxy.'],
        ];
        $apexProducts = [];
        foreach ($apexProductsData as $pData) {
            $apexProducts[] = Product::updateOrCreate(
                ['tenant_id' => $apex->id, 'sku' => $pData['sku']],
                array_merge($pData, ['is_available' => true])
            );
        }

        // Apex Order 1 (Acme Corp)
        $order1 = Order::updateOrCreate(
            ['tenant_id' => $apex->id, 'order_number' => 'ORD-APX-001'],
            [
                'customer_id' => $apexCustomers[0]->id,
                'status' => OrderStatus::COMPLETED,
                'subtotal' => 68.00,
                'tax_amount' => 5.44,
                'discount_amount' => 0.00,
                'total_amount' => 73.44,
                'notes' => 'Priority deployment requested.',
                'ordered_at' => now()->subDays(5),
            ]
        );
        OrderItem::updateOrCreate(
            ['order_id' => $order1->id, 'product_id' => $apexProducts[0]->id],
            ['tenant_id' => $apex->id, 'product_name' => $apexProducts[0]->name, 'unit_price' => 49.00, 'quantity' => 1, 'subtotal' => 49.00]
        );
        OrderItem::updateOrCreate(
            ['order_id' => $order1->id, 'product_id' => $apexProducts[1]->id],
            ['tenant_id' => $apex->id, 'product_name' => $apexProducts[1]->name, 'unit_price' => 19.00, 'quantity' => 1, 'subtotal' => 19.00]
        );

        // Apex Order 2 (Stellar Tech)
        $order2 = Order::updateOrCreate(
            ['tenant_id' => $apex->id, 'order_number' => 'ORD-APX-002'],
            [
                'customer_id' => $apexCustomers[1]->id,
                'status' => OrderStatus::PROCESSING,
                'subtotal' => 99.00,
                'tax_amount' => 7.92,
                'discount_amount' => 10.00,
                'total_amount' => 96.92,
                'notes' => 'Early adopter promotional discount applied.',
                'ordered_at' => now()->subDay(),
            ]
        );
        OrderItem::updateOrCreate(
            ['order_id' => $order2->id, 'product_id' => $apexProducts[2]->id],
            ['tenant_id' => $apex->id, 'product_name' => $apexProducts[2]->name, 'unit_price' => 99.00, 'quantity' => 1, 'subtotal' => 99.00]
        );

        // ---------------------------------------------------------------------
        // DEMO TENANT 2: BlueWave Logistics (Free Plan)
        // ---------------------------------------------------------------------
        $blueWave = Tenant::updateOrCreate(
            ['slug' => 'bluewave-logistics'],
            [
                'name' => 'BlueWave Logistics',
                'domain' => 'bluewave.saas-platform.test',
                'email' => 'support@bluewave.test',
                'phone' => '+1-555-0202',
                'status' => TenantStatus::ACTIVE,
                'plan_id' => $freePlan->id,
                'settings' => ['timezone' => 'America/Los_Angeles', 'currency' => 'USD'],
            ]
        );

        Subscription::updateOrCreate(
            ['tenant_id' => $blueWave->id, 'plan_id' => $freePlan->id],
            [
                'status' => SubscriptionStatus::ACTIVE,
                'starts_at' => now()->subWeeks(2),
                'ends_at' => null,
            ]
        );

        // BlueWave Users
        $blueWaveUsers = [
            [
                'name' => 'BlueWave Owner',
                'email' => 'owner@bluewave.test',
                'password' => Hash::make('password'),
                'role' => UserRole::TENANT_OWNER,
                'tenant_id' => $blueWave->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'BlueWave Staff',
                'email' => 'staff@bluewave.test',
                'password' => Hash::make('password'),
                'role' => UserRole::STAFF,
                'tenant_id' => $blueWave->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        ];
        foreach ($blueWaveUsers as $userData) {
            User::updateOrCreate(['email' => $userData['email']], $userData);
        }

        // BlueWave Customers
        $blueWaveCustomersData = [
            ['name' => 'Harbor Freight Inc', 'email' => 'harbor@client-example.com', 'phone' => '+1-555-4444', 'company_name' => 'Harbor Freight Services', 'city' => 'Seattle'],
            ['name' => 'Pacific Cargo', 'email' => 'pacific@client-example.com', 'phone' => '+1-555-5555', 'company_name' => 'Pacific Ocean Cargo', 'city' => 'San Francisco'],
        ];
        $blueWaveCustomers = [];
        foreach ($blueWaveCustomersData as $cData) {
            $blueWaveCustomers[] = Customer::updateOrCreate(
                ['tenant_id' => $blueWave->id, 'email' => $cData['email']],
                array_merge($cData, ['status' => 'active'])
            );
        }

        // BlueWave Products
        $blueWaveProductsData = [
            ['sku' => 'BW-TRK-01', 'name' => 'GPS Fleet Tracker Basic', 'price' => 29.00, 'cost_price' => 12.00, 'stock' => 40, 'description' => 'Real-time vehicle GPS tracking beacon.'],
            ['sku' => 'BW-SCN-02', 'name' => 'Barcode Cargo Scanner', 'price' => 59.00, 'cost_price' => 25.00, 'stock' => 15, 'description' => 'Handheld wireless warehouse scanner.'],
        ];
        $blueWaveProducts = [];
        foreach ($blueWaveProductsData as $pData) {
            $blueWaveProducts[] = Product::updateOrCreate(
                ['tenant_id' => $blueWave->id, 'sku' => $pData['sku']],
                array_merge($pData, ['is_available' => true])
            );
        }

        // BlueWave Order 1 (Harbor Freight Inc)
        $bwOrder1 = Order::updateOrCreate(
            ['tenant_id' => $blueWave->id, 'order_number' => 'ORD-BW-001'],
            [
                'customer_id' => $blueWaveCustomers[0]->id,
                'status' => OrderStatus::COMPLETED,
                'subtotal' => 58.00,
                'tax_amount' => 4.64,
                'discount_amount' => 0.00,
                'total_amount' => 62.64,
                'notes' => 'Standard warehouse delivery.',
                'ordered_at' => now()->subDays(2),
            ]
        );
        OrderItem::updateOrCreate(
            ['order_id' => $bwOrder1->id, 'product_id' => $blueWaveProducts[0]->id],
            ['tenant_id' => $blueWave->id, 'product_name' => $blueWaveProducts[0]->name, 'unit_price' => 29.00, 'quantity' => 2, 'subtotal' => 58.00]
        );
    }
}
