@extends('layouts.app')

@section('title', 'Edit Customer')
@section('header_title', 'Edit Customer — ' . $customer->name)

@section('content')
<div class="max-w-3xl space-y-6">

    <!-- Breadcrumb & Title -->
    <div>
        <div class="flex items-center gap-2 text-xs text-slate-400 mb-1">
            <a href="{{ route('customers.index') }}" class="hover:text-white transition-colors">Customers</a>
            <span>/</span>
            <a href="{{ route('customers.show', $customer) }}" class="hover:text-white transition-colors truncate max-w-[150px]">{{ $customer->name }}</a>
            <span>/</span>
            <span class="text-indigo-400 font-medium">Edit</span>
        </div>
        <h1 class="text-xl font-bold text-white tracking-tight">Edit Customer Profile</h1>
        <p class="text-xs text-slate-400 mt-0.5">Update contact and operational details for {{ $customer->name }}.</p>
    </div>

    <!-- Edit Form Card -->
    <div class="p-6 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm">
        <form method="POST" action="{{ route('customers.update', $customer) }}" class="space-y-5">
            @csrf
            @method('PUT')

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
                        value="{{ old('name', $customer->name) }}" 
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
                        value="{{ old('email', $customer->email) }}" 
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
                        value="{{ old('phone', $customer->phone) }}" 
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
                        value="{{ old('company_name', $customer->company_name) }}" 
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
                        value="{{ old('city', $customer->city) }}" 
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
                        <option value="active" {{ old('status', $customer->status) === 'active' ? 'selected' : '' }}>Active Customer</option>
                        <option value="lead" {{ old('status', $customer->status) === 'lead' ? 'selected' : '' }}>Lead / Opportunity</option>
                        <option value="inactive" {{ old('status', $customer->status) === 'inactive' ? 'selected' : '' }}>Inactive</option>
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
                    class="w-full px-3.5 py-2.5 rounded-lg bg-slate-800/80 border {{ $errors->has('address') ? 'border-rose-500 ring-1 ring-rose-500' : 'border-slate-700' }} text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all placeholder-slate-500"
                >{{ old('address', $customer->address) }}</textarea>
                @error('address')
                    <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            <!-- Action Buttons -->
            <div class="pt-4 border-t border-slate-800 flex items-center justify-end gap-3">
                <a 
                    href="{{ route('customers.show', $customer) }}" 
                    class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-semibold border border-slate-700 transition-colors"
                >
                    Cancel
                </a>
                <button 
                    type="submit" 
                    class="px-5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold shadow-lg shadow-indigo-600/30 transition-all"
                >
                    Save Changes
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
