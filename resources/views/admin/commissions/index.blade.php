@extends('layouts.auth')

@section('title', 'Commission Calculation')

@section('content')
    <main class="min-h-screen bg-slate-100">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
                <div>
                    <p class="text-sm font-medium text-emerald-700">Admin</p>
                    <h1 class="text-xl font-semibold text-slate-950">Commission Calculation</h1>
                </div>
                <a href="{{ route('admin.dashboard') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Dashboard
                </a>
            </div>
        </header>

        <section class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6">
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

            <div class="app-card p-6 sm:p-7">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">Run Monthly Calculation</h2>
                    <p class="mt-1 text-sm text-slate-600">Select a commission period and generate entries using the configured monthly rates.</p>
                </div>
                <form method="POST" action="{{ route('admin.commissions.store') }}" class="mt-5 grid gap-4 md:grid-cols-[1fr_1fr_auto] md:items-end">
                    @csrf

                    <div>
                        <label for="month" class="block text-sm font-medium text-slate-700">Month</label>
                        <select id="month" name="month" class="form-field">
                            @foreach ($months as $number => $name)
                                <option value="{{ $number }}" @selected((int) old('month', now()->month) === $number)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="year" class="block text-sm font-medium text-slate-700">Year</label>
                        <input id="year" name="year" type="number" min="2020" max="2100" value="{{ old('year', now()->year) }}"
                            class="form-field">
                    </div>

                    <button type="submit" class="btn-primary">
                        Run Commission Calculation
                    </button>
                </form>
            </div>

            <div class="app-card overflow-hidden">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h2 class="text-lg font-semibold text-slate-950">Commission Runs</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="app-table min-w-full divide-y divide-slate-200 text-sm">
                        <thead>
                            <tr>
                                <th class="text-left">Month/Year</th>
                                <th class="text-right">Total Sales</th>
                                <th class="text-right">Total Commission</th>
                                <th class="text-left">Status</th>
                                <th class="text-left">Calculated At</th>
                                <th class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($runs as $run)
                                <tr>
                                    <td class="font-medium text-slate-950">{{ $months[$run->month] }} {{ $run->year }}</td>
                                    <td class="money text-slate-700">RM {{ number_format((float) $run->total_sales, 2) }}</td>
                                    <td class="money font-semibold text-emerald-700">RM {{ number_format((float) $run->total_commission, 2) }}</td>
                                    <td>
                                        <span class="badge {{ $run->status === 'completed' ? 'badge-green' : ($run->status === 'processing' ? 'badge-blue' : ($run->status === 'failed' ? 'badge-red' : 'badge-amber')) }}">
                                            {{ ucfirst($run->status) }}
                                        </span>
                                    </td>
                                    <td class="text-slate-700">{{ $run->calculated_at?->format('d/m/Y H:i') ?: '-' }}</td>
                                    <td class="text-right">
                                        <a href="{{ route('admin.commissions.show', $run) }}" class="btn-secondary px-3 py-1.5 text-xs">
                                            View Report
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-slate-500">Belum ada commission run.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $runs->links() }}
        </section>
    </main>
@endsection
