@extends('layouts.app')

@section('title', $product->name)
@section('header_title', 'Product Details — ' . $product->name)

@section('content')
<div class="space-y-6">

    <!-- Breadcrumb & Top Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-400 mb-1">
                <a href="{{ route('products.index') }}" class="hover:text-white transition-colors">Products</a>
                <span>/</span>
                <span class="text-indigo-400 font-medium truncate max-w-[200px]">{{ $product->name }}</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-xl font-bold text-white tracking-tight">{{ $product->name }}</h1>
                @if($product->is_available)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        Available
                    </span>
                @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-700/50 text-slate-400 border border-slate-700">
                        Unavailable
                    </span>
                @endif
                @if($product->stock <= 0)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                        Out of Stock
                    </span>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a 
                href="{{ route('products.index') }}" 
                class="px-3.5 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-xs font-semibold border border-slate-700 transition-colors"
            >
                ← Back to List
            </a>

            @can('update', $product)
                <a 
                    href="{{ route('products.edit', $product) }}" 
                    class="px-3.5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-md shadow-indigo-600/30 transition-all flex items-center gap-1.5"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    <span>Edit Product</span>
                </a>
            @endcan

            @can('delete', $product)
                <form 
                    method="POST" 
                    action="{{ route('products.destroy', $product) }}" 
                    class="inline"
                    onsubmit="return confirm('Are you sure you want to delete product [{{ addslashes($product->name) }}]?');"
                >
                    @csrf
                    @method('DELETE')
                    <button 
                        type="submit" 
                        class="px-3.5 py-2 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 hover:text-rose-300 text-xs font-semibold border border-rose-500/30 transition-colors"
                    >
                        Delete
                    </button>
                </form>
            @endcan
        </div>
    </div>

    <!-- 2-Column Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Column 1: Product Specifications Card -->
        <div class="lg:col-span-1 p-6 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm space-y-6">
            <div class="flex items-center gap-4 pb-4 border-b border-slate-800">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-indigo-600 to-violet-500 text-white font-extrabold text-2xl flex items-center justify-center shadow-lg shadow-indigo-500/25 shrink-0">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                </div>
                <div>
                    <h2 class="text-base font-bold text-white">{{ $product->name }}</h2>
                    <span class="text-xs text-slate-400 font-mono block">SKU: {{ $product->sku }}</span>
                </div>
            </div>

            <!-- Profile Info Fields -->
            <div class="space-y-4 text-xs">
                <!-- Pricing Breakdown -->
                <div class="p-3 rounded-xl bg-slate-800/60 border border-slate-700/60 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Selling Price</span>
                        <span class="text-emerald-400 font-bold text-sm">${{ number_format($product->price, 2) }}</span>
                    </div>
                    @if($product->cost_price !== null)
                        <div class="flex items-center justify-between border-t border-slate-700/50 pt-2">
                            <span class="text-slate-400">Cost Price</span>
                            <span class="text-slate-300 font-medium">${{ number_format($product->cost_price, 2) }}</span>
                        </div>
                        @php
                            $marginDollars = $product->price - $product->cost_price;
                            $marginPercent = $product->price > 0 ? ($marginDollars / $product->price) * 100 : 0;
                        @endphp
                        <div class="flex items-center justify-between border-t border-slate-700/50 pt-2">
                            <span class="text-slate-400">Profit Margin</span>
                            <span class="text-indigo-300 font-medium">${{ number_format($marginDollars, 2) }} ({{ number_format($marginPercent, 1) }}%)</span>
                        </div>
                    @endif
                </div>

                <!-- Stock Inventory -->
                <div>
                    <span class="text-slate-400 block uppercase tracking-wider text-[11px] mb-1">Inventory Level</span>
                    @if($product->stock > 10)
                        <span class="text-emerald-400 font-semibold text-sm">{{ $product->stock }} units available</span>
                    @elseif($product->stock > 0)
                        <span class="text-amber-400 font-semibold text-sm">{{ $product->stock }} units remaining (Low stock)</span>
                    @else
                        <span class="text-rose-400 font-semibold text-sm">0 units (Out of stock)</span>
                    @endif
                </div>

                <!-- Product Description -->
                <div>
                    <span class="text-slate-400 block uppercase tracking-wider text-[11px] mb-1">Description</span>
                    <p class="text-slate-300 leading-relaxed bg-slate-800/30 p-3 rounded-lg border border-slate-800">
                        {{ $product->description ?: 'No product description provided.' }}
                    </p>
                </div>

                <!-- Metadata -->
                <div class="pt-3 border-t border-slate-800 space-y-1 text-slate-400">
                    <div class="flex items-center justify-between">
                        <span>Created:</span>
                        <span class="font-medium text-slate-300">{{ $product->created_at?->format('M d, Y') }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Last Updated:</span>
                        <span class="font-medium text-slate-300">{{ $product->updated_at?->format('M d, Y') }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Column 2: Sales Metrics & Order History -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Metrics Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="p-4 rounded-xl bg-slate-900 border border-slate-800 shadow-sm">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Units Sold</span>
                    <span class="text-2xl font-bold text-white mt-1 block">{{ number_format($totalSold) }}</span>
                    <span class="text-[11px] text-slate-500 mt-0.5 block">Lifetime fulfilled quantity</span>
                </div>
                <div class="p-4 rounded-xl bg-slate-900 border border-slate-800 shadow-sm">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Gross Sales</span>
                    <span class="text-2xl font-bold text-emerald-400 mt-1 block">${{ number_format($totalRevenue, 2) }}</span>
                    <span class="text-[11px] text-slate-500 mt-0.5 block">Total revenue generated</span>
                </div>
                <div class="p-4 rounded-xl bg-slate-900 border border-slate-800 shadow-sm">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Orders</span>
                    <span class="text-2xl font-bold text-indigo-400 mt-1 block">{{ $ordersCount }}</span>
                    <span class="text-[11px] text-slate-500 mt-0.5 block">Distinct transactions</span>
                </div>
            </div>

            <!-- Recent Orders Card -->
            <div class="p-6 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-white tracking-tight">Recent Order Transactions</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Purchases that included this catalog product.</p>
                    </div>
                    <span class="text-xs px-2.5 py-1 rounded bg-slate-800 text-slate-300 border border-slate-700">
                        {{ $product->orderItems->count() }} Line Items
                    </span>
                </div>

                @if($product->orderItems->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-300">
                            <thead class="uppercase bg-slate-800/60 text-slate-400 border-b border-slate-700">
                                <tr>
                                    <th class="px-4 py-3">Order Number</th>
                                    <th class="px-4 py-3">Customer</th>
                                    <th class="px-4 py-3 text-center">Qty</th>
                                    <th class="px-4 py-3 text-right">Unit Price</th>
                                    <th class="px-4 py-3 text-right">Subtotal</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800">
                                @foreach($product->orderItems as $item)
                                    <tr class="hover:bg-slate-800/40 transition-colors">
                                        <td class="px-4 py-3 font-mono font-semibold text-indigo-400">
                                            {{ $item->order?->order_number ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3">
                                            @if($item->order?->customer)
                                                <a href="{{ route('customers.show', $item->order->customer) }}" class="text-slate-200 hover:text-indigo-400 transition-colors">
                                                    {{ $item->order->customer->name }}
                                                </a>
                                            @else
                                                <span class="text-slate-500">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-center font-semibold text-white">
                                            {{ $item->quantity }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-slate-300">
                                            ${{ number_format($item->unit_price, 2) }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-semibold text-emerald-400">
                                            ${{ number_format($item->subtotal, 2) }}
                                        </td>
                                        <td class="px-4 py-3">
                                            @if($item->order)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium 
                                                    {{ $item->order->status->value === 'completed' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : '' }}
                                                    {{ $item->order->status->value === 'processing' ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20' : '' }}
                                                    {{ $item->order->status->value === 'pending' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : '' }}
                                                    {{ $item->order->status->value === 'canceled' ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' : '' }}
                                                ">
                                                    {{ $item->order->status->label() }}
                                                </span>
                                            @else
                                                <span class="text-slate-500">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-slate-400">
                                            {{ $item->order?->ordered_at?->format('M d, Y') ?? $item->created_at?->format('M d, Y') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-8 text-center rounded-xl bg-slate-800/40 border border-dashed border-slate-700 space-y-2">
                        <p class="text-xs text-slate-400">No order sales recorded for this product yet.</p>
                    </div>
                @endif
            </div>

        </div>

    </div>

</div>
@endsection
