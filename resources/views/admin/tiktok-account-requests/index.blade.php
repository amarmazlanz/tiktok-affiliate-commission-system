@extends('layouts.auth')

@section('title', 'TikTok Account Requests')

@section('content')
    <main class="min-h-screen bg-slate-100">
        <section class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6">
            <div>
                <p class="text-sm font-bold text-emerald-700">Admin</p>
                <h2 class="mt-1 text-2xl font-black text-slate-950">TikTok Account Requests</h2>
                <p class="mt-2 text-sm text-slate-500">Review new TikTok usernames submitted by affiliates before they become active.</p>
            </div>

            @if (session('success'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="app-card overflow-hidden">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h2 class="text-lg font-bold text-slate-950">Pending Requests</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ number_format($pendingRequests->count()) }} request(s) awaiting review.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="app-table min-w-full divide-y divide-slate-200 text-sm">
                        <thead>
                            <tr>
                                <th class="text-left">Affiliate</th>
                                <th class="text-left">Group / Type</th>
                                <th class="text-left">Requested Username</th>
                                <th class="text-left">Submitted</th>
                                <th class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($pendingRequests as $request)
                                <tr>
                                    <td class="whitespace-normal break-words font-semibold text-slate-950">
                                        {{ $request->affiliate->name }}
                                        <span class="mt-1 block font-mono text-xs font-normal text-slate-500">{{ $request->affiliate->affiliate_code ?: '-' }}</span>
                                    </td>
                                    <td class="text-slate-700">
                                        {{ $request->affiliate->group_name ?: '-' }}
                                        <span class="badge {{ $request->affiliate->affiliate_type === 'external' ? 'badge-amber' : 'badge-teal' }} ml-1">
                                            {{ $request->affiliate->affiliate_type === 'external' ? 'Affiliate Luar' : 'Inhouse' }}
                                        </span>
                                    </td>
                                    <td class="font-mono text-slate-950">{{ $request->requested_username }}</td>
                                    <td class="text-slate-600">{{ $request->created_at?->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <div class="flex justify-end gap-2">
                                            <form method="POST" action="{{ route('admin.tiktok-account-requests.approve', $request) }}" onsubmit="return confirm('Approve this TikTok username and activate it for {{ $request->affiliate->name }}?')">
                                                @csrf
                                                <button type="submit" class="btn-primary px-3 py-1.5 text-xs">Approve</button>
                                            </form>
                                            <button type="button" class="btn-danger px-3 py-1.5 text-xs" data-reject-toggle="{{ $request->id }}">Reject</button>
                                        </div>
                                        <form method="POST" action="{{ route('admin.tiktok-account-requests.reject', $request) }}" class="mt-3 hidden space-y-2 rounded-lg border border-red-200 bg-red-50 p-3 text-left" data-reject-form="{{ $request->id }}">
                                            @csrf
                                            <label class="block text-xs font-bold uppercase text-red-700">Rejection Reason</label>
                                            <textarea name="rejection_reason" required maxlength="1000" rows="2" class="form-field" placeholder="Explain why this request is rejected"></textarea>
                                            <button type="submit" class="btn-danger w-full">Confirm Reject</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-slate-500">No pending TikTok account requests.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="app-card overflow-hidden">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h2 class="text-lg font-bold text-slate-950">Recently Reviewed</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="app-table min-w-full divide-y divide-slate-200 text-sm">
                        <thead>
                            <tr>
                                <th class="text-left">Affiliate</th>
                                <th class="text-left">Username</th>
                                <th class="text-left">Status</th>
                                <th class="text-left">Reviewed By</th>
                                <th class="text-left">Reviewed At</th>
                                <th class="text-left">Reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($reviewedRequests as $request)
                                <tr>
                                    <td class="font-semibold text-slate-950">{{ $request->affiliate->name }}</td>
                                    <td class="font-mono text-slate-700">{{ $request->requested_username }}</td>
                                    <td>
                                        <span class="badge {{ $request->status === 'approved' ? 'badge-green' : 'badge-red' }}">
                                            {{ ucfirst($request->status) }}
                                        </span>
                                    </td>
                                    <td class="text-slate-700">{{ $request->reviewer?->name ?: '-' }}</td>
                                    <td class="text-slate-600">{{ $request->reviewed_at?->format('d/m/Y H:i') ?: '-' }}</td>
                                    <td class="whitespace-normal break-words text-slate-600">{{ $request->rejection_reason ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-slate-500">No reviewed requests yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

    <script>
        document.querySelectorAll('[data-reject-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                document.querySelector(`[data-reject-form="${button.dataset.rejectToggle}"]`)?.classList.toggle('hidden');
            });
        });
    </script>
@endsection
