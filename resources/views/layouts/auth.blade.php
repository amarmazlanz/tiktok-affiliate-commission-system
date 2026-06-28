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
        @php
            $isAdmin = auth()->user()->role === 'admin';
            $icons = [
                'dashboard' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 13h6V4H4v9Z"/><path d="M14 20h6v-9h-6v9Z"/><path d="M4 20h6v-3H4v3Z"/><path d="M14 7h6V4h-6v3Z"/></svg>',
                'users' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="9.5" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
                'registrations' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 11h6"/><path d="M9 15h4"/><path d="M8 3h8l2 2h2a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h2l2-2Z"/><path d="M8 3v4h8V3"/></svg>',
                'upload' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/></svg>',
                'report' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 2h8l4 4v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h2Z"/><path d="M14 2v6h6"/><path d="M8 13h8"/><path d="M8 17h5"/></svg>',
                'home' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m3 11 9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M9 20v-6h6v6"/></svg>',
                'wallet' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h15a2 2 0 0 1 2 2v10H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h12"/><path d="M16 11h5v4h-5a2 2 0 0 1 0-4Z"/></svg>',
                'team' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="7" r="3"/><circle cx="17" cy="9" r="2"/><path d="M3 20v-2a5 5 0 0 1 10 0v2"/><path d="M14 20v-1a4 4 0 0 1 7 0v1"/></svg>',
                'invite' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M15 19c0-3-2-5-5-5s-5 2-5 5"/><circle cx="10" cy="7" r="4"/><path d="M19 8v6"/><path d="M16 11h6"/></svg>',
                'tiktok' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 4v11a4 4 0 1 1-4-4"/><path d="M14 4c1 3 3 5 6 5"/></svg>',
                'settings' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-1.6v-.2h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"/></svg>',
            ];
            $menuItems = $isAdmin
                ? [
                    ['label' => 'Admin Dashboard', 'route' => route('admin.dashboard'), 'active' => request()->routeIs('admin.dashboard'), 'icon' => 'dashboard'],
                    ['label' => 'Affiliate Management', 'route' => route('admin.affiliates.index'), 'active' => request()->routeIs('admin.affiliates.*'), 'icon' => 'users'],
                    ['label' => 'Pending Registrations', 'route' => route('admin.affiliate-registrations.index'), 'active' => request()->routeIs('admin.affiliate-registrations.*'), 'icon' => 'registrations'],
                    ['label' => 'CSV Upload', 'route' => route('admin.orders.upload'), 'active' => request()->routeIs('admin.orders.*'), 'icon' => 'upload'],
                    ['label' => 'Commission Runs', 'route' => route('admin.commissions.index'), 'active' => request()->routeIs('admin.commissions.*'), 'icon' => 'report'],
                ]
                : [
                    ['label' => 'Dashboard', 'route' => route('affiliate.dashboard'), 'active' => request()->routeIs('affiliate.dashboard'), 'icon' => 'home'],
                    ['label' => 'My Commission', 'route' => route('affiliate.commission'), 'active' => request()->routeIs('affiliate.commission'), 'icon' => 'wallet'],
                    ['label' => 'My Team', 'route' => route('affiliate.team'), 'active' => request()->routeIs('affiliate.team'), 'icon' => 'team'],
                    ['label' => 'Invite Affiliate', 'route' => route('affiliate.invite'), 'active' => request()->routeIs('affiliate.invite'), 'icon' => 'invite'],
                    ['label' => 'TikTok Accounts', 'route' => route('affiliate.tiktok-accounts'), 'active' => request()->routeIs('affiliate.tiktok-accounts'), 'icon' => 'tiktok'],
                    ['label' => 'Account Settings', 'route' => route('affiliate.settings'), 'active' => request()->routeIs('affiliate.settings', 'affiliate.password.*'), 'icon' => 'settings'],
                ];
        @endphp

        <aside class="fixed inset-y-0 left-0 z-50 hidden w-72 border-r border-slate-200 bg-white lg:flex lg:flex-col">
                <div class="flex h-20 items-center gap-3 border-b border-slate-200 px-6">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-700 text-lg font-black text-white shadow-sm">
                        TT
                    </div>
                    <div>
                        <p class="text-sm font-black leading-5 text-slate-950">TikTok Affiliate</p>
                        <p class="text-xs font-semibold text-slate-500">Commission System</p>
                    </div>
                </div>

                <nav class="flex-1 space-y-1 overflow-y-auto px-4 py-6">
                    @foreach ($menuItems as $item)
                        <a href="{{ $item['route'] }}" class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-bold transition {{ $item['active'] ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950' }}">
                            <span class="flex h-9 w-9 items-center justify-center rounded-lg {{ $item['active'] ? 'bg-white text-emerald-700 shadow-sm' : 'bg-slate-100 text-slate-500 group-hover:text-slate-700' }}">
                                {!! $icons[$item['icon']] !!}
                            </span>
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </nav>

                <div class="border-t border-slate-200 p-4">
                    <div class="mb-3 rounded-xl bg-slate-50 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Signed in as</p>
                        <p class="mt-1 truncate text-sm font-bold text-slate-950">{{ auth()->user()->name }}</p>
                        <p class="truncate text-xs text-slate-500">{{ auth()->user()->email }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn-secondary w-full">Logout</button>
                    </form>
                </div>
        </aside>

        <div class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur lg:pl-72">
            <div class="px-4 sm:px-6">
                <div class="flex min-h-20 flex-col gap-4 py-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-center gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">TikTok Affiliate Commission System</p>
                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                <h1 class="text-xl font-black text-slate-950">@yield('title', 'Dashboard')</h1>
                                <span class="badge badge-gray">{{ ucfirst(auth()->user()->role) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="hidden items-center gap-3 lg:flex">
                        <div class="text-right">
                            <p class="text-sm font-bold text-slate-950">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-slate-500">{{ auth()->user()->email }}</p>
                        </div>
                        @if (! $isAdmin)
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="btn-secondary">Logout</button>
                            </form>
                        @endif
                    </div>
                </div>

                <div class="flex gap-2 overflow-x-auto border-t border-slate-100 py-3 lg:hidden">
                    @foreach ($menuItems as $item)
                        <a href="{{ $item['route'] }}" class="inline-flex shrink-0 items-center gap-2 rounded-xl px-3 py-2 text-sm font-bold {{ $item['active'] ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100' : 'bg-white text-slate-600 ring-1 ring-slate-200' }}">
                            {!! $icons[$item['icon']] !!}
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                    <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                        @csrf
                        <button type="submit" class="btn-secondary py-2">Logout</button>
                    </form>
                </div>
            </div>
        </div>
    @endauth

    <div class="@auth app-shell lg:pl-72 @endauth">
        @yield('content')
    </div>
</body>
</html>
