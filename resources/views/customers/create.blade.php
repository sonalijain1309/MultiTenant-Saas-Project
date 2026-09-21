@extends('layouts.app')

@section('title', 'Add Customer')
@section('header_title', 'Create New Customer')

@section('content')
<div class="max-w-3xl space-y-6">

    <!-- Breadcrumb & Title -->
    <div>
        <div class="flex items-center gap-2 text-xs text-slate-400 mb-1">
            <a href="{{ route('customers.index') }}" class="hover:text-white transition-colors">Customers</a>
            <span>/</span>
            <span class="text-indigo-400 font-medium">New Customer</span>
        </div>
        <h1 class="text-xl font-bold text-white tracking-tight">Add New Customer</h1>
        <p class="text-xs text-slate-400 mt-0.5">Record a new client profile under your active workspace.</p>
    </div>

    <!-- Quota Warning Banner if at limit -->
    @if(! $canCreate)
        <div class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-300 text-sm flex items-center gap-3">
            <svg class="w-5 h-5 text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            <div>
                <strong class="font-semibold block">Customer Limit Reached</strong>
                <span class="text-xs text-amber-300/80">You have utilized {{ $currentCount }} of your {{ $customerLimit }} customer quota. Upgrading your plan will allow adding more customers.</span>
            </div>
        </div>
    @endif

    <!-- Form Card -->
    <div class="p-6 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm">
        <form method="POST" action="{{ route('customers.store') }}" class="space-y-5">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <!-- Name -->
                <div>
                    <label for="name" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                        Customer / Contact Name <span class="text-rose-400">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="name" 
                        id="name" 
                        required 
                        value="{{ old('name') }}" 
                        placeholder="e.g. Jane Doe"
                        class="w-full px-3.5 py-2.5 rounded-lg bg-slate-800/80 border {{ $errors->has('name') ? 'border-rose-500 ring-1 ring-rose-500' : 'border-slate-700' }} text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all placeholder-slate-500"
                    >
                    @error('name')
                        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                        Email Address <span class="text-rose-400">*</span>
                    </label>
                    <input 
                        type="email" 
                        name="email" 
                        id="email" 
                        required 
                        value="{{ old('email') }}" 
                        placeholder="client@example.com"
                        class="w-full px-3.5 py-2.5 rounded-lg bg-slate-800/80 border {{ $errors->has('email') ? 'border-rose-500 ring-1 ring-rose-500' : 'border-slate-700' }} text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all placeholder-slate-500 font-mono"
                    >
                    @error('email')
                        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Phone -->
                <div>
                    <label for="phone" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                        Phone Number
                    </label>
                    <input 
                        type="text" 
                        name="phone" 
                        id="phone" 
                        value="{{ old('phone') }}" 
                        placeholder="+1-555-0123"
                        class="w-full px-3.5 py-2.5 rounded-lg bg-slate-800/80 border {{ $errors->has('phone') ? 'border-rose-500 ring-1 ring-rose-500' : 'border-slate-700' }} text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all placeholder-slate-500"
                    >
                    @error('phone')
                        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Company Name -->
                <div>
                    <label for="company_name" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                        Company Name
                    </label>
                    <input 
                        type="text" 
                        name="company_name" 
                        id="company_name" 
                        value="{{ old('company_name') }}" 
                        placeholder="Acme Global Inc"
                        class="w-full px-3.5 py-2.5 rounded-lg bg-slate-800/80 border {{ $errors->has('company_name') ? 'border-rose-500 ring-1 ring-rose-500' : 'border-slate-700' }} text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all placeholder-slate-500"
                    >
                    @error('company_name')
                        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- City -->
                <div>
                    <label for="city" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                        City
                    </label>
                    <input 
                        type="text" 
                        name="city" 
                        id="city" 
                        value="{{ old('city') }}" 
                        placeholder="San Francisco"
                        class="w-full px-3.5 py-2.5 rounded-lg bg-slate-800/80 border {{ $errors->has('city') ? 'border-rose-500 ring-1 ring-rose-500' : 'border-slate-700' }} text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all placeholder-slate-500"
                    >
                    @error('city')
                        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Status -->
                <div>
                    <label for="status" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                        Customer Status
                    </label>
                    <select 
                        name="status" 
                        id="status" 
                        class="w-full px-3.5 py-2.5 rounded-lg bg-slate-800/80 border {{ $errors->has('status') ? 'border-rose-500 ring-1 ring-rose-500' : 'border-slate-700' }} text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all"
                    >
                        <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>Active Customer</option>
                        <option value="lead" {{ old('status') === 'lead' ? 'selected' : '' }}>Lead / Opportunity</option>
                        <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                    @error('status')
                        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Address (Full Width) -->
            <div>
                <label for="address" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                    Physical / Mailing Address
                </label>
                <textarea 
                    name="address" 
                    id="address" 
                    rows="3" 
                    placeholder="Suite 500, 100 Market St..."
                    class="w-full px-3.5 py-2.5 rounded-lg bg-slate-800/80 border {{ $errors->has('address') ? 'border-rose-500 ring-1 ring-rose-500' : 'border-slate-700' }} text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all placeholder-slate-500"
                >{{ old('address') }}</textarea>
                @error('address')
                    <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            <!-- Action Buttons -->
            <div class="pt-4 border-t border-slate-800 flex items-center justify-end gap-3">
                <a 
                    href="{{ route('customers.index') }}" 
                    class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-semibold border border-slate-700 transition-colors"
                >
                    Cancel
                </a>
                <button 
                    type="submit" 
                    class="px-5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold shadow-lg shadow-indigo-600/30 transition-all"
                >
                    Create Customer
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
