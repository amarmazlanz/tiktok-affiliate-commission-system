@extends('layouts.auth')

@section('title', 'My Commission')

@section('content')
    <main class="min-h-screen bg-slate-100">
        <section class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6">
            <div>
                <p class="text-sm font-bold text-emerald-700">Earnings</p>
                <h2 class="mt-1 text-2xl font-black text-slate-950">My Commission</h2>
                <p class="mt-2 text-sm text-slate-500">Review commission totals and the affiliate sources behind your earnings.</p>
            </div>

            <div class="relative" data-period-region>
                <div class="mb-4 hidden rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700" data-period-error>Unable to load commission data. Please try again.</div>
                <div class="pointer-events-none absolute inset-0 z-20 hidden items-start justify-center rounded-xl bg-white/60 pt-20 backdrop-blur-[1px]" data-period-loading>
                    <div class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 shadow-lg">
                        <span class="h-4 w-4 animate-spin rounded-full border-2 border-emerald-600 border-t-transparent"></span> Loading...
                    </div>
                </div>
                <div data-commission-summary-container>
                    @include('affiliate.partials.commission-summary')
                </div>
            </div>

            <div class="app-card p-5 sm:p-6">
                <p class="stat-label">Simplified Source Summary</p>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-lg bg-slate-50 p-4"><p class="text-sm text-slate-500">Personal</p><p class="mt-1 font-black text-slate-950">Your own eligible sales</p></div>
                    <div class="rounded-lg bg-slate-50 p-4"><p class="text-sm text-slate-500">Manager Bonus</p><p class="mt-1 font-black text-slate-950">Bonus from manager qualification</p></div>
                    <div class="rounded-lg bg-slate-50 p-4"><p class="text-sm text-slate-500">L1 Earnings</p><p class="mt-1 font-black text-slate-950">Direct overriding and split earnings</p></div>
                    <div class="rounded-lg bg-slate-50 p-4"><p class="text-sm text-slate-500">L2 / L3</p><p class="mt-1 font-black text-slate-950">Deeper team overriding earnings</p></div>
                </div>
            </div>

            <div data-commission-breakdown-container>
                @include('affiliate.partials.commission-breakdown')
            </div>
        </section>
    </main>

    @include('affiliate.partials.commission-period-script')
@endsection
