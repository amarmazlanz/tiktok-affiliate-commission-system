@extends('layouts.auth')

@section('title', 'Admin Dashboard')

@section('content')
    <main class="min-h-screen bg-slate-100">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
                <div>
                    <p class="text-sm font-medium text-emerald-700">Admin</p>
                    <h1 class="text-xl font-semibold text-slate-950">Dashboard</h1>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Logout
                    </button>
                </form>
            </div>
        </header>

        <section class="mx-auto max-w-6xl px-4 py-8">
            @if (session('error'))
                <div class="mb-6 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    {{ session('error') }}
                </div>
            @endif

            <div class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <h2 class="text-lg font-semibold text-slate-950">Selamat datang, {{ auth()->user()->name }}</h2>
                <p class="mt-2 text-sm text-slate-600">Ruang admin untuk pengurusan sistem komisyen affiliate TikTok.</p>

                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="{{ route('admin.affiliates.index') }}" class="inline-flex rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                        Affiliate Management
                    </a>
                    <a href="{{ route('admin.orders.upload') }}" class="inline-flex rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        CSV Upload
                    </a>
                    <a href="{{ route('admin.commissions.index') }}" class="inline-flex rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Commission Calculation
                    </a>
                </div>
            </div>
        </section>
    </main>
@endsection
