@extends('layouts.auth')

@section('title', 'TikTok Accounts')

@section('content')
    <main class="min-h-screen bg-slate-100">
        <section class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6">
            <div>
                <p class="text-sm font-bold text-emerald-700">Account Links</p>
                <h2 class="mt-1 text-2xl font-black text-slate-950">TikTok Accounts</h2>
                <p class="mt-2 text-sm text-slate-500">Read-only accounts currently linked to {{ $affiliate->name }}.</p>
            </div>
            <div class="app-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="app-table min-w-full divide-y divide-slate-200 text-sm">
                        <thead><tr><th class="text-left">Username</th><th class="text-left">Normalized Username</th><th class="text-left">Linked Affiliate</th><th class="text-left">Status</th><th class="text-left">Added</th></tr></thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($tiktokAccounts as $account)
                                <tr>
                                    <td class="font-bold text-slate-950">{{ $account->username }}</td>
                                    <td class="font-mono text-slate-600">{{ $account->username_normalized }}</td>
                                    <td class="whitespace-normal break-words">{{ $affiliate->name }}</td>
                                    <td><span class="badge {{ $account->status === 'active' ? 'badge-green' : 'badge-gray' }}">{{ ucfirst($account->status) }}</span></td>
                                    <td class="whitespace-nowrap text-slate-600">{{ $account->created_at?->format('d/m/Y') ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-6 py-12 text-center text-slate-500">No TikTok accounts are linked to your profile.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
@endsection
