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

            @if (! empty($setupError))
                <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    {{ $setupError }}
                </div>
            @endif

            <div class="app-card p-6 sm:p-7">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">Run Monthly Calculation</h2>
                    <p class="mt-1 text-sm text-slate-600">Select a commission period and generate entries using the configured monthly rates.</p>
                </div>
                <form method="POST" action="{{ route('admin.commissions.store') }}" class="mt-5 space-y-4">
                    @csrf

                    <div class="grid gap-4 md:grid-cols-[1fr_1fr_1fr_auto] md:items-end">
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

                        <div>
                            <label for="report_status" class="block text-sm font-medium text-slate-700">Report Status</label>
                            <select id="report_status" name="report_status" class="form-field">
                                <option value="provisional" @selected(old('report_status', 'provisional') === 'provisional')>Provisional</option>
                                <option value="final" @selected(old('report_status') === 'final')>Final</option>
                            </select>
                        </div>

                        <button type="submit" class="btn-primary">
                            Run Commission Calculation
                        </button>
                    </div>

                    <label class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        <input type="checkbox" name="confirm_final_recalculate" value="1" class="mt-1 rounded border-amber-300 text-emerald-700 focus:ring-emerald-500" @checked(old('confirm_final_recalculate'))>
                        <span>
                            I confirm recalculation if the selected month/year report is already Final.
                            Existing commission entries for that month/year will be replaced.
                        </span>
                    </label>
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
                                    <td class="money text-slate-700">RM {{ number_format((float) ($run->display_total_sales ?? $run->total_sales), 2) }}</td>
                                    <td class="money font-semibold text-emerald-700">RM {{ number_format((float) $run->total_commission, 2) }}</td>
                                    <td>
                                        <span class="badge {{ in_array($run->status, ['completed', 'final'], true) ? 'badge-green' : ($run->status === 'processing' ? 'badge-blue' : ($run->status === 'failed' ? 'badge-red' : 'badge-amber')) }}">
                                            {{ str($run->status)->headline() }}
                                        </span>
                                    </td>
                                    <td class="text-slate-700">{{ $run->calculated_at?->format('d/m/Y H:i') ?: '-' }}</td>
                                    <td class="text-right">
                                        <div class="inline-flex items-center gap-2">
                                            <a href="{{ route('admin.commissions.show', $run) }}" class="btn-secondary px-3 py-1.5 text-xs">
                                                View Report
                                            </a>
                                            <form method="POST" action="{{ route('admin.commissions.destroy', $run) }}"
                                                  data-delete-form
                                                  data-month="{{ $months[$run->month] }} {{ $run->year }}"
                                                  data-status="{{ $run->status }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
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

    <script>
        document.querySelectorAll('[data-delete-form]').forEach((form) => {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                const month = form.dataset.month;
                const status = form.dataset.status;
                const isFinal = status === 'final' || status === 'completed';

                const msg = isFinal
                    ? `⚠️ AMARAN: Commission run "${month}" berstatus FINAL.\n\nSemua ${month} commission entries akan dipadam SEPENUHNYA.\n\nAdakah anda PASTI mahu memadam? Tindakan ini tidak boleh diundur.`
                    : `Padam commission run "${month}" (${status})?\n\nSemua commission entries untuk bulan ini akan dipadam. Anda boleh run semula selepas ini.`;

                if (window.confirm(msg)) {
                    form.submit();
                }
            });
        });
    </script>
@endsection
