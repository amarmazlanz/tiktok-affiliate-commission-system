@extends('layouts.auth')

@section('title', 'Admin Settings')

@section('content')
    <main class="min-h-screen bg-slate-100">
        <section class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
            <div class="mb-6">
                <p class="text-sm font-medium text-emerald-700">Admin Settings</p>
                <h1 class="text-2xl font-black text-slate-950">Admin Settings</h1>
            </div>

            @if (session('success'))
                <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid gap-6">
                <div class="app-card p-6 sm:p-7">
                    <h2 class="text-lg font-black text-slate-950">Change Password</h2>
                    <p class="mt-1 text-sm text-slate-500">Update the password used to access your own admin account.</p>
                    <form method="POST" action="{{ route('admin.settings.password.update') }}" class="mt-5 space-y-5">
                        @csrf
                        @method('PUT')

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
                            Use at least 8 characters.
                        </div>

                        <button type="submit" class="btn-primary">Save New Password</button>
                    </form>
                </div>

                <div class="app-card overflow-hidden">
                    <div class="border-b border-slate-200 px-6 py-5">
                        <h2 class="text-lg font-black text-slate-950">Admin Accounts</h2>
                        <p class="mt-1 text-sm text-slate-500">Everyone listed here has full admin access to the system.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="app-table min-w-full divide-y divide-slate-200 text-sm">
                            <thead>
                                <tr>
                                    <th class="text-left">Name</th>
                                    <th class="text-left">Email</th>
                                    <th class="text-left">Created</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @foreach ($admins as $admin)
                                    <tr>
                                        <td class="font-bold text-slate-950">{{ $admin->name }}</td>
                                        <td class="text-slate-700">{{ $admin->email }}</td>
                                        <td class="text-slate-600">{{ $admin->created_at?->format('d/m/Y') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="app-card p-6 sm:p-7">
                    <h2 class="text-lg font-black text-slate-950">Add New Admin</h2>
                    <p class="mt-1 text-sm text-slate-500">Create another account with full admin access.</p>
                    <form method="POST" action="{{ route('admin.settings.admins.store') }}" class="mt-5 grid gap-5 sm:grid-cols-2">
                        @csrf

                        <div>
                            <label for="new_admin_name" class="block text-sm font-bold text-slate-700">Full Name</label>
                            <input id="new_admin_name" name="name" type="text" maxlength="255" required value="{{ old('name') }}" class="form-field">
                            @error('name')<p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="new_admin_email" class="block text-sm font-bold text-slate-700">Email</label>
                            <input id="new_admin_email" name="email" type="email" maxlength="255" required value="{{ old('email') }}" class="form-field">
                            @error('email')<p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="new_admin_password" class="block text-sm font-bold text-slate-700">Password</label>
                            <input id="new_admin_password" name="password" type="password" autocomplete="new-password" required class="form-field">
                            @error('password')<p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="new_admin_password_confirmation" class="block text-sm font-bold text-slate-700">Confirm Password</label>
                            <input id="new_admin_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="form-field">
                        </div>

                        <div class="sm:col-span-2">
                            <button type="submit" class="btn-primary">Create Admin Account</button>
                        </div>
                    </form>
                </div>
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
