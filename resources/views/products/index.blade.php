@extends('layouts.app')

@section('title', 'Products')
@section('header_title', 'Product Catalog')

@section('content')
<div class="space-y-6">

    <!-- Page Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-white tracking-tight">Product Catalog</h1>
            <p class="text-xs text-slate-400 mt-1">
                Showing {{ $products->total() }} total product items for this workspace
                @if($productLimit)
                    <span class="text-indigo-400 font-medium">({{ $totalProducts }}/{{ $productLimit }} allocated)</span>
                @else
                    <span class="text-emerald-400 font-medium">(Unlimited catalog capacity)</span>
                @endif
            </p>
        </div>

        <div>
            <a 
                href="{{ route('products.create') }}" 
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-500 shadow-lg shadow-indigo-600/30 transition-all"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                <span>Add Product</span>
            </a>
        </div>
    </div>

    <!-- Filters & Search Form -->
    <div class="p-4 rounded-xl bg-slate-900 border border-slate-800 shadow-sm">
        <form method="GET" action="{{ route('products.index') }}" class="flex flex-col sm:flex-row gap-3">
            <!-- Search Input -->
            <div class="flex-1 relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <input 
                    type="text" 
                    name="search" 
                    value="{{ $search }}" 
                    placeholder="Search by product name or SKU..." 
                    class="w-full pl-9 pr-3 py-2 rounded-lg bg-slate-800/80 border border-slate-700 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                >
            </div>

            <!-- Status / Availability Filter -->
            <div class="w-full sm:w-48">
                <select 
                    name="status" 
                    class="w-full px-3 py-2 rounded-lg bg-slate-800/80 border border-slate-700 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                >
                    <option value="">All Statuses</option>
                    <option value="available" {{ $status === 'available' ? 'selected' : '' }}>Available</option>
                    <option value="unavailable" {{ $status === 'unavailable' ? 'selected' : '' }}>Unavailable</option>
                    <option value="out_of_stock" {{ $status === 'out_of_stock' ? 'selected' : '' }}>Out of Stock</option>
                </select>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center gap-2">
                <button 
                    type="submit" 
                    class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-sm font-semibold text-white transition-colors"
                >
                    Filter
                </button>
                @if($search || $status)
                    <a 
                        href="{{ route('products.index') }}" 
                        class="px-3 py-2 rounded-lg text-xs font-medium text-slate-400 hover:text-white transition-colors"
                    >
                        Clear
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Products Data Table -->
    <div class="rounded-2xl bg-slate-900 border border-slate-800 shadow-sm overflow-hidden">
        @if($products->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="text-xs uppercase bg-slate-800/70 text-slate-400 border-b border-slate-800 tracking-wider">
                        <tr>
                            <th class="px-5 py-3.5">Product / SKU</th>
                            <th class="px-5 py-3.5">Price & Cost</th>
                            <th class="px-5 py-3.5">Stock Level</th>
                            <th class="px-5 py-3.5">Status</th>
                            <th class="px-5 py-3.5">Created Date</th>
                            <th class="px-5 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach($products as $product)
                            <tr class="hover:bg-slate-800/40 transition-colors">
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-lg bg-indigo-500/10 text-indigo-400 font-bold flex items-center justify-center border border-indigo-500/20 shrink-0">
                                            <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <a href="{{ route('products.show', $product) }}" class="font-semibold text-white hover:text-indigo-400 transition-colors block">
                                                {{ $product->name }}
                                            </a>
                                            <span class="text-xs text-slate-400 font-mono block">SKU: {{ $product->sku }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="text-xs space-y-0.5">
                                        <span class="block text-slate-200 font-semibold">${{ number_format($product->price, 2) }}</span>
                                        @if($product->cost_price !== null)
                                            <span class="block text-slate-500 text-[11px]">Cost: ${{ number_format($product->cost_price, 2) }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    @if($product->stock > 10)
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                            {{ $product->stock }} in stock
                                        </span>
                                    @elseif($product->stock > 0)
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded text-xs font-medium bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                                            {{ $product->stock }} (Low)
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded text-xs font-medium bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                                            Out of stock
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-4">
                                    @if($product->is_available)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                            Available
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-700/50 text-slate-400 border border-slate-700">
                                            Unavailable
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-xs text-slate-400">
                                    {{ $product->created_at?->format('M d, Y') }}
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2 text-xs">
                                        <a 
                                            href="{{ route('products.show', $product) }}" 
                                            class="px-2.5 py-1.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700 transition-colors"
                                            title="View Details"
                                        >
                                            View
                                        </a>

                                        @can('update', $product)
                                            <a 
                                                href="{{ route('products.edit', $product) }}" 
                                                class="px-2.5 py-1.5 rounded bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-400 hover:text-indigo-300 border border-indigo-500/30 transition-colors"
                                                title="Edit Product"
                                            >
                                                Edit
                                            </a>
                                        @endcan

                                        @can('delete', $product)
                                            <form 
                                                method="POST" 
                                                action="{{ route('products.destroy', $product) }}" 
                                                class="inline"
                                                onsubmit="return confirm('Are you sure you want to delete product [{{ addslashes($product->name) }}]? This action will soft-delete the record.');"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button 
                                                    type="submit" 
                                                    class="px-2.5 py-1.5 rounded bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 hover:text-rose-300 border border-rose-500/30 transition-colors"
                                                    title="Delete Product"
                                                >
                                                    Delete
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            @if($products->hasPages())
                <div class="p-4 border-t border-slate-800 bg-slate-900/50">
                    {{ $products->links() }}
                </div>
            @endif

        @else
            <!-- Empty State -->
            <div class="p-12 text-center space-y-4">
                <div class="w-12 h-12 rounded-2xl bg-slate-800 flex items-center justify-center mx-auto text-slate-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                </div>
                <div class="space-y-1">
                    <h3 class="text-base font-semibold text-white">No Products Found</h3>
                    <p class="text-xs text-slate-400 max-w-sm mx-auto">
                        @if($search || $status)
                            No product records match your filter criteria. Try clearing your search parameters.
                        @else
                            Start building your inventory catalog by adding your first product item.
                        @endif
                    </p>
                </div>
                <div>
                    @if($search || $status)
                        <a href="{{ route('products.index') }}" class="inline-flex items-center px-4 py-2 rounded-lg text-xs font-semibold text-slate-300 bg-slate-800 hover:bg-slate-700 border border-slate-700 transition-colors">
                            Clear Filters
                        </a>
                    @else
                        <a href="{{ route('products.create') }}" class="inline-flex items-center px-4 py-2 rounded-lg text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-500 shadow-md shadow-indigo-600/30 transition-colors">
                            Create First Product
                        </a>
                    @endif
                </div>
            </div>
        @endif
    </div>

</div>
@endsection
