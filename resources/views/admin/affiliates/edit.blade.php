@extends('layouts.auth')

@section('title', 'Edit Affiliate')

@section('content')
    <main class="min-h-screen bg-slate-100">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-3xl items-center justify-between px-4 py-4">
                <div>
                    <p class="text-sm font-medium text-emerald-700">Admin</p>
                    <h1 class="text-xl font-semibold text-slate-950">Edit Affiliate</h1>
                </div>
                <a href="{{ route('admin.affiliates.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Kembali
                </a>
            </div>
        </header>

        <section class="mx-auto max-w-3xl px-4 py-8">
            <div class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <form method="POST" action="{{ route('admin.affiliates.update', $affiliate) }}" class="space-y-5">
                    @csrf
                    @method('PUT')

                    @include('admin.affiliates.form')

                    <div class="flex justify-end gap-3 pt-2">
                        <a href="{{ route('admin.affiliates.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Cancel
                        </a>
                        <button type="submit" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                            Update Affiliate
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </main>
@endsection
