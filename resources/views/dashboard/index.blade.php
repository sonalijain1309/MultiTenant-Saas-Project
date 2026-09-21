@extends('layouts.app')

@section('title', $isSuperAdmin ? 'Platform Overview' : ($tenant->name ?? 'Dashboard'))
@section('header_title', $isSuperAdmin ? 'Global Platform Analytics' : ($tenant->name . ' — Dashboard'))

@section('content')
<div class="space-y-6">

    <!-- ============================================================= -->
    <!-- 1. WELCOME BANNER & TENANT CONTEXT -->
    <!-- ============================================================= -->
    <div class="p-6 rounded-2xl bg-gradient-to-r from-slate-900 via-indigo-950/40 to-slate-900 border border-slate-800 flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-xl">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="text-xs font-semibold uppercase tracking-wider text-indigo-400">Authenticated Session</span>
                <span class="text-slate-600">•</span>
                <span class="text-xs text-slate-400">{{ $user->role->label() }}</span>
            </div>
            <h1 class="text-2xl font-bold text-white tracking-tight">
                Welcome back, {{ $user->name }}!
            </h1>
            <p class="text-sm text-slate-400 mt-1">
                @if($isSuperAdmin)
                    Platform-level administrator view across all tenant organizations.
                @else
                    Operating within workspace <strong class="text-slate-200">{{ $tenant->name }}</strong>. All metrics and records are strictly isolated to your organization.
                @endif
            </p>
        </div>

        @if(! $isSuperAdmin && $plan)
            <div class="shrink-0 p-4 rounded-xl bg-slate-800/80 border border-slate-700/70 text-right">
                <span class="text-xs text-slate-400 uppercase tracking-wider block">Subscription Plan</span>
                <div class="text-lg font-bold text-white flex items-center justify-end gap-2 mt-0.5">
                    <span class="text-indigo-400">{{ $plan->name }} Tier</span>
                    <span class="text-xs font-medium text-slate-400">(${{ number_format($plan->price, 2) }}/mo)</span>
                </div>
                <div class="mt-1">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        {{ ucfirst($subscription->status->value ?? 'active') }}
                    </span>
                </div>
            </div>
        @endif
    </div>

    @if($isSuperAdmin)
        <!-- ============================================================= -->
        <!-- SUPER ADMIN GLOBAL PLATFORM METRICS -->
        <!-- ============================================================= -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Total Workspaces / Tenants</span>
                <div class="mt-2 flex items-baseline justify-between">
                    <span class="text-3xl font-extrabold text-white">{{ number_format($stats['total_tenants']) }}</span>
                    <span class="text-xs text-indigo-400 font-medium bg-indigo-500/10 px-2 py-1 rounded">System-wide</span>
                </div>
            </div>

            <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Platform Users</span>
                <div class="mt-2 flex items-baseline justify-between">
                    <span class="text-3xl font-extrabold text-white">{{ number_format($stats['total_users']) }}</span>
                    <span class="text-xs text-emerald-400 font-medium bg-emerald-500/10 px-2 py-1 rounded">Registered</span>
                </div>
            </div>

            <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Total Customers</span>
                <div class="mt-2 flex items-baseline justify-between">
                    <span class="text-3xl font-extrabold text-white">{{ number_format($stats['total_customers']) }}</span>
                    <span class="text-xs text-slate-400 font-medium">All Tenants</span>
                </div>
            </div>

            <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Total Products</span>
                <div class="mt-2 flex items-baseline justify-between">
                    <span class="text-3xl font-extrabold text-white">{{ number_format($stats['total_products']) }}</span>
                    <span class="text-xs text-slate-400 font-medium">All Catalogs</span>
                </div>
            </div>

            <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Total Orders</span>
                <div class="mt-2 flex items-baseline justify-between">
                    <span class="text-3xl font-extrabold text-white">{{ number_format($stats['total_orders']) }}</span>
                    <span class="text-xs text-slate-400 font-medium">Placed</span>
                </div>
            </div>

            <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Gross System Revenue</span>
                <div class="mt-2 flex items-baseline justify-between">
                    <span class="text-3xl font-extrabold text-emerald-400">${{ number_format($stats['total_revenue'], 2) }}</span>
                    <span class="text-xs text-emerald-400/80 font-medium bg-emerald-500/10 px-2 py-1 rounded">Gross</span>
                </div>
            </div>
        </div>

        <!-- Recent Registered Tenants -->
        <div class="p-6 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm">
            <h2 class="text-base font-bold text-white mb-4">Provisioned Tenant Organizations</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="text-xs uppercase bg-slate-800/60 text-slate-400 border-b border-slate-700">
                        <tr>
                            <th class="px-4 py-3">Tenant Name</th>
                            <th class="px-4 py-3">Slug</th>
                            <th class="px-4 py-3">Plan Tier</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Provisioned Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach($recentTenants as $t)
                            <tr class="hover:bg-slate-800/40">
                                <td class="px-4 py-3 font-semibold text-white">{{ $t->name }}</td>
                                <td class="px-4 py-3 text-slate-400 font-mono text-xs">{{ $t->slug }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 text-xs font-medium rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                                        {{ $t->plan->name ?? 'None' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 text-xs font-medium rounded bg-emerald-500/10 text-emerald-400">
                                        {{ ucfirst($t->status->value) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-400 text-xs">{{ $t->created_at->format('M d, Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    @else
        <!-- ============================================================= -->
        <!-- TENANT-ISOLATED OPERATIONAL METRICS -->
        <!-- ============================================================= -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <!-- Total Customers -->
            <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm relative overflow-hidden group">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Customers</span>
                    <div class="w-8 h-8 rounded-lg bg-indigo-500/10 text-indigo-400 flex items-center justify-center border border-indigo-500/20">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="text-3xl font-extrabold text-white tracking-tight">{{ number_format($stats['total_customers']) }}</span>
                    <span class="text-xs text-slate-400 ml-1.5">records</span>
                </div>
            </div>

            <!-- Total Products -->
            <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm relative overflow-hidden group">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Catalog Products</span>
                    <div class="w-8 h-8 rounded-lg bg-violet-500/10 text-violet-400 flex items-center justify-center border border-violet-500/20">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="text-3xl font-extrabold text-white tracking-tight">{{ number_format($stats['total_products']) }}</span>
                    <span class="text-xs text-slate-400 ml-1.5">items</span>
                </div>
            </div>

            <!-- Total Orders -->
            <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm relative overflow-hidden group">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Orders</span>
                    <div class="w-8 h-8 rounded-lg bg-sky-500/10 text-sky-400 flex items-center justify-center border border-sky-500/20">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="text-3xl font-extrabold text-white tracking-tight">{{ number_format($stats['total_orders']) }}</span>
                    <span class="text-xs text-slate-400 ml-1.5">placed</span>
                </div>
            </div>

            <!-- Total Revenue -->
            <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm relative overflow-hidden group">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Revenue</span>
                    <div class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center border border-emerald-500/20">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="text-3xl font-extrabold text-emerald-400 tracking-tight">${{ number_format($stats['total_revenue'], 2) }}</span>
                    <span class="text-xs text-slate-400 ml-1.5">USD</span>
                </div>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- SUBSCRIPTION LIMITS & REAL-TIME QUOTA GAUGES -->
        <!-- ============================================================= -->
        <div class="p-6 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h2 class="text-base font-bold text-white tracking-tight">Plan Quotas & Resource Consumption</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Automated enforcement powered by <code class="text-indigo-300 font-mono text-xs">SubscriptionService</code></p>
                </div>
                <span class="text-xs font-medium text-slate-400">Plan: <strong class="text-white">{{ $plan->name ?? 'Default' }}</strong></span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Customer Quota -->
                <div class="p-4 rounded-xl bg-slate-800/60 border border-slate-700/60">
                    <div class="flex items-center justify-between text-xs font-semibold mb-2">
                        <span class="text-slate-300">Customer Capacity</span>
                        <span class="text-slate-400">
                            {{ $stats['customer_usage'] }} / {{ $stats['customer_limit'] ?? '∞' }}
                        </span>
                    </div>
                    <div class="w-full bg-slate-700/60 rounded-full h-2 overflow-hidden">
                        <div 
                            class="h-2 rounded-full bg-gradient-to-r from-indigo-500 to-indigo-400 transition-all duration-500"
                            style="width: {{ $stats['customer_percentage'] ?? 0 }}%"
                        ></div>
                    </div>
                    <div class="mt-2 text-right text-[11px] text-slate-400">
                        {{ $stats['customer_percentage'] !== null ? $stats['customer_percentage'] . '% utilized' : 'Unlimited allocation' }}
                    </div>
                </div>

                <!-- Product Quota -->
                <div class="p-4 rounded-xl bg-slate-800/60 border border-slate-700/60">
                    <div class="flex items-center justify-between text-xs font-semibold mb-2">
                        <span class="text-slate-300">Product Catalog</span>
                        <span class="text-slate-400">
                            {{ $stats['product_usage'] }} / {{ $stats['product_limit'] ?? '∞' }}
                        </span>
                    </div>
                    <div class="w-full bg-slate-700/60 rounded-full h-2 overflow-hidden">
                        <div 
                            class="h-2 rounded-full bg-gradient-to-r from-violet-500 to-violet-400 transition-all duration-500"
                            style="width: {{ $stats['product_percentage'] ?? 0 }}%"
                        ></div>
                    </div>
                    <div class="mt-2 text-right text-[11px] text-slate-400">
                        {{ $stats['product_percentage'] !== null ? $stats['product_percentage'] . '% utilized' : 'Unlimited allocation' }}
                    </div>
                </div>

                <!-- Monthly Orders Quota -->
                <div class="p-4 rounded-xl bg-slate-800/60 border border-slate-700/60">
                    <div class="flex items-center justify-between text-xs font-semibold mb-2">
                        <span class="text-slate-300">Monthly Orders ({{ now()->format('M Y') }})</span>
                        <span class="text-slate-400">
                            {{ $stats['order_usage'] }} / {{ $stats['order_limit'] ?? '∞' }}
                        </span>
                    </div>
                    <div class="w-full bg-slate-700/60 rounded-full h-2 overflow-hidden">
                        <div 
                            class="h-2 rounded-full bg-gradient-to-r from-sky-500 to-sky-400 transition-all duration-500"
                            style="width: {{ $stats['order_percentage'] ?? 0 }}%"
                        ></div>
                    </div>
                    <div class="mt-2 text-right text-[11px] text-slate-400">
                        {{ $stats['order_percentage'] !== null ? $stats['order_percentage'] . '% utilized' : 'Unlimited allocation' }}
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- OPERATIONAL SNAPSHOT (Recent Orders & Catalog) -->
        <!-- ============================================================= -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Recent Orders Table -->
            <div class="p-6 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-bold text-white tracking-tight">Recent Orders</h2>
                    <span class="text-xs text-slate-400">Latest 5 records</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="uppercase bg-slate-800/60 text-slate-400 border-b border-slate-700">
                            <tr>
                                <th class="px-3 py-2.5">Order #</th>
                                <th class="px-3 py-2.5">Customer</th>
                                <th class="px-3 py-2.5">Status</th>
                                <th class="px-3 py-2.5 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @forelse($recentOrders as $order)
                                <tr class="hover:bg-slate-800/40">
                                    <td class="px-3 py-2.5 font-mono text-indigo-300 font-semibold">{{ $order->order_number }}</td>
                                    <td class="px-3 py-2.5 font-medium text-white truncate max-w-[130px]">{{ $order->customer->name ?? 'N/A' }}</td>
                                    <td class="px-3 py-2.5">
                                        <span class="px-2 py-0.5 rounded text-[11px] font-medium 
                                            {{ $order->status->value === 'completed' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : '' }}
                                            {{ $order->status->value === 'processing' ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20' : '' }}
                                            {{ $order->status->value === 'pending' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : '' }}
                                            {{ $order->status->value === 'canceled' ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' : '' }}
                                        ">
                                            {{ $order->status->label() }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2.5 text-right font-semibold text-white">${{ number_format($order->total_amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-3 py-4 text-center text-slate-500">No orders recorded for this workspace yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Product Inventory Snapshot -->
            <div class="p-6 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-bold text-white tracking-tight">Active Product Catalog</h2>
                    <span class="text-xs text-slate-400">Latest 5 records</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="uppercase bg-slate-800/60 text-slate-400 border-b border-slate-700">
                            <tr>
                                <th class="px-3 py-2.5">SKU</th>
                                <th class="px-3 py-2.5">Product Name</th>
                                <th class="px-3 py-2.5">Stock</th>
                                <th class="px-3 py-2.5 text-right">Price</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @forelse($recentProducts as $prod)
                                <tr class="hover:bg-slate-800/40">
                                    <td class="px-3 py-2.5 font-mono text-slate-400">{{ $prod->sku }}</td>
                                    <td class="px-3 py-2.5 font-medium text-white truncate max-w-[150px]">{{ $prod->name }}</td>
                                    <td class="px-3 py-2.5">
                                        <span class="px-1.5 py-0.5 rounded text-[11px] font-medium {{ $prod->stock > 10 ? 'text-emerald-400 bg-emerald-500/10' : 'text-amber-400 bg-amber-500/10' }}">
                                            {{ $prod->stock }} units
                                        </span>
                                    </td>
                                    <td class="px-3 py-2.5 text-right font-semibold text-white">${{ number_format($prod->price, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-3 py-4 text-center text-slate-500">No products configured in catalog.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

</div>
@endsection
