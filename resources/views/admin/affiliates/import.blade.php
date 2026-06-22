@extends('layouts.auth')

@section('title', 'Import Affiliates')

@section('content')
    <main class="min-h-screen bg-slate-100">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-4xl items-center justify-between px-4 py-4">
                <div>
                    <p class="text-sm font-medium text-emerald-700">Admin</p>
                    <h1 class="text-xl font-semibold text-slate-950">Import Affiliates</h1>
                </div>
                <a href="{{ route('admin.affiliates.index') }}" class="btn-secondary">Affiliate Management</a>
            </div>
        </header>

        <section class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
            <div class="app-card p-6 sm:p-7">
                <div class="mb-6">
                    <h2 class="text-lg font-semibold text-slate-950">Bulk import affiliate accounts</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Upload the management Excel file with group sheets such as Titan Group, Aurora Group, SWG, and Kaizen Group.
                    </p>
                </div>

                @if ($errors->any())
                    <div class="mb-5 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.affiliates.import.store') }}" enctype="multipart/form-data" class="space-y-5">
                    @csrf

                    <div>
                        <label for="csv_file" class="block text-sm font-medium text-slate-700">Affiliate Excel / CSV File</label>
                        <input id="csv_file" name="csv_file" type="file" accept=".xlsx,.csv,text/csv" required
                            class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm file:mr-4 file:rounded-md file:border-0 file:bg-emerald-700 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-emerald-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                        <p class="mt-2 text-xs text-slate-500">
                            Accepted formats: .xlsx or .csv. For Excel files, every sheet will be imported as a separate affiliate group.
                        </p>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                        The importer detects <span class="font-semibold">INHOUSE</span> and <span class="font-semibold">AFFILIATE LUAR</span> sections.
                        Inhouse affiliates receive login access with an affiliate code and temporary password. Affiliate luar profiles do not receive login access.
                    </div>

                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        Expected columns: <span class="font-semibold">Nama Penuh</span>, <span class="font-semibold">User id Tiktok</span>,
                        <span class="font-semibold">Manager (L1)</span>, <span class="font-semibold">Senior Manager (L2)</span>,
                        and <span class="font-semibold">General Manager (L3)</span>. Ambiguous upline names will be marked for mapping instead of guessed.
                    </div>

                    <button type="submit" class="btn-primary">
                        Upload & Import Affiliates
                    </button>
                </form>
            </div>
        </section>
    </main>
@endsection
