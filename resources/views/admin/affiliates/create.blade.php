@extends('layouts.auth')

@section('title', 'Tambah Affiliate')

@section('content')
    <main class="min-h-screen bg-slate-100">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-3xl items-center justify-between px-4 py-4">
                <div>
                    <p class="text-sm font-medium text-emerald-700">Admin</p>
                    <h1 class="text-xl font-semibold text-slate-950">Tambah Affiliate</h1>
                </div>
                <a href="{{ route('admin.affiliates.index') }}" class="btn-secondary">
                    Kembali
                </a>
            </div>
        </header>

        <section class="mx-auto max-w-3xl px-4 py-8">
            <div class="app-card p-6 sm:p-7">
                <form method="POST" action="{{ route('admin.affiliates.store') }}" class="space-y-5">
                    @csrf

                    @include('admin.affiliates.form')

                    <div class="flex justify-end gap-3 pt-2">
                        <a href="{{ route('admin.affiliates.index') }}" class="btn-secondary">
                            Cancel
                        </a>
                        <button type="submit" class="btn-primary">
                            Simpan Affiliate
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </main>
@endsection
