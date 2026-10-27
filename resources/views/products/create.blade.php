@extends('layouts.app')

@section('title', 'Add Product')
@section('header_title', 'Create New Product')

@section('content')
<div class="max-w-3xl space-y-6">

    <!-- Breadcrumb & Title -->
    <div>
        <div class="flex items-center gap-2 text-xs text-slate-400 mb-1">
            <a href="{{ route('products.index') }}" class="hover:text-white transition-colors">Products</a>
            <span>/</span>
            <span class="text-indigo-400 font-medium">New Product</span>
        </div>
        <h1 class="text-xl font-bold text-white tracking-tight">Add New Product</h1>
        <p class="text-xs text-slate-400 mt-0.5">Register a new catalog item under your active workspace inventory.</p>
    </div>

    <!-- Quota Warning Banner if at limit -->
    @if(! $canCreate)
        <div class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-300 text-sm flex items-center gap-3">
            <svg class="w-5 h-5 text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            <div>
                <strong class="font-semibold block">Product Limit Reached</strong>
                <span class="text-xs text-amber-300/80">You have utilized {{ $currentCount }} of your {{ $productLimit }} product catalog quota. Upgrading your plan will allow adding more products.</span>
            </div>
        </div>
    @endif

    <!-- Form Card -->
    <div class="p-6 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm">
        <form method="POST" action="{{ route('products.store') }}" class="space-y-5">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <!-- Product Name -->
                <div class="md:col-span-2">
                    <label for="name" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                        Product Name <span class="text-rose-400">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="name" 
                        id="name" 
                        required 
                        value="{{ old('name') }}" 
                        placeholder="e.g. Enterprise Cloud Server Node"
                        class="w-full px-3.5 py-2.5 rounded-lg bg-slate-800/80 border {{ $errors->has('name') ? 'border-rose-500 ring-1 ring-rose-500' : 'border-slate-700' }} text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all placeholder-slate-500"
                    >
                    @error('name')
                        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- SKU -->
                <div>
                    <label for="sku" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                        Stock Keeping Unit (SKU) <span class="text-rose-400">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="sku" 
                        id="sku" 
                        required 
                        value="{{ old('sku') }}" 
                        placeholder="e.g. PROD-SRV-001"
                        class="w-full px-3.5 py-2.5 rounded-lg bg-slate-800/80 border {{ $errors->has('sku') ? 'border-rose-500 ring-1 ring-rose-500' : 'border-slate-700' }} text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all placeholder-slate-500 font-mono"
                    >
                    @error('sku')
                        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Initial Stock -->
                <div>
                    <label for="stock" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                        Inventory Stock Level
                    </label>
                    <input 
                        type="number" 
                        name="stock" 
                        id="stock" 
                        min="0"
                        value="{{ old('stock', 0) }}" 
                        class="w-full px-3.5 py-2.5 rounded-lg bg-slate-800/80 border {{ $errors->has('stock') ? 'border-rose-500 ring-1 ring-rose-500' : 'border-slate-700' }} text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all placeholder-slate-500"
                    >
                    @error('stock')
                        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Selling Price -->
                <div>
                    <label for="price" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                        Selling Price ($) <span class="text-rose-400">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 text-sm">$</span>
                        <input 
                            type="number" 
                            step="0.01" 
                            min="0"
                            name="price" 
                            id="price" 
                            required 
                            value="{{ old('price') }}" 
                            placeholder="99.99"
                            class="w-full pl-8 pr-3.5 py-2.5 rounded-lg bg-slate-800/80 border {{ $errors->has('price') ? 'border-rose-500 ring-1 ring-rose-500' : 'border-slate-700' }} text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all placeholder-slate-500"
                        >
                    </div>
                    @error('price')
                        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Cost Price -->
                <div>
                    <label for="cost_price" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                        Cost Price ($)
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 text-sm">$</span>
                        <input 
                            type="number" 
                            step="0.01" 
                            min="0"
                            name="cost_price" 
                            id="cost_price" 
                            value="{{ old('cost_price') }}" 
                            placeholder="49.50"
                            class="w-full pl-8 pr-3.5 py-2.5 rounded-lg bg-slate-800/80 border {{ $errors->has('cost_price') ? 'border-rose-500 ring-1 ring-rose-500' : 'border-slate-700' }} text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all placeholder-slate-500"
                        >
                    </div>
                    @error('cost_price')
                        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Description -->
            <div>
                <label for="description" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                    Product Description
                </label>
                <textarea 
                    name="description" 
                    id="description" 
                    rows="3" 
                    placeholder="Provide technical specifications, product features or notes..."
                    class="w-full px-3.5 py-2.5 rounded-lg bg-slate-800/80 border {{ $errors->has('description') ? 'border-rose-500 ring-1 ring-rose-500' : 'border-slate-700' }} text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all placeholder-slate-500"
                >{{ old('description') }}</textarea>
                @error('description')
                    <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            <!-- Availability Toggle -->
            <div class="pt-2">
                <input type="hidden" name="is_available" value="0">
                <label class="inline-flex items-center gap-3 cursor-pointer">
                    <input 
                        type="checkbox" 
                        name="is_available" 
                        value="1" 
                        {{ old('is_available', '1') == '1' ? 'checked' : '' }}
                        class="w-4 h-4 rounded bg-slate-800 border-slate-700 text-indigo-600 focus:ring-indigo-500 focus:ring-offset-slate-900"
                    >
                    <span class="text-sm font-medium text-slate-300">Available for customer orders</span>
                </label>
            </div>

            <!-- Action Buttons -->
            <div class="pt-4 border-t border-slate-800 flex items-center justify-end gap-3">
                <a 
                    href="{{ route('products.index') }}" 
                    class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-semibold border border-slate-700 transition-colors"
                >
                    Cancel
                </a>
                <button 
                    type="submit" 
                    class="px-5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold shadow-lg shadow-indigo-600/30 transition-all"
                >
                    Create Product
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
