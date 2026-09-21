@extends('layouts.app')

@section('title', 'Customers')
@section('header_title', 'Customer Management')

@section('content')
<div class="space-y-6">

    <!-- Page Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-white tracking-tight">Customer Directory</h1>
            <p class="text-xs text-slate-400 mt-1">
                Showing {{ $customers->total() }} total customer records for this workspace
                @if($customerLimit)
                    <span class="text-indigo-400 font-medium">({{ $totalCustomers }}/{{ $customerLimit }} allocated)</span>
                @endif
            </p>
        </div>

        <div>
            <a 
                href="{{ route('customers.create') }}" 
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-500 shadow-lg shadow-indigo-600/30 transition-all"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                <span>Add Customer</span>
            </a>
        </div>
    </div>

    <!-- Filters & Search Form -->
    <div class="p-4 rounded-xl bg-slate-900 border border-slate-800 shadow-sm">
        <form method="GET" action="{{ route('customers.index') }}" class="flex flex-col sm:flex-row gap-3">
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
                    placeholder="Search by name, email, phone, or company..." 
                    class="w-full pl-9 pr-3 py-2 rounded-lg bg-slate-800/80 border border-slate-700 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                >
            </div>

            <!-- Status Filter -->
            <div class="w-full sm:w-44">
                <select 
                    name="status" 
                    class="w-full px-3 py-2 rounded-lg bg-slate-800/80 border border-slate-700 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                >
                    <option value="">All Statuses</option>
                    <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="lead" {{ $status === 'lead' ? 'selected' : '' }}>Lead</option>
                    <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Inactive</option>
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
                        href="{{ route('customers.index') }}" 
                        class="px-3 py-2 rounded-lg text-xs font-medium text-slate-400 hover:text-white transition-colors"
                    >
                        Clear
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Customers Data Table -->
    <div class="rounded-2xl bg-slate-900 border border-slate-800 shadow-sm overflow-hidden">
        @if($customers->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="text-xs uppercase bg-slate-800/70 text-slate-400 border-b border-slate-800 tracking-wider">
                        <tr>
                            <th class="px-5 py-3.5">Customer / Company</th>
                            <th class="px-5 py-3.5">Contact Details</th>
                            <th class="px-5 py-3.5">City</th>
                            <th class="px-5 py-3.5">Status</th>
                            <th class="px-5 py-3.5">Created Date</th>
                            <th class="px-5 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach($customers as $customer)
                            <tr class="hover:bg-slate-800/40 transition-colors">
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-lg bg-indigo-500/10 text-indigo-400 font-bold flex items-center justify-center border border-indigo-500/20 shrink-0">
                                            {{ strtoupper(substr($customer->name, 0, 1)) }}
                                        </div>
                                        <div>
                                            <a href="{{ route('customers.show', $customer) }}" class="font-semibold text-white hover:text-indigo-400 transition-colors block">
                                                {{ $customer->name }}
                                            </a>
                                            @if($customer->company_name)
                                                <span class="text-xs text-slate-400 block">{{ $customer->company_name }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="text-xs space-y-0.5">
                                        <span class="block text-slate-200 font-mono">{{ $customer->email }}</span>
                                        @if($customer->phone)
                                            <span class="block text-slate-400">{{ $customer->phone }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-xs text-slate-300">
                                    {{ $customer->city ?? '—' }}
                                </td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium 
                                        {{ $customer->status === 'active' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : '' }}
                                        {{ $customer->status === 'lead' ? 'bg-sky-500/10 text-sky-400 border border-sky-500/20' : '' }}
                                        {{ $customer->status === 'inactive' ? 'bg-slate-700/50 text-slate-400 border border-slate-700' : '' }}
                                    ">
                                        {{ ucfirst($customer->status) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-xs text-slate-400">
                                    {{ $customer->created_at?->format('M d, Y') }}
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2 text-xs">
                                        <a 
                                            href="{{ route('customers.show', $customer) }}" 
                                            class="px-2.5 py-1.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700 transition-colors"
                                            title="View Details"
                                        >
                                            View
                                        </a>

                                        @can('update', $customer)
                                            <a 
                                                href="{{ route('customers.edit', $customer) }}" 
                                                class="px-2.5 py-1.5 rounded bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-400 hover:text-indigo-300 border border-indigo-500/30 transition-colors"
                                                title="Edit Customer"
                                            >
                                                Edit
                                            </a>
                                        @endcan

                                        @can('delete', $customer)
                                            <form 
                                                method="POST" 
                                                action="{{ route('customers.destroy', $customer) }}" 
                                                class="inline"
                                                onsubmit="return confirm('Are you sure you want to delete customer [{{ addslashes($customer->name) }}]? This action will soft-delete the record.');"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button 
                                                    type="submit" 
                                                    class="px-2.5 py-1.5 rounded bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 hover:text-rose-300 border border-rose-500/30 transition-colors"
                                                    title="Delete Customer"
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
            @if($customers->hasPages())
                <div class="p-4 border-t border-slate-800 bg-slate-900/50">
                    {{ $customers->links() }}
                </div>
            @endif

        @else
            <!-- Empty State -->
            <div class="p-12 text-center space-y-4">
                <div class="w-12 h-12 rounded-2xl bg-slate-800 flex items-center justify-center mx-auto text-slate-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                <div class="space-y-1">
                    <h3 class="text-base font-semibold text-white">No Customers Found</h3>
                    <p class="text-xs text-slate-400 max-w-sm mx-auto">
                        @if($search || $status)
                            No customer records match your filter criteria. Try clearing your search parameters.
                        @else
                            Start building your client portfolio by adding your first customer record.
                        @endif
                    </p>
                </div>
                <div>
                    @if($search || $status)
                        <a href="{{ route('customers.index') }}" class="inline-flex items-center px-4 py-2 rounded-lg text-xs font-semibold text-slate-300 bg-slate-800 hover:bg-slate-700 border border-slate-700 transition-colors">
                            Clear Filters
                        </a>
                    @else
                        <a href="{{ route('customers.create') }}" class="inline-flex items-center px-4 py-2 rounded-lg text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-500 shadow-md shadow-indigo-600/30 transition-colors">
                            Create First Customer
                        </a>
                    @endif
                </div>
            </div>
        @endif
    </div>

</div>
@endsection
