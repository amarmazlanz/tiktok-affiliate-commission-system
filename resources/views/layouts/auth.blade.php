<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'TikTok Affiliate Commission System')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .app-shell main > header:first-child { display: none; }
        .btn-primary { display: inline-flex; align-items: center; justify-content: center; gap: .5rem; border-radius: 0.5rem; background: #047857; padding: 0.625rem 1rem; font-size: 0.875rem; font-weight: 700; color: #fff; box-shadow: 0 1px 2px rgb(15 23 42 / .08); transition: background-color .15s ease, box-shadow .15s ease, transform .15s ease; }
        .btn-primary:hover { background: #065f46; box-shadow: 0 6px 16px rgb(4 120 87 / .16); transform: translateY(-1px); }
        .btn-secondary { display: inline-flex; align-items: center; justify-content: center; gap: .5rem; border-radius: 0.5rem; border: 1px solid #cbd5e1; background: #fff; padding: 0.625rem 1rem; font-size: 0.875rem; font-weight: 700; color: #334155; box-shadow: 0 1px 2px rgb(15 23 42 / .04); transition: background-color .15s ease, border-color .15s ease, color .15s ease; }
        .btn-secondary:hover { border-color: #94a3b8; background: #f8fafc; color: #0f172a; }
        .btn-nav-active { border-color: #a7f3d0; background: #ecfdf5; color: #047857; }
        .btn-danger { display: inline-flex; align-items: center; justify-content: center; border-radius: 0.5rem; border: 1px solid #fecaca; background: #fff; padding: 0.45rem 0.8rem; font-size: 0.75rem; font-weight: 700; color: #b91c1c; transition: background-color .15s ease, border-color .15s ease; }
        .btn-danger:hover { border-color: #fca5a5; background: #fef2f2; }
        .app-card { border-radius: 0.75rem; background: #fff; box-shadow: 0 1px 2px rgb(15 23 42 / .05); outline: 1px solid #e2e8f0; }
        .stat-card { min-height: 8rem; border-radius: 0.75rem; background: #fff; padding: 1.25rem; box-shadow: 0 1px 2px rgb(15 23 42 / .05); outline: 1px solid #e2e8f0; }
        .stat-label { font-size: .75rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: #64748b; }
        .stat-value { margin-top: .6rem; font-size: 1.65rem; line-height: 2rem; font-weight: 800; color: #0f172a; }
        .stat-value-money { color: #047857; }
        .app-table th { background: #f8fafc; color: #334155; font-weight: 800; white-space: nowrap; }
        .app-table th, .app-table td { padding: 0.95rem 1.25rem; }
        .app-table tbody tr { transition: background-color .15s ease; }
        .app-table tbody tr:hover { background: #f8fafc; }
        .badge { display: inline-flex; align-items: center; border-radius: 9999px; padding: .25rem .65rem; font-size: .75rem; font-weight: 800; }
        .badge-green { background: #ecfdf5; color: #047857; }
        .badge-blue { background: #eff6ff; color: #1d4ed8; }
        .badge-purple { background: #faf5ff; color: #7e22ce; }
        .badge-amber { background: #fffbeb; color: #b45309; }
        .badge-teal { background: #f0fdfa; color: #0f766e; }
        .badge-gray { background: #f1f5f9; color: #475569; }
        .badge-red { background: #fef2f2; color: #b91c1c; }
        .money { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .form-field { margin-top: 0.5rem; display: block; width: 100%; border-radius: 0.5rem; border: 1px solid #cbd5e1; padding: 0.625rem 0.8rem; font-size: 0.875rem; box-shadow: 0 1px 2px rgb(15 23 42 / .05); outline: none; }
        .form-field:focus { border-color: #10b981; box-shadow: 0 0 0 3px #d1fae5; }
    </style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    @auth
        <div class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="flex flex-col gap-3 py-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">TikTok Affiliate Commission System</p>
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <h1 class="text-lg font-semibold text-slate-950">@yield('title', 'Dashboard')</h1>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ ucfirst(auth()->user()->role) }}</span>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @if (auth()->user()->role === 'admin')
                            <a href="{{ route('admin.dashboard') }}" class="btn-secondary {{ request()->routeIs('admin.dashboard') ? 'btn-nav-active' : '' }}">Admin Dashboard</a>
                            <a href="{{ route('admin.affiliates.index') }}" class="btn-secondary {{ request()->routeIs('admin.affiliates.*') && ! request()->routeIs('admin.affiliates.import.*') ? 'btn-nav-active' : '' }}">Affiliate Management</a>
                            <a href="{{ route('admin.affiliates.import.create') }}" class="btn-secondary {{ request()->routeIs('admin.affiliates.import.*') ? 'btn-nav-active' : '' }}">Import Affiliates</a>
                            <a href="{{ route('admin.orders.upload') }}" class="btn-secondary {{ request()->routeIs('admin.orders.*') ? 'btn-nav-active' : '' }}">CSV Upload</a>
                            <a href="{{ route('admin.commissions.index') }}" class="btn-secondary {{ request()->routeIs('admin.commissions.*') ? 'btn-nav-active' : '' }}">Commission Runs</a>
                            <a href="{{ route('admin.commission-rate-settings.index') }}" class="btn-secondary {{ request()->routeIs('admin.commission-rate-settings.*') ? 'btn-nav-active' : '' }}">Commission Settings</a>
                        @else
                            <a href="{{ route('affiliate.dashboard') }}" class="btn-secondary {{ request()->routeIs('affiliate.dashboard') ? 'btn-nav-active' : '' }}">Affiliate Dashboard</a>
                        @endif

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn-secondary">Logout</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endauth

    <div class="@auth app-shell @endauth">
        @yield('content')
    </div>
</body>
</html>
