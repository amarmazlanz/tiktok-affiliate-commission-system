@extends('layouts.auth')

@section('title', 'Upload TikTok Orders CSV')

@section('content')
    <main class="min-h-screen bg-slate-100">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-4xl items-center justify-between px-4 py-4">
                <div>
                    <p class="text-sm font-medium text-emerald-700">Admin</p>
                    <h1 class="text-xl font-semibold text-slate-950">Upload TikTok Orders CSV</h1>
                </div>
                <a href="{{ route('admin.dashboard') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Dashboard
                </a>
            </div>
        </header>

        <section class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
            <div class="app-card p-6 sm:p-7">
                <div class="mb-6">
                    <h2 class="text-lg font-semibold text-slate-950">Import TikTok affiliate orders</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Upload a CSV exported from TikTok. Creator Username will be normalized without @ and matched against registered TikTok accounts.
                    </p>
                </div>

                @if ($errors->any())
                    <div class="mb-5 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.orders.import') }}" enctype="multipart/form-data" class="space-y-5">
                    @csrf

                    <div>
                        <label for="csv_file" class="block text-sm font-medium text-slate-700">CSV File</label>
                        <input id="csv_file" name="csv_file" type="file" accept=".csv,text/csv" required
                            class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm file:mr-4 file:rounded-md file:border-0 file:bg-emerald-700 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-emerald-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                        <p class="mt-2 text-xs text-slate-500">Accepted format: .csv. Required columns include Order ID, Creator Username, Order Status, and Est. Commission Base.</p>
                    </div>

                    <div class="rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        Order hanya akan disimpan jika Creator Username match dengan TikTok account registered. Unmatched order akan di-skip dan tidak disimpan.
                    </div>

                    <button type="submit" class="btn-primary">
                        Upload & Import
                    </button>
                </form>
            </div>
        </section>
    </main>
@endsection
