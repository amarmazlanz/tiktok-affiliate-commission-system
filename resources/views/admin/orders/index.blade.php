@extends('layouts.auth')

@section('title', 'Upload History')

@section('content')
    <main class="min-h-screen bg-slate-100">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
                <div>
                    <p class="text-sm font-medium text-emerald-700">Admin</p>
                    <h1 class="text-xl font-semibold text-slate-950">Upload History</h1>
                </div>
                <a href="{{ route('admin.orders.upload') }}" class="btn-primary">
                    Upload CSV Baru
                </a>
            </div>
        </header>

        <section class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
            @if (session('success'))
                <div class="mb-5 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            <div class="app-card overflow-hidden">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h2 class="text-lg font-semibold text-slate-950">Senarai Upload</h2>
                    <p class="mt-1 text-sm text-slate-500">Setiap baris adalah satu sesi upload CSV. Delete akan padam semua orders dalam sesi tersebut.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="app-table min-w-full divide-y divide-slate-200 text-sm">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Nama Fail</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Tarikh Upload</th>
                                <th class="px-4 py-3 text-right font-semibold text-slate-700">Orders</th>
                                <th class="px-4 py-3 text-right font-semibold text-slate-700">Matched</th>
                                <th class="px-4 py-3 text-right font-semibold text-slate-700">No Upline</th>
                                <th class="px-4 py-3 text-right font-semibold text-slate-700">Total Sales</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Diupload Oleh</th>
                                <th class="px-4 py-3 text-right font-semibold text-slate-700">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($imports as $import)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-slate-900">
                                        {{ $import->file_name }}
                                        <div class="mt-0.5 text-xs text-slate-400">
                                            {{ $import->inserted_orders }} baru · {{ $import->updated_orders }} dikemaskini · {{ $import->skipped_rows }} skip
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">
                                        {{ $import->created_at->format('d M Y') }}
                                        <div class="mt-0.5 text-xs text-slate-400">{{ $import->created_at->format('H:i') }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right text-slate-700">
                                        {{ number_format($import->total_rows) }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-emerald-700">
                                        {{ number_format($import->matched_orders) }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-amber-700">
                                        {{ number_format($import->no_upline_orders) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-medium text-slate-700">
                                        RM {{ number_format((float) $import->total_sales, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">
                                        {{ $import->importedBy?->name ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <form method="POST" action="{{ route('admin.orders.destroy', $import) }}"
                                            onsubmit="return confirm('Padam upload ini?\n\nSemua {{ number_format($import->total_rows) }} orders dalam upload ini akan dipadam dari database. Commission runs yang terjejas perlu dikira semula.\n\nTindakan ini tidak boleh dibatalkan.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-danger">Padam</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-10 text-center text-slate-500">
                                        Tiada upload lagi. <a href="{{ route('admin.orders.upload') }}" class="font-medium text-emerald-700 underline">Upload CSV sekarang</a>.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($imports->hasPages())
                    <div class="border-t border-slate-200 px-6 py-4">
                        {{ $imports->links() }}
                    </div>
                @endif
            </div>
        </section>
    </main>
@endsection
