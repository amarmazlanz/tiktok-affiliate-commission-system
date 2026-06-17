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

        <section class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-xl font-bold text-slate-950">Senarai Affiliate</h2>
                    <p class="mt-1 text-sm text-slate-600">Tambah, edit, dan nyahaktifkan affiliate.</p>
                </div>
                <a href="{{ route('admin.affiliates.create') }}" class="btn-primary">
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

            <div class="app-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="app-table min-w-full divide-y divide-slate-200 text-sm">
                        <thead>
                            <tr>
                                <th class="text-left">Name</th>
                                <th class="text-left">Email</th>
                                <th class="text-left">Phone</th>
                                <th class="text-left">Direct Upline</th>
                                <th class="text-right">Direct Downline</th>
                                <th class="text-left">Position</th>
                                <th class="text-left">Account Status</th>
                                <th class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($affiliates as $affiliate)
                                <tr>
                                    <td class="font-medium text-slate-950">{{ $affiliate->name }}</td>
                                    <td class="text-slate-700">{{ $affiliate->email }}</td>
                                    <td class="text-slate-700">{{ $affiliate->phone ?: '-' }}</td>
                                    <td class="text-slate-700">{{ $affiliate->upline?->name ?: '-' }}</td>
                                    <td class="money text-slate-700">{{ $affiliate->direct_downlines_count }}</td>
                                    <td>
                                        <span class="badge {{ $affiliate->direct_downlines_count > 0 ? 'badge-green' : 'badge-gray' }}">
                                            {{ $affiliate->direct_downlines_count > 0 ? 'Manager' : 'Affiliate' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $affiliate->status === 'active' ? 'badge-green' : 'badge-gray' }}">
                                            {{ ucfirst($affiliate->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('admin.affiliates.show', $affiliate) }}" class="btn-secondary px-3 py-1.5 text-xs">
                                                Detail
                                            </a>
                                            <a href="{{ route('admin.affiliates.edit', $affiliate) }}" class="btn-secondary px-3 py-1.5 text-xs">
                                                Edit
                                            </a>
                                            <form method="POST" action="{{ route('admin.affiliates.destroy', $affiliate) }}" onsubmit="return confirm('Deactivate affiliate ini?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-danger">
                                                    Deactivate
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center text-slate-500">
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
