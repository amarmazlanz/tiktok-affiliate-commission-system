@extends('layouts.auth')

@section('title', 'Edit Commission Rate')

@section('content')
    <main class="min-h-screen bg-slate-100">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-3xl items-center justify-between px-4 py-4">
                <div>
                    <p class="text-sm font-medium text-emerald-700">Admin</p>
                    <h1 class="text-xl font-semibold text-slate-950">Edit Commission Rate</h1>
                </div>
                <a href="{{ route('admin.commission-rate-settings.index') }}" class="btn-secondary">Back</a>
            </div>
        </header>

        <section class="mx-auto max-w-3xl px-4 py-8">
            <div class="app-card p-6">
                <form method="POST" action="{{ route('admin.commission-rate-settings.update', $setting) }}" class="space-y-5">
                    @csrf
                    @method('PUT')
                    @include('admin.commission-rate-settings.form')

                    <div class="flex justify-end gap-3 pt-2">
                        <a href="{{ route('admin.commission-rate-settings.index') }}" class="btn-secondary">Cancel</a>
                        <button type="submit" class="btn-primary">Update Rate</button>
                    </div>
                </form>
            </div>
        </section>
    </main>
@endsection
