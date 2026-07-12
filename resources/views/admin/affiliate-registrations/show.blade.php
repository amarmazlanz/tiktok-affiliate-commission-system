@extends('layouts.auth')

@section('title', 'Registration Details')

@section('content')
    @php
        $statusLabels = [
            'pending' => 'Pending',
            'duplicate_review' => 'Duplicate Review',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
        ];
        $statusBadges = [
            'pending' => 'badge-blue',
            'duplicate_review' => 'badge-amber',
            'approved' => 'badge-green',
            'rejected' => 'badge-red',
        ];
        $detailRows = [
            ['Application Reference', $application->application_reference],
            ['Status', $statusLabels[$application->status] ?? ucfirst(str_replace('_', ' ', $application->status)), 'badge '.($statusBadges[$application->status] ?? 'badge-gray')],
            ['Full Applicant Name', $application->full_name],
            ['Masked IC', $application->masked_nric],
            ['Phone', $application->phone],
            ['Email', $application->email],
            ['TikTok Username', $application->tiktok_username],
            ['Additional TikTok Username', $application->additional_tiktok_username ?: '-'],
            ['Referral Code Used', $application->referral_code],
            ['Invited By', $application->referrer?->name ?: '-'],
            ['Proposed Upline', $application->proposedUpline?->name ?: '-'],
            ['Proposed Group', $application->proposed_group_name ?: '-'],
            ['Submitted At', $application->submitted_at?->format('d M Y, h:i A') ?? '-'],
            ['Consent Timestamp', $application->consent_at?->format('d M Y, h:i A') ?? '-'],
            ['Duplicate Status', ucfirst(str_replace('_', ' ', (string) $application->duplicate_status))],
            ['Reviewed At', $application->reviewed_at?->format('d M Y, h:i A') ?? '-'],
            ['Reviewed By', $application->reviewer?->name ?: '-'],
            ['Rejection Reason', $application->rejection_reason ?: '-'],
        ];
        $duplicateNotes = collect(preg_split('/\r\n|\r|\n/', (string) $application->duplicate_notes))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values();
    @endphp

    <main class="min-h-screen bg-slate-100">
        <section class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-bold text-emerald-700">Admin</p>
                    <h2 class="mt-1 text-2xl font-black text-slate-950">Registration Details</h2>
                    <p class="mt-1 font-mono text-sm font-semibold text-slate-600">{{ $application->application_reference }}</p>
                </div>
                <a href="{{ route('admin.affiliate-registrations.index', request()->query()) }}" class="btn-secondary">
                    Back to Pending Registrations
                </a>
            </div>

            @if (session('success'))
                <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                    {{ session('error') }}
                </div>
            @endif

            @if ($errors->has('approval'))
                <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                    {{ $errors->first('approval') }}
                </div>
            @endif

            @if (session('approved_login'))
                @php($approvedLogin = session('approved_login'))
                <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-5">
                    <p class="text-lg font-black text-emerald-900">Application approved successfully.</p>
                    <div class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                        <div>
                            <p class="text-xs font-bold uppercase text-emerald-700">Affiliate</p>
                            <p class="mt-1 font-black text-slate-950">{{ $approvedLogin['affiliate_name'] }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase text-emerald-700">Affiliate Code</p>
                            <p class="mt-1 font-mono font-black text-slate-950">{{ $approvedLogin['affiliate_code'] }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase text-emerald-700">Login ID</p>
                            <p class="mt-1 font-mono font-black text-slate-950">{{ $approvedLogin['login_id'] }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase text-emerald-700">Temporary Password</p>
                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                <code id="temporary-password" class="rounded bg-white px-3 py-2 font-mono font-black text-slate-950 ring-1 ring-emerald-200">{{ $approvedLogin['temporary_password'] }}</code>
                                <button type="button" class="btn-secondary px-3 py-2 text-xs" onclick="navigator.clipboard?.writeText(document.getElementById('temporary-password').textContent)">
                                    Copy Password
                                </button>
                            </div>
                            <p class="mt-2 text-xs font-semibold text-emerald-800">Copy this password now. It will not be shown again.</p>
                        </div>
                    </div>
                </div>
            @endif

            @if ($application->status === 'duplicate_review')
                <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-5 text-amber-900">
                    <p class="font-black">This application requires administrator review.</p>
                    <p class="mt-1 text-sm">This application contains information that may already exist in the system and requires administrator review.</p>
                    @if ($duplicateNotes->isNotEmpty())
                        <ul class="mt-4 list-disc space-y-1 pl-5 text-sm">
                            @foreach ($duplicateNotes as $note)
                                <li>{{ $note }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            <div class="app-card overflow-hidden">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h3 class="text-lg font-black text-slate-950">Application Information</h3>
                    <p class="mt-1 text-sm text-slate-600">Masked IC is shown for review. Full IC is intentionally not displayed.</p>
                </div>
                <dl class="divide-y divide-slate-100">
                    @foreach ($detailRows as $row)
                        <div class="grid gap-2 px-6 py-4 sm:grid-cols-3">
                            <dt class="text-sm font-bold text-slate-500">{{ $row[0] }}</dt>
                            <dd class="whitespace-normal break-words text-sm font-semibold text-slate-950 sm:col-span-2">
                                @if (isset($row[2]))
                                    <span class="{{ $row[2] }}">{{ $row[1] }}</span>
                                @else
                                    {{ $row[1] }}
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            @if ($application->status === 'approved')
                <div class="app-card mt-6 p-6">
                    <h3 class="text-lg font-black text-slate-950">Approval Result</h3>
                    <div class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                        <div>
                            <p class="text-xs font-bold uppercase text-slate-500">Approved Affiliate</p>
                            @if ($application->approvedAffiliate)
                                <a href="{{ route('admin.affiliates.show', $application->approvedAffiliate) }}" class="mt-1 inline-flex font-bold text-emerald-700 hover:underline">
                                    {{ $application->approvedAffiliate->name }} ({{ $application->approvedAffiliate->affiliate_code }})
                                </a>
                            @else
                                <p class="mt-1 font-semibold text-slate-700">-</p>
                            @endif
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase text-slate-500">Approved Direct Upline</p>
                            <p class="mt-1 font-semibold text-slate-950">{{ $application->approvedUpline?->name ?: '-' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase text-slate-500">Affiliate Type</p>
                            <p class="mt-1 font-semibold text-slate-950">{{ $application->approved_affiliate_type === 'online' ? 'Online' : 'Inhouse' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase text-slate-500">Approved Group</p>
                            <p class="mt-1 font-semibold text-slate-950">{{ $application->approved_group_name ?: '-' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase text-slate-500">Position</p>
                            <p class="mt-1 font-semibold text-slate-950">{{ ucfirst((string) $application->approved_position) ?: '-' }}</p>
                        </div>
                    </div>
                    @if ($application->upline_change_reason)
                        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                            <p class="font-black">Upline change reason</p>
                            <p class="mt-1 whitespace-pre-line">{{ $application->upline_change_reason }}</p>
                        </div>
                    @endif
                    @if ($application->approval_notes)
                        <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                            <p class="font-black text-slate-950">Approval notes</p>
                            <p class="mt-1 whitespace-pre-line">{{ $application->approval_notes }}</p>
                        </div>
                    @endif
                </div>
            @elseif ($application->status === 'rejected')
                <div class="app-card mt-6 p-6">
                    <h3 class="text-lg font-black text-slate-950">Rejection Result</h3>
                    <p class="mt-3 whitespace-pre-line rounded-lg border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-800">{{ $application->rejection_reason }}</p>
                </div>
            @else
                <div class="mt-6 grid gap-6 lg:grid-cols-5">
                    <form method="POST" action="{{ route('admin.affiliate-registrations.approve', $application->application_reference) }}" class="app-card p-6 lg:col-span-3">
                        @csrf
                        <h3 class="text-lg font-black text-slate-950">Approve Application</h3>
                        <p class="mt-1 text-sm text-slate-600">Create affiliate profile, login access, TikTok account, and referral code.</p>

                        <div class="mt-5 grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="affiliate_type" class="block text-xs font-bold uppercase text-slate-500">Affiliate Type</label>
                                <select id="affiliate_type" name="affiliate_type" class="form-field">
                                    <option value="inhouse" @selected(old('affiliate_type', 'inhouse') === 'inhouse')>Inhouse</option>
                                    <option value="online" @selected(old('affiliate_type') === 'online')>Online</option>
                                </select>
                                @error('affiliate_type')<p class="mt-2 text-sm font-semibold text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="group_name" class="block text-xs font-bold uppercase text-slate-500">Group</label>
                                <input id="group_name" name="group_name" list="group-options" value="{{ old('group_name', $application->proposed_group_name ?: $application->referrer?->group_name) }}" class="form-field">
                                <datalist id="group-options">
                                    @foreach ($groups as $group)
                                        <option value="{{ $group }}"></option>
                                    @endforeach
                                </datalist>
                                @error('group_name')<p class="mt-2 text-sm font-semibold text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label for="upline_id" class="block text-xs font-bold uppercase text-slate-500">Direct Upline</label>
                                <select id="upline_id" name="upline_id" class="form-field">
                                    @foreach ($uplines as $upline)
                                        <option value="{{ $upline->id }}" @selected((int) old('upline_id', $application->proposed_upline_id) === (int) $upline->id)>
                                            {{ $upline->name }}{{ $upline->affiliate_code ? ' ('.$upline->affiliate_code.')' : '' }}{{ $upline->group_name ? ' - '.$upline->group_name : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('upline_id')<p class="mt-2 text-sm font-semibold text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label for="upline_change_reason" class="block text-xs font-bold uppercase text-slate-500">Reason for Upline Change</label>
                                <textarea id="upline_change_reason" name="upline_change_reason" rows="3" class="form-field" placeholder="Required only if direct upline is changed">{{ old('upline_change_reason') }}</textarea>
                                @error('upline_change_reason')<p class="mt-2 text-sm font-semibold text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="position" class="block text-xs font-bold uppercase text-slate-500">Position</label>
                                <select id="position" name="position" class="form-field">
                                    <option value="affiliate" @selected(old('position', 'affiliate') === 'affiliate')>Affiliate</option>
                                    <option value="manager" @selected(old('position') === 'manager')>Manager</option>
                                </select>
                                @error('position')<p class="mt-2 text-sm font-semibold text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="status" class="block text-xs font-bold uppercase text-slate-500">Status After Approval</label>
                                <select id="status" name="status" class="form-field">
                                    <option value="active" selected>Active</option>
                                </select>
                                @error('status')<p class="mt-2 text-sm font-semibold text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label for="approval_notes" class="block text-xs font-bold uppercase text-slate-500">Notes</label>
                                <textarea id="approval_notes" name="approval_notes" rows="3" class="form-field" placeholder="Optional internal notes">{{ old('approval_notes') }}</textarea>
                                @error('approval_notes')<p class="mt-2 text-sm font-semibold text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <button type="submit" class="btn-primary mt-5" onclick="return confirm('Approve this application and create affiliate login access?')">
                            Approve Application
                        </button>
                    </form>

                    <form method="POST" action="{{ route('admin.affiliate-registrations.reject', $application->application_reference) }}" class="app-card p-6 lg:col-span-2">
                        @csrf
                        <h3 class="text-lg font-black text-slate-950">Reject Application</h3>
                        <p class="mt-1 text-sm text-slate-600">Rejecting will not create an affiliate, user login, TikTok account, or referral code.</p>

                        <div class="mt-5">
                            <label for="rejection_reason" class="block text-xs font-bold uppercase text-slate-500">Rejection Reason</label>
                            <textarea id="rejection_reason" name="rejection_reason" rows="6" required class="form-field">{{ old('rejection_reason') }}</textarea>
                            @error('rejection_reason')<p class="mt-2 text-sm font-semibold text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <button type="submit" class="mt-5 inline-flex items-center justify-center rounded-lg bg-red-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-red-700" onclick="return confirm('Reject this application?')">
                            Reject Application
                        </button>
                    </form>
                </div>
            @endif

            @if ($application->notes)
                <div class="app-card mt-6 p-6">
                    <h3 class="text-lg font-black text-slate-950">Applicant Notes</h3>
                    <p class="mt-3 whitespace-pre-line break-words text-sm leading-6 text-slate-700">{{ $application->notes }}</p>
                </div>
            @endif
        </section>
    </main>
@endsection
