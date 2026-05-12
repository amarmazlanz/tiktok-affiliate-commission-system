<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'TikTok Affiliate Commission System')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .app-shell main > header:first-child { display: none; }
        .btn-primary { display: inline-flex; align-items: center; justify-content: center; border-radius: 0.375rem; background: #047857; padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 600; color: #fff; transition: background-color .15s ease; }
        .btn-primary:hover { background: #065f46; }
        .btn-secondary { display: inline-flex; align-items: center; justify-content: center; border-radius: 0.375rem; border: 1px solid #cbd5e1; background: #fff; padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 600; color: #334155; transition: background-color .15s ease; }
        .btn-secondary:hover { background: #f8fafc; }
        .btn-danger { display: inline-flex; align-items: center; justify-content: center; border-radius: 0.375rem; border: 1px solid #fecaca; background: #fff; padding: 0.375rem 0.75rem; font-size: 0.75rem; font-weight: 600; color: #b91c1c; transition: background-color .15s ease; }
        .btn-danger:hover { background: #fef2f2; }
        .app-card { border-radius: 0.5rem; background: #fff; box-shadow: 0 1px 2px rgb(15 23 42 / .05); outline: 1px solid #e2e8f0; }
        .app-table th { background: #f8fafc; color: #334155; font-weight: 700; }
        .app-table th, .app-table td { padding: 0.75rem 1rem; }
        .form-field { margin-top: 0.5rem; display: block; width: 100%; border-radius: 0.375rem; border: 1px solid #cbd5e1; padding: 0.5rem 0.75rem; font-size: 0.875rem; box-shadow: 0 1px 2px rgb(15 23 42 / .05); outline: none; }
        .form-field:focus { border-color: #10b981; box-shadow: 0 0 0 3px #d1fae5; }
    </style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    @auth
        <div class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur">
            <div class="mx-auto max-w-6xl px-4">
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
                            <a href="{{ route('admin.dashboard') }}" class="btn-secondary">Admin Dashboard</a>
                            <a href="{{ route('admin.affiliates.index') }}" class="btn-secondary">Affiliate Management</a>
                            <a href="{{ route('admin.orders.upload') }}" class="btn-secondary">CSV Upload</a>
                            <a href="{{ route('admin.commissions.index') }}" class="btn-secondary">Commission Runs</a>
                        @else
                            <a href="{{ route('affiliate.dashboard') }}" class="btn-secondary">Affiliate Dashboard</a>
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
