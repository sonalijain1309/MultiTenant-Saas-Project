@extends('layouts.app')

@section('title', $customer->name)
@section('header_title', 'Customer Profile — ' . $customer->name)

@section('content')
<div class="space-y-6">

    <!-- Breadcrumb & Top Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-400 mb-1">
                <a href="{{ route('customers.index') }}" class="hover:text-white transition-colors">Customers</a>
                <span>/</span>
                <span class="text-indigo-400 font-medium truncate max-w-[200px]">{{ $customer->name }}</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-xl font-bold text-white tracking-tight">{{ $customer->name }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold 
                    {{ $customer->status === 'active' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : '' }}
                    {{ $customer->status === 'lead' ? 'bg-sky-500/10 text-sky-400 border border-sky-500/20' : '' }}
                    {{ $customer->status === 'inactive' ? 'bg-slate-700/50 text-slate-400 border border-slate-700' : '' }}
                ">
                    {{ ucfirst($customer->status) }}
                </span>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a 
                href="{{ route('customers.index') }}" 
                class="px-3.5 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-xs font-semibold border border-slate-700 transition-colors"
            >
                ← Back to List
            </a>

            @can('update', $customer)
                <a 
                    href="{{ route('customers.edit', $customer) }}" 
                    class="px-3.5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-md shadow-indigo-600/30 transition-all flex items-center gap-1.5"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    <span>Edit Profile</span>
                </a>
            @endcan

            @can('delete', $customer)
                <form 
                    method="POST" 
                    action="{{ route('customers.destroy', $customer) }}" 
                    class="inline"
                    onsubmit="return confirm('Are you sure you want to delete customer [{{ addslashes($customer->name) }}]?');"
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

    <!-- 2-Column Overview Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Column 1: Customer Profile Details Card -->
        <div class="lg:col-span-1 p-6 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm space-y-6">
            <div class="flex items-center gap-4 pb-4 border-b border-slate-800">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-indigo-600 to-violet-500 text-white font-extrabold text-2xl flex items-center justify-center shadow-lg shadow-indigo-500/25">
                    {{ strtoupper(substr($customer->name, 0, 1)) }}
                </div>
                <div>
                    <h2 class="text-base font-bold text-white">{{ $customer->name }}</h2>
                    <span class="text-xs text-slate-400 block">{{ $customer->company_name ?: 'Individual Client' }}</span>
                </div>
            </div>

            <!-- Profile Info Fields -->
            <div class="space-y-4 text-xs">
                <div>
                    <span class="text-slate-400 block uppercase tracking-wider text-[11px] mb-1">Email Address</span>
                    <span class="text-slate-200 font-mono text-sm">{{ $customer->email }}</span>
                </div>

                <div>
                    <span class="text-slate-400 block uppercase tracking-wider text-[11px] mb-1">Phone Number</span>
                    <span class="text-slate-200 text-sm">{{ $customer->phone ?: 'Not provided' }}</span>
                </div>

                <div>
                    <span class="text-slate-400 block uppercase tracking-wider text-[11px] mb-1">Company / Organization</span>
                    <span class="text-slate-200 text-sm">{{ $customer->company_name ?: 'None' }}</span>
                </div>

                <div>
                    <span class="text-slate-400 block uppercase tracking-wider text-[11px] mb-1">City</span>
                    <span class="text-slate-200 text-sm">{{ $customer->city ?: 'Not specified' }}</span>
                </div>

                <div>
                    <span class="text-slate-400 block uppercase tracking-wider text-[11px] mb-1">Mailing Address</span>
                    <p class="text-slate-300 leading-relaxed">{{ $customer->address ?: 'No address specified.' }}</p>
                </div>

                <div class="pt-3 border-t border-slate-800 flex items-center justify-between text-slate-400">
                    <span>Client Since:</span>
                    <span class="font-medium text-slate-300">{{ $customer->created_at?->format('M d, Y') }}</span>
                </div>
            </div>
        </div>

        <!-- Column 2: Order History Card -->
        <div class="lg:col-span-2 p-6 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-white tracking-tight">Order History</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Purchases and operational transactions associated with this customer.</p>
                </div>
                <span class="text-xs px-2.5 py-1 rounded bg-slate-800 text-slate-300 border border-slate-700">
                    {{ $customer->orders->count() }} Orders Placed
                </span>
            </div>

            @if($customer->orders->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="uppercase bg-slate-800/60 text-slate-400 border-b border-slate-700">
                            <tr>
                                <th class="px-4 py-3">Order Number</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3 text-right">Order Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @foreach($customer->orders as $order)
                                <tr class="hover:bg-slate-800/40 transition-colors">
                                    <td class="px-4 py-3 font-mono font-semibold text-indigo-400">
                                        {{ $order->order_number }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium 
                                            {{ $order->status->value === 'completed' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : '' }}
                                            {{ $order->status->value === 'processing' ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20' : '' }}
                                            {{ $order->status->value === 'pending' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : '' }}
                                            {{ $order->status->value === 'canceled' ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' : '' }}
                                        ">
                                            {{ $order->status->label() }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-slate-400">
                                        {{ $order->ordered_at?->format('M d, Y') }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-white">
                                        ${{ number_format($order->total_amount, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-8 text-center rounded-xl bg-slate-800/40 border border-dashed border-slate-700 space-y-2">
                    <p class="text-xs text-slate-400">No orders have been recorded for this customer yet.</p>
                </div>
            @endif
        </div>

    </div>

</div>
@endsection
