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

            @if ($application->notes)
                <div class="app-card mt-6 p-6">
                    <h3 class="text-lg font-black text-slate-950">Applicant Notes</h3>
                    <p class="mt-3 whitespace-pre-line break-words text-sm leading-6 text-slate-700">{{ $application->notes }}</p>
                </div>
            @endif
        </section>
    </main>
@endsection
