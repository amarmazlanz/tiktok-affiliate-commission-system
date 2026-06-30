@extends('layouts.auth')

@section('title', 'Dashboard')

@section('content')
    @php
        $statusBadge = fn (?string $status) => $status === 'active' ? 'badge-green' : 'badge-gray';
        $typeLabel = ($affiliate?->affiliate_type ?? 'inhouse') === 'external' ? 'Affiliate Luar' : 'Inhouse';
        $typeBadge = ($affiliate?->affiliate_type ?? 'inhouse') === 'external' ? 'badge-amber' : 'badge-teal';
    @endphp

    <main class="min-h-screen bg-slate-100">
        <section class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6">
            @if (session('success'))
                <div data-success-toast class="fixed right-4 top-24 z-[70] flex max-w-sm items-start gap-3 rounded-lg border border-emerald-200 bg-white px-4 py-3 text-sm font-semibold text-emerald-800 shadow-xl transition duration-200 sm:right-6">
                    <span class="flex-1">{{ session('success') }}</span>
                    <button type="button" data-dismiss-toast class="rounded p-1 text-emerald-600 hover:bg-emerald-50" aria-label="Dismiss notification">&times;</button>
                </div>
            @endif

            @if (! $affiliate)
                <div class="app-card p-6">
                    <h2 class="text-lg font-semibold text-slate-950">Affiliate profile unavailable</h2>
                    <p class="mt-2 text-sm text-slate-600">Your login account is not linked to an affiliate profile.</p>
                </div>
            @else
                <div class="app-card overflow-hidden">
                    <div class="border-b border-slate-200 px-6 py-5">
                        <p class="text-sm font-bold text-emerald-700">Profile Overview</p>
                        <h2 class="mt-1 max-w-4xl break-words text-2xl font-black leading-tight text-slate-950">{{ $affiliate->name }}</h2>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span class="tier-badge {{ $tierData['current']['css'] }}">🏆 {{ $tierData['current']['label'] }}</span>
                            <span class="badge badge-gray">{{ $affiliate->affiliate_code }}</span>
                            <span class="badge badge-blue">{{ $affiliate->group_name ?: 'No Group' }}</span>
                            <span class="badge {{ $teamSummary['direct_count'] > 0 ? 'badge-green' : 'badge-gray' }}">{{ $profileSummary['position'] }}</span>
                            <span class="badge {{ $statusBadge($affiliate->status) }}">{{ ucfirst($affiliate->status) }}</span>
                            <span class="badge {{ $typeBadge }}">{{ $typeLabel }}</span>
                        </div>
                    </div>
                    <div class="px-6 pt-5">
                        @if ($tierData['next'])
                            <div class="flex items-center justify-between text-sm">
                                <p class="font-bold text-slate-700">Progress to {{ $tierData['next']['label'] }}</p>
                                <p class="font-bold text-emerald-700">RM {{ number_format($tierData['amount_to_next'], 2) }} lagi</p>
                            </div>
                            <div class="tier-progress-track mt-2">
                                <div class="tier-progress-fill" style="width: {{ $tierData['progress_percent'] }}%"></div>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">Sales bulan ini: RM {{ number_format($tierData['sales'], 2) }} / RM {{ number_format($tierData['next']['min_sales'], 2) }}</p>
                        @else
                            <div class="rounded-lg border border-violet-200 bg-violet-50 px-4 py-3 text-sm font-bold text-violet-800">
                                🎉 Anda di tahap tertinggi: {{ $tierData['current']['label'] }}! Sales bulan ini: RM {{ number_format($tierData['sales'], 2) }}
                            </div>
                        @endif
                    </div>
                    <div class="grid gap-4 p-6 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <p class="stat-label">Direct Upline</p>
                            <p class="mt-2 break-words text-sm font-bold text-slate-950">{{ $affiliate->upline?->name ?? '-' }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <p class="stat-label">Direct Downlines</p>
                            <p class="mt-2 text-xl font-black text-slate-950">{{ number_format($teamSummary['direct_count']) }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <p class="stat-label">Total Team</p>
                            <p class="mt-2 text-xl font-black text-slate-950">{{ number_format($teamSummary['total_count']) }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <p class="stat-label">Login ID</p>
                            <p class="mt-2 break-words text-sm font-bold text-slate-950">{{ $profileSummary['login_label'] ?: '-' }}</p>
                        </div>
                    </div>
                </div>

                <div class="relative" data-period-region>
                    <div class="mb-4 hidden rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700" data-period-error>Unable to load commission summary. Please try again.</div>
                    <div class="pointer-events-none absolute inset-0 z-20 hidden items-start justify-center rounded-xl bg-white/60 pt-20 backdrop-blur-[1px]" data-period-loading>
                        <div class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 shadow-lg">
                            <span class="h-4 w-4 animate-spin rounded-full border-2 border-emerald-600 border-t-transparent"></span> Loading...
                        </div>
                    </div>
                    <div data-commission-summary-container>
                        @include('affiliate.partials.commission-summary')
                    </div>
                </div>

                <div class="app-card p-5 sm:p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-bold text-emerald-700">Team Summary</p>
                            <h2 class="mt-1 text-lg font-black text-slate-950">Your current downline structure</h2>
                        </div>
                        <a href="{{ route('affiliate.team') }}" class="btn-secondary">View My Team</a>
                    </div>
                    <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-lg bg-slate-50 p-4"><p class="stat-label">Direct</p><p class="mt-1 text-xl font-black">{{ number_format($teamSummary['direct_count']) }}</p></div>
                        <div class="rounded-lg bg-slate-50 p-4"><p class="stat-label">Total Team</p><p class="mt-1 text-xl font-black">{{ number_format($teamSummary['total_count']) }}</p></div>
                        <div class="rounded-lg bg-slate-50 p-4"><p class="stat-label">Level 2</p><p class="mt-1 text-xl font-black">{{ number_format($teamSummary['level_2_count']) }}</p></div>
                        <div class="rounded-lg bg-slate-50 p-4"><p class="stat-label">Level 3+</p><p class="mt-1 text-xl font-black">{{ number_format($teamSummary['level_3_plus_count']) }}</p></div>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    @foreach ([
                        [route('affiliate.commission'), 'View My Commission', 'Review earnings and source breakdown.'],
                        [route('affiliate.team'), 'View My Team', 'Explore your full downline hierarchy.'],
                        [route('affiliate.tiktok-accounts'), 'Manage TikTok Accounts', 'View accounts linked to your profile.'],
                    ] as [$url, $label, $description])
                        <a href="{{ $url }}" class="app-card group p-5 transition hover:-translate-y-0.5 hover:shadow-md">
                            <p class="font-black text-slate-950 group-hover:text-emerald-700">{{ $label }}</p>
                            <p class="mt-2 text-sm text-slate-500">{{ $description }}</p>
                            <span class="mt-4 inline-flex text-sm font-bold text-emerald-700">Open page &rarr;</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    </main>

    @include('affiliate.partials.commission-period-script')
    <script>
        (() => {
            const toast = document.querySelector('[data-success-toast]');
            if (! toast) return;
            const dismiss = () => {
                toast.classList.add('opacity-0', '-translate-y-2');
                window.setTimeout(() => toast.remove(), 200);
            };
            document.querySelector('[data-dismiss-toast]')?.addEventListener('click', dismiss);
            window.setTimeout(dismiss, 4500);
        })();
    </script>
@endsection
