@extends('layouts.auth')

@section('title', 'Affiliate Detail')

@section('content')
    <main class="min-h-screen bg-slate-100">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
                <div>
                    <p class="text-sm font-medium text-emerald-700">Admin</p>
                    <h1 class="text-xl font-semibold text-slate-950">Affiliate Detail</h1>
                </div>

                <div class="flex items-center gap-3">
                    <a href="{{ route('admin.affiliates.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Affiliate List
                    </a>
                    <a href="{{ route('admin.dashboard') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Dashboard
                    </a>
                </div>
            </div>
        </header>

        <section class="mx-auto max-w-6xl space-y-6 px-4 py-8">
            @if (session('success'))
                <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">{{ $affiliate->name }}</h2>
                        <p class="mt-1 text-sm text-slate-600">{{ $affiliate->email }}</p>
                    </div>
                    <a href="{{ route('admin.affiliates.edit', $affiliate) }}" class="inline-flex rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Edit Affiliate
                    </a>
                </div>

                <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-4">
                    <div>
                        <dt class="font-medium text-slate-500">Phone</dt>
                        <dd class="mt-1 text-slate-950">{{ $affiliate->phone ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-slate-500">Status</dt>
                        <dd class="mt-1 text-slate-950">{{ ucfirst($affiliate->status) }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-slate-500">Upline</dt>
                        <dd class="mt-1 text-slate-950">{{ $affiliate->upline?->name ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-slate-500">TikTok Accounts</dt>
                        <dd class="mt-1 text-slate-950">{{ $affiliate->tiktokAccounts->count() }}</dd>
                    </div>
                </dl>
            </div>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
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
                                    <th class="px-4 py-3 text-right font-semibold text-slate-700">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @forelse ($affiliate->tiktokAccounts as $account)
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-slate-950">{{ $account->username }}</td>
                                        <td class="px-4 py-3 text-slate-700">{{ $account->username_normalized }}</td>
                                        <td class="px-4 py-3">
                                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $account->status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                                {{ ucfirst($account->status) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex justify-end">
                                                <form method="POST" action="{{ route('admin.affiliates.tiktok-accounts.destroy', [$affiliate, $account]) }}" onsubmit="return confirm('Deactivate TikTok account ini?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50">
                                                        Deactivate
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-8 text-center text-slate-500">
                                            Belum ada TikTok account.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <h2 class="text-lg font-semibold text-slate-950">Tambah TikTok Account</h2>
                    <form method="POST" action="{{ route('admin.affiliates.tiktok-accounts.store', $affiliate) }}" class="mt-5 space-y-5">
                        @csrf

                        <div>
                            <label for="username" class="block text-sm font-medium text-slate-700">Username</label>
                            <input id="username" name="username" type="text" value="{{ old('username') }}" placeholder="@ali_shop1" required
                                class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                            <p class="mt-1 text-xs text-slate-500">Simbol @ akan dibuang semasa disimpan.</p>
                        </div>

                        <div>
                            <label for="status" class="block text-sm font-medium text-slate-700">Status</label>
                            <select id="status" name="status"
                                class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                                <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                                <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                            </select>
                        </div>

                        <button type="submit" class="w-full rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                            Tambah Account
                        </button>
                    </form>
                </div>
            </div>
        </section>
    </main>
@endsection
