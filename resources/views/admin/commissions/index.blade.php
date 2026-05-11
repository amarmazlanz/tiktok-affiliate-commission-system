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
                <h2 class="text-lg font-semibold text-slate-950">Run Monthly Calculation</h2>
                <form method="POST" action="{{ route('admin.commissions.store') }}" class="mt-5 grid gap-4 md:grid-cols-[1fr_1fr_auto] md:items-end">
                    @csrf

                    <div>
                        <label for="month" class="block text-sm font-medium text-slate-700">Month</label>
                        <select id="month" name="month" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                            @foreach ($months as $number => $name)
                                <option value="{{ $number }}" @selected((int) old('month', now()->month) === $number)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="year" class="block text-sm font-medium text-slate-700">Year</label>
                        <input id="year" name="year" type="number" min="2020" max="2100" value="{{ old('year', now()->year) }}"
                            class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                    </div>

                    <button type="submit" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                        Run Commission Calculation
                    </button>
                </form>
            </div>

            <div class="rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
                <div class="border-b border-slate-200 px-6 py-4">
                    <h2 class="text-lg font-semibold text-slate-950">Commission Runs</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Month/Year</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Total Sales</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Total Commission</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Calculated At</th>
                                <th class="px-4 py-3 text-right font-semibold text-slate-700">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($runs as $run)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-slate-950">{{ $months[$run->month] }} {{ $run->year }}</td>
                                    <td class="px-4 py-3 text-slate-700">RM {{ number_format((float) $run->total_sales, 2) }}</td>
                                    <td class="px-4 py-3 text-slate-700">RM {{ number_format((float) $run->total_commission, 2) }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ ucfirst($run->status) }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ $run->calculated_at?->format('d/m/Y H:i') ?: '-' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('admin.commissions.show', $run) }}" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
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
