@extends('layouts.auth')

@section('title', 'Affiliate Management')

@section('content')
    <main class="min-h-screen bg-slate-100">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
                <div>
                    <p class="text-sm font-medium text-emerald-700">Admin</p>
                    <h1 class="text-xl font-semibold text-slate-950">Affiliate Management</h1>
                </div>

                <div class="flex items-center gap-3">
                    <a href="{{ route('admin.dashboard') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Dashboard
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <section class="mx-auto max-w-6xl px-4 py-8">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">Senarai Affiliate</h2>
                    <p class="mt-1 text-sm text-slate-600">Tambah, edit, dan nyahaktifkan affiliate.</p>
                </div>
                <a href="{{ route('admin.affiliates.create') }}" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                    Tambah Affiliate
                </a>
            </div>

            @if (session('success'))
                <div class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-6 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    {{ session('error') }}
                </div>
            @endif

            <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Name</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Email</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Phone</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Upline</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Downline</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                                <th class="px-4 py-3 text-right font-semibold text-slate-700">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($affiliates as $affiliate)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-slate-950">{{ $affiliate->name }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ $affiliate->email }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ $affiliate->phone ?: '-' }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ $affiliate->upline?->name ?: '-' }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ $affiliate->direct_downlines_count }}</td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $affiliate->status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                            {{ ucfirst($affiliate->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('admin.affiliates.show', $affiliate) }}" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                                Detail
                                            </a>
                                            <a href="{{ route('admin.affiliates.edit', $affiliate) }}" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                                Edit
                                            </a>
                                            <form method="POST" action="{{ route('admin.affiliates.destroy', $affiliate) }}" onsubmit="return confirm('Deactivate affiliate ini?')">
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
                                    <td colspan="7" class="px-4 py-8 text-center text-slate-500">
                                        Belum ada affiliate.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6">
                {{ $affiliates->links() }}
            </div>
        </section>
    </main>
@endsection
