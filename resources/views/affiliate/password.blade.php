@extends('layouts.auth')

@section('title', 'Account Settings')

@section('content')
    <main class="min-h-screen bg-slate-100">
        <section class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
            <div class="mb-6">
                <div>
                    <p class="text-sm font-medium text-emerald-700">Account Settings</p>
                    <h1 class="text-2xl font-black text-slate-950">Login & Security</h1>
                </div>
            </div>

            @if (session('success'))
                <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('warning'))
                <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800">
                    {{ session('warning') }}
                </div>
            @endif

            <div class="mb-6 grid gap-4 sm:grid-cols-3">
                <div class="app-card p-5"><p class="stat-label">Login ID</p><p class="mt-2 break-words font-black text-slate-950">{{ $loginId ?: '-' }}</p></div>
                <div class="app-card p-5"><p class="stat-label">Login Access</p><p class="mt-2"><span class="badge badge-green">{{ $loginAccessStatus }}</span></p></div>
                <div class="app-card p-5"><p class="stat-label">Affiliate</p><p class="mt-2 break-words font-black text-slate-950">{{ $affiliate?->name ?? auth()->user()->name }}</p></div>
            </div>

            <div class="app-card p-6 sm:p-7">
                <h2 class="text-lg font-black text-slate-950">Change Password</h2>
                <p class="mt-1 text-sm text-slate-500">Update the password used to access your affiliate portal.</p>
                <form method="POST" action="{{ route('affiliate.password.update') }}" class="space-y-5">
                    @csrf

                    @foreach ([
                        'current_password' => 'Current Password',
                        'password' => 'New Password',
                        'password_confirmation' => 'Confirm New Password',
                    ] as $field => $label)
                        <div>
                            <label for="{{ $field }}" class="block text-sm font-bold text-slate-700">{{ $label }}</label>
                            <div class="mt-2 flex overflow-hidden rounded-lg border border-slate-300 bg-white shadow-sm focus-within:border-emerald-500 focus-within:ring-4 focus-within:ring-emerald-100">
                                <input id="{{ $field }}" name="{{ $field }}" type="password" autocomplete="new-password" required
                                    class="min-w-0 flex-1 border-0 px-3 py-2.5 text-sm outline-none">
                                <button type="button" class="border-l border-slate-200 px-3 text-xs font-bold text-slate-500 hover:bg-slate-50 hover:text-slate-900" data-toggle-password="{{ $field }}">
                                    Show
                                </button>
                            </div>
                            @error($field)
                                <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach

                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                        Use at least 8 characters. Your password is stored securely and will not be shown again.
                    </div>

                    <button type="submit" class="btn-primary">Save New Password</button>
                </form>
            </div>
        </section>
    </main>

    <script>
        document.querySelectorAll('[data-toggle-password]').forEach((button) => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.togglePassword);
                const showing = input.type === 'text';

                input.type = showing ? 'password' : 'text';
                button.textContent = showing ? 'Show' : 'Hide';
            });
        });
    </script>
@endsection
