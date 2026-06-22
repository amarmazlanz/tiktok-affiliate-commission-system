@extends('layouts.auth')

@section('title', 'Login')

@section('content')
    <main class="flex min-h-screen items-center justify-center px-4 py-10">
        <section class="w-full max-w-md rounded-lg bg-white p-8 shadow-sm ring-1 ring-slate-200">
            <div class="mb-8">
                <p class="text-sm font-medium text-emerald-700">TikTok Affiliate Commission</p>
                <h1 class="mt-2 text-2xl font-semibold text-slate-950">Login</h1>
            </div>

            @if ($errors->any())
                <div class="mb-5 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-5 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    {{ session('error') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="login" class="block text-sm font-medium text-slate-700">Email or Affiliate Code</label>
                    <input id="login" name="login" type="text" value="{{ old('login') }}" required autofocus
                        class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                    <p class="mt-1 text-xs text-slate-500">Admin login uses email. Affiliate login uses affiliate code.</p>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
                    <input id="password" name="password" type="password" required
                        class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remember" value="1" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                    Remember me
                </label>

                <button type="submit" class="w-full rounded-md bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-200">
                    Login
                </button>
            </form>
        </section>
    </main>
@endsection
