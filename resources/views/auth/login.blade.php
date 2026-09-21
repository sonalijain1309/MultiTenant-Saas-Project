<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — SaaS Platform</title>

    <!-- Google Font: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind CSS (CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="h-full antialiased bg-slate-950 text-slate-100 flex items-center justify-center p-4">

    <div class="w-full max-w-md space-y-6">

        <!-- Platform Brand Header -->
        <div class="text-center space-y-2">
            <div class="inline-flex w-12 h-12 rounded-2xl bg-gradient-to-tr from-indigo-600 to-violet-500 items-center justify-center shadow-lg shadow-indigo-500/30">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-white">SaaS Platform Portal</h1>
            <p class="text-sm text-slate-400">Sign in to your multi-tenant workspace</p>
        </div>

        <!-- Status Message Banner -->
        @if(session('status'))
            <div class="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/25 text-emerald-300 text-sm text-center">
                {{ session('status') }}
            </div>
        @endif

        <!-- Card Container -->
        <div class="p-8 rounded-2xl bg-slate-900/90 border border-slate-800 shadow-2xl backdrop-blur">
            <form method="POST" action="{{ route('login.post') }}" class="space-y-4">
                @csrf

                <!-- Email Input -->
                <div>
                    <label for="email" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Email Address</label>
                    <input 
                        type="email" 
                        name="email" 
                        id="email" 
                        required 
                        value="{{ old('email', 'owner@apex.test') }}"
                        autocomplete="email"
                        class="w-full px-3.5 py-2.5 rounded-lg bg-slate-800/80 border {{ $errors->has('email') ? 'border-rose-500 ring-1 ring-rose-500' : 'border-slate-700' }} text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                        placeholder="you@company.test"
                    >
                    @error('email')
                        <p class="mt-1.5 text-xs text-rose-400 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Password Input -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">Password</label>
                    </div>
                    <input 
                        type="password" 
                        name="password" 
                        id="password" 
                        required 
                        value="password"
                        autocomplete="current-password"
                        class="w-full px-3.5 py-2.5 rounded-lg bg-slate-800/80 border {{ $errors->has('password') ? 'border-rose-500 ring-1 ring-rose-500' : 'border-slate-700' }} text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                        placeholder="••••••••"
                    >
                    @error('password')
                        <p class="mt-1.5 text-xs text-rose-400 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Remember Me Checkbox -->
                <div class="flex items-center justify-between pt-1">
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" name="remember" class="w-4 h-4 rounded bg-slate-800 border-slate-700 text-indigo-600 focus:ring-indigo-500 focus:ring-offset-slate-900">
                        <span class="text-xs text-slate-400">Remember my session</span>
                    </label>
                </div>

                <!-- Submit Button -->
                <div class="pt-2">
                    <button 
                        type="submit" 
                        class="w-full py-2.5 px-4 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm shadow-lg shadow-indigo-600/30 transition-all focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-slate-900"
                    >
                        Sign In to Dashboard
                    </button>
                </div>
            </form>

            <!-- Quick Demo Login Switcher -->
            <div class="mt-6 pt-5 border-t border-slate-800 space-y-2.5">
                <span class="block text-xs font-semibold text-slate-400 uppercase tracking-wider text-center">
                    Quick Demo Switcher
                </span>
                <div class="grid grid-cols-1 gap-1.5 text-xs">
                    <button type="button" onclick="fillDemo('owner@apex.test', 'password')" class="w-full py-1.5 px-2.5 rounded bg-slate-800/80 hover:bg-slate-700 border border-slate-700 text-slate-300 text-left flex items-center justify-between transition-colors">
                        <span class="font-medium text-white">Apex Dynamics Owner</span>
                        <span class="text-indigo-400 text-[11px]">Basic Plan</span>
                    </button>
                    <button type="button" onclick="fillDemo('manager@apex.test', 'password')" class="w-full py-1.5 px-2.5 rounded bg-slate-800/80 hover:bg-slate-700 border border-slate-700 text-slate-300 text-left flex items-center justify-between transition-colors">
                        <span class="font-medium text-white">Apex Dynamics Manager</span>
                        <span class="text-slate-400 text-[11px]">Operational</span>
                    </button>
                    <button type="button" onclick="fillDemo('staff@apex.test', 'password')" class="w-full py-1.5 px-2.5 rounded bg-slate-800/80 hover:bg-slate-700 border border-slate-700 text-slate-300 text-left flex items-center justify-between transition-colors">
                        <span class="font-medium text-white">Apex Dynamics Staff</span>
                        <span class="text-slate-400 text-[11px]">Read-Only/Ops</span>
                    </button>
                    <button type="button" onclick="fillDemo('owner@bluewave.test', 'password')" class="w-full py-1.5 px-2.5 rounded bg-slate-800/80 hover:bg-slate-700 border border-slate-700 text-slate-300 text-left flex items-center justify-between transition-colors">
                        <span class="font-medium text-white">BlueWave Logistics Owner</span>
                        <span class="text-emerald-400 text-[11px]">Free Plan</span>
                    </button>
                    <button type="button" onclick="fillDemo('admin@saas-platform.test', 'password')" class="w-full py-1.5 px-2.5 rounded bg-slate-800/80 hover:bg-slate-700 border border-slate-700 text-slate-300 text-left flex items-center justify-between transition-colors">
                        <span class="font-medium text-white">Super Administrator</span>
                        <span class="text-amber-400 text-[11px]">Platform</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="text-center text-xs text-slate-500">
            Portfolio project demo credentials • Standard password: <code class="text-slate-400 bg-slate-800 px-1 py-0.5 rounded">password</code>
        </div>
    </div>

    <script>
        function fillDemo(email, password) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = password;
        }
    </script>
</body>
</html>
