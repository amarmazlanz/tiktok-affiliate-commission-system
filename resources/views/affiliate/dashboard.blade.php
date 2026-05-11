@extends('layouts.auth')

@section('title', 'Affiliate Dashboard')

@section('content')
    <main class="min-h-screen bg-slate-100">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
                <div>
                    <p class="text-sm font-medium text-emerald-700">Affiliate</p>
                    <h1 class="text-xl font-semibold text-slate-950">Dashboard</h1>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Logout
                    </button>
                </form>
            </div>
        </header>

        <section class="mx-auto max-w-6xl px-4 py-8">
            @if (session('error'))
                <div class="mb-6 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    {{ session('error') }}
                </div>
            @endif

            @if (! $affiliate)
                <div class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <h2 class="text-lg font-semibold text-slate-950">Selamat datang, {{ auth()->user()->name }}</h2>
                    <p class="mt-2 text-sm text-slate-600">Profil affiliate belum dipautkan kepada akaun login ini.</p>
                </div>
            @else
                <div class="mb-6 rounded-lg bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <h2 class="text-lg font-semibold text-slate-950">Selamat datang, {{ $affiliate->name }}</h2>
                    <p class="mt-2 text-sm text-slate-600">{{ $affiliate->email }}{{ $affiliate->phone ? ' | '.$affiliate->phone : '' }}</p>
                </div>

                <div class="mb-6 grid gap-4 md:grid-cols-3">
                    <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200">
                        <p class="text-xs font-semibold uppercase text-slate-500">Personal Sales</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-950">RM {{ number_format((float) $personalSales, 2) }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200">
                        <p class="text-xs font-semibold uppercase text-slate-500">Total Commission</p>
                        <p class="mt-2 text-2xl font-semibold text-emerald-700">RM {{ number_format((float) $commissionSummary['total'], 2) }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200">
                        <p class="text-xs font-semibold uppercase text-slate-500">Direct Downline</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-950">{{ $directDownlines->count() }}</p>
                    </div>
                </div>

                <div class="mb-6 grid gap-4 md:grid-cols-5">
                    <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200">
                        <p class="text-xs font-semibold uppercase text-slate-500">Personal</p>
                        <p class="mt-2 text-xl font-semibold text-slate-950">RM {{ number_format((float) $commissionSummary['personal'], 2) }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200">
                        <p class="text-xs font-semibold uppercase text-slate-500">Manager Bonus</p>
                        <p class="mt-2 text-xl font-semibold text-slate-950">RM {{ number_format((float) $commissionSummary['manager_bonus'], 2) }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200">
                        <p class="text-xs font-semibold uppercase text-slate-500">Overriding L1</p>
                        <p class="mt-2 text-xl font-semibold text-slate-950">RM {{ number_format((float) $commissionSummary['overriding_l1'], 2) }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200">
                        <p class="text-xs font-semibold uppercase text-slate-500">Overriding L2</p>
                        <p class="mt-2 text-xl font-semibold text-slate-950">RM {{ number_format((float) $commissionSummary['overriding_l2'], 2) }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200">
                        <p class="text-xs font-semibold uppercase text-slate-500">Overriding L3</p>
                        <p class="mt-2 text-xl font-semibold text-slate-950">RM {{ number_format((float) $commissionSummary['overriding_l3'], 2) }}</p>
                    </div>
                </div>

                <div class="mb-6 grid gap-6 lg:grid-cols-2">
                    <div class="rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
                        <div class="border-b border-slate-200 px-6 py-4">
                            <h2 class="text-lg font-semibold text-slate-950">TikTok Accounts</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Username</th>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Normalized</th>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @forelse ($tiktokAccounts as $account)
                                        <tr>
                                            <td class="px-4 py-3 font-medium text-slate-950">{{ $account->username }}</td>
                                            <td class="px-4 py-3 text-slate-700">{{ $account->username_normalized }}</td>
                                            <td class="px-4 py-3 text-slate-700">{{ ucfirst($account->status) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="px-4 py-8 text-center text-slate-500">Belum ada TikTok account.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
                        <div class="border-b border-slate-200 px-6 py-4">
                            <h2 class="text-lg font-semibold text-slate-950">Direct Downline</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Name</th>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Email</th>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-700">TikTok</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @forelse ($directDownlines as $downline)
                                        <tr>
                                            <td class="px-4 py-3 font-medium text-slate-950">{{ $downline->name }}</td>
                                            <td class="px-4 py-3 text-slate-700">{{ $downline->email }}</td>
                                            <td class="px-4 py-3 text-slate-700">{{ ucfirst($downline->status) }}</td>
                                            <td class="px-4 py-3 text-slate-700">{{ $downline->tiktok_accounts_count }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-4 py-8 text-center text-slate-500">Belum ada direct downline.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
                    <div class="border-b border-slate-200 px-6 py-4">
                        <h2 class="text-lg font-semibold text-slate-950">Recent Orders</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Order ID</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Creator Username</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Estimated Base</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Time Created</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @forelse ($recentOrders as $order)
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-slate-950">{{ $order->order_id }}</td>
                                        <td class="px-4 py-3 text-slate-700">{{ $order->creator_username }}</td>
                                        <td class="px-4 py-3 text-slate-700">{{ $order->order_status }}</td>
                                        <td class="px-4 py-3 text-slate-700">RM {{ number_format((float) $order->estimated_commission_base, 2) }}</td>
                                        <td class="px-4 py-3 text-slate-700">{{ $order->time_created?->format('d/m/Y H:i') ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-8 text-center text-slate-500">Belum ada order.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </section>
    </main>
@endsection
