@extends('layouts.auth')

@section('title', 'Leaderboard')

@section('content')
    <main class="min-h-screen bg-slate-100">
        <section class="mx-auto max-w-4xl space-y-6 px-4 py-8 sm:px-6">

            {{-- Header --}}
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-bold text-emerald-700">Komuniti</p>
                    <h2 class="mt-1 text-2xl font-black text-slate-950">Leaderboard</h2>
                    <p class="mt-1 text-sm text-slate-500">Ranking jualan peribadi ahli untuk tempoh yang dipilih.</p>
                </div>

                <form method="GET" action="{{ route('affiliate.leaderboard') }}" class="flex items-end gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Bulan</label>
                        <select name="month" class="form-field mt-1" onchange="this.form.submit()">
                            @foreach ($months as $num => $name)
                                <option value="{{ $num }}" @selected($num === $selectedMonth)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Tahun</label>
                        <select name="year" class="form-field mt-1" onchange="this.form.submit()">
                            @foreach ($availableYears as $y)
                                <option value="{{ $y }}" @selected($y === $selectedYear)>{{ $y }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </div>

            @if (! $selectedRun)
                <div class="app-card p-8 text-center">
                    <p class="text-3xl">📊</p>
                    <p class="mt-3 font-bold text-slate-950">Tiada data untuk tempoh ini</p>
                    <p class="mt-1 text-sm text-slate-500">Commission run belum dijalankan untuk {{ $months[$selectedMonth] }} {{ $selectedYear }}.</p>
                </div>
            @else
                {{-- Summary bar --}}
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    <div class="stat-card">
                        <p class="stat-label">Tempoh</p>
                        <p class="mt-2 text-base font-black text-slate-950">{{ $months[$selectedMonth] }} {{ $selectedYear }}</p>
                    </div>
                    <div class="stat-card">
                        <p class="stat-label">Peserta</p>
                        <p class="stat-value">{{ number_format($totalParticipants) }}</p>
                    </div>
                    <div class="stat-card col-span-2 sm:col-span-1">
                        <p class="stat-label">Ranking Anda</p>
                        <p class="stat-value">{{ $ownRank ? '#' . number_format($ownRank) : '-' }}</p>
                    </div>
                </div>

                <div class="rounded-xl border border-blue-100 bg-blue-50 px-5 py-3 text-sm text-blue-800">
                    Exact sales hanya dipaparkan untuk akaun anda sendiri. Jualan affiliate lain dirahsiakan untuk privasi.
                </div>

                {{-- Own rank callout (if not in top 10) --}}
                @if ($ownRank && $ownRank > 10)
                    <div class="flex items-center gap-4 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4">
                        <span class="leaderboard-rank leaderboard-rank-other shrink-0 text-sm">#{{ $ownRank }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-black text-slate-950">{{ $ownEntry['name'] }}</p>
                            <p class="text-xs text-slate-500">Ranking awak bulan ini</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-black text-emerald-700">RM {{ number_format($ownEntry['total_sales'], 2) }}</p>
                            <p class="text-xs text-slate-500">jualan peribadi</p>
                        </div>
                    </div>
                @endif

                {{-- Leaderboard table --}}
                @if (count($leaderboard) === 0)
                    <div class="app-card p-8 text-center">
                        <p class="text-slate-500">Tiada data jualan untuk tempoh ini.</p>
                    </div>
                @else
                    <div class="app-card overflow-hidden">
                        <div class="border-b border-slate-200 px-5 py-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Top {{ count($leaderboard) }} Affiliate — {{ $months[$selectedMonth] }} {{ $selectedYear }}</p>
                        </div>
                        <div class="divide-y divide-slate-100">
                            @foreach ($leaderboard as $entry)
                                @php
                                    $isOwn = $entry['affiliate_id'] === auth()->user()->affiliate?->id;
                                    $bar   = $topSales > 0 ? round(($entry['total_sales'] / $topSales) * 100) : 0;
                                    $rankClass = match ($entry['rank']) {
                                        1 => 'leaderboard-rank-1',
                                        2 => 'leaderboard-rank-2',
                                        3 => 'leaderboard-rank-3',
                                        default => 'leaderboard-rank-other',
                                    };
                                    $medalIcon = match ($entry['rank']) {
                                        1 => '🥇',
                                        2 => '🥈',
                                        3 => '🥉',
                                        default => null,
                                    };
                                @endphp
                                <div class="px-5 py-4 {{ $isOwn ? 'bg-emerald-50' : '' }}">
                                    <div class="flex items-center gap-4">
                                        <div class="shrink-0">
                                            @if ($medalIcon)
                                                <span class="text-2xl leading-none">{{ $medalIcon }}</span>
                                            @else
                                                <span class="leaderboard-rank {{ $rankClass }}">#{{ $entry['rank'] }}</span>
                                            @endif
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <p class="truncate text-sm font-black text-slate-950 {{ $isOwn ? 'text-emerald-800' : '' }}">
                                                    {{ $entry['name'] }}
                                                    @if ($isOwn) <span class="text-xs font-bold text-emerald-600">(Anda)</span> @endif
                                                </p>
                                                <span class="badge badge-gray text-xs">{{ $entry['affiliate_code'] }}</span>
                                                <span class="tier-badge {{ $entry['tier']['css'] }} text-xs">{{ $entry['tier']['label'] }}</span>
                                            </div>
                                            <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-slate-200">
                                                <div class="h-full rounded-full {{ $entry['rank'] === 1 ? 'bg-amber-400' : 'bg-emerald-500' }}" style="width: {{ $bar }}%"></div>
                                            </div>
                                        </div>
                                        <div class="shrink-0 text-right">
                                            @if ($isOwn)
                                                <p class="text-sm font-black text-emerald-700">RM {{ number_format($entry['total_sales'], 2) }}</p>
                                                <p class="text-xs text-slate-500">jualan anda</p>
                                            @else
                                                <p class="text-sm font-black text-slate-500">Private</p>
                                                <p class="text-xs text-slate-400">jualan dirahsiakan</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Own rank in top 10 highlight --}}
                @if ($ownRank && $ownRank <= 10)
                    <div class="rounded-xl border border-emerald-300 bg-emerald-50 px-5 py-3 text-sm font-bold text-emerald-800">
                        🎉 Tahniah! Awak berada di ranking #{{ $ownRank }} daripada {{ number_format($totalParticipants) }} peserta bulan ini.
                    </div>
                @elseif (! $ownRank)
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-5 py-3 text-sm text-slate-500">
                        Awak belum mempunyai jualan yang diproses untuk tempoh ini. Teruskan semangat!
                    </div>
                @endif
            @endif

        </section>
    </main>
@endsection
