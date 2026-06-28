@extends('layouts.auth')

@section('title', 'Login')

@section('content')
    <main class="relative flex min-h-screen items-center justify-center overflow-hidden bg-slate-950 px-4 py-10">
        <div class="absolute inset-0 bg-cover bg-center opacity-25 blur-2xl scale-125"
            style="background-image: url('{{ asset('images/Role_Vision_Sdn_bhd.jpg') }}');"></div>
        <div class="absolute inset-0 bg-slate-950/70"></div>
        <div class="absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-slate-950/90 to-transparent"></div>

        <section class="relative z-10 w-full max-w-md rounded-3xl border border-white/40 bg-white/95 px-6 py-8 shadow-2xl backdrop-blur sm:px-8 sm:py-10">
            <div class="mb-8 text-center">
                <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center overflow-hidden rounded-full border border-white/70 bg-white shadow-lg">
                    <img src="{{ asset('images/Role_Vision_Sdn_bhd.jpg') }}" alt="Role Vision Sdn Bhd Logo" class="h-full w-full scale-125 object-cover">
                </div>
                <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">TikTok Affiliate Commission System</p>
                <h1 class="mt-3 text-2xl font-black tracking-wide text-slate-950">
                    ROLE VISION <span class="text-rose-600">AFFILIATE</span>
                </h1>
                <p class="mt-2 text-sm font-semibold text-slate-500">Login</p>
            </div>

            @if ($errors->any())
                <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
                    {{ session('error') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="login" class="block text-sm font-bold text-slate-700">Username / Affiliate Code</label>
                    <input id="login" name="login" type="text" value="{{ old('login') }}" required autofocus
                        placeholder="Enter username or affiliate code"
                        class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm shadow-sm outline-none transition placeholder:text-slate-400 focus:border-rose-500 focus:ring-4 focus:ring-rose-100">
                    <p class="mt-1 text-xs text-slate-500">Admin login uses email. Affiliate login uses affiliate code.</p>
                </div>

                <div>
                    <label for="password" class="block text-sm font-bold text-slate-700">Password</label>
                    <input id="password" name="password" type="password" required
                        placeholder="Enter password"
                        class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm shadow-sm outline-none transition placeholder:text-slate-400 focus:border-rose-500 focus:ring-4 focus:ring-rose-100">
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remember" value="1" class="rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                    Remember me
                </label>

                <button type="submit" class="w-full rounded-xl bg-rose-600 px-4 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-rose-700 focus:outline-none focus:ring-4 focus:ring-rose-200">
                    Login
                </button>
            </form>
        </section>
    </main>
@endsection
