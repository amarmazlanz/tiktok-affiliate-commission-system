@extends('layouts.auth')

@section('title', 'My Team')

@section('content')
    <main class="min-h-screen bg-slate-100">
        <section class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6">
            <div>
                <p class="text-sm font-bold text-emerald-700">Team Structure</p>
                <h2 class="mt-1 text-2xl font-black text-slate-950">My Team</h2>
                <p class="mt-2 text-sm text-slate-500">Only your own downline branch is visible here.</p>
            </div>

            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div class="stat-card"><p class="stat-label">Direct Downlines</p><p class="stat-value">{{ number_format($teamSummary['direct_count']) }}</p></div>
                <div class="stat-card"><p class="stat-label">Total Team</p><p class="stat-value">{{ number_format($teamSummary['total_count']) }}</p></div>
                <div class="stat-card"><p class="stat-label">Level 2</p><p class="stat-value">{{ number_format($teamSummary['level_2_count']) }}</p></div>
                <div class="stat-card"><p class="stat-label">Level 3+</p><p class="stat-value">{{ number_format($teamSummary['level_3_plus_count']) }}</p></div>
            </div>

            @if ($teamStats !== null)
            <div class="app-card overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4 sm:px-6" style="background: linear-gradient(to right, #f0fdf4, #f8fafc)">
                    <p class="text-xs font-bold uppercase tracking-wide text-emerald-700">Manager View</p>
                    <h2 class="mt-0.5 text-lg font-black text-slate-950">Team Command Centre</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Ringkasan prestasi seluruh team awak —
                        @if ($periodFilters['year'] === 'all')
                            semua masa.
                        @else
                            {{ $months[$periodFilters['month']] ?? 'All Months' }} {{ $periodFilters['year'] }}.
                        @endif
                        Tukar tempoh di bahagian <em>Team Performance</em> bawah.
                    </p>
                </div>

                <div class="p-5 sm:p-6 space-y-6">
                    {{-- 4 overview stat cards --}}
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <div class="rounded-xl border border-emerald-100 bg-emerald-50 p-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-emerald-700">Total Sales Team</p>
                            <p class="mt-2 text-xl font-black text-slate-950">RM {{ number_format($teamStats['total_team_sales'], 2) }}</p>
                        </div>
                        <div class="rounded-xl border border-violet-100 bg-violet-50 p-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-violet-700">Overriding Saya</p>
                            <p class="mt-2 text-xl font-black text-slate-950">RM {{ number_format($teamStats['total_overriding'], 2) }}</p>
                        </div>
                        <div class="rounded-xl border border-blue-100 bg-blue-50 p-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-blue-700">Ahli Aktif</p>
                            <p class="mt-2 text-xl font-black text-slate-950">
                                {{ $teamStats['active_members'] }}
                                <span class="text-sm font-semibold text-slate-400">/ {{ $teamStats['total_members'] }}</span>
                            </p>
                        </div>
                        <div class="rounded-xl border border-amber-100 bg-amber-50 p-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-amber-700">Ahli Tiada Sales</p>
                            <p class="mt-2 text-xl font-black text-slate-950">{{ $teamStats['inactive_members'] }}</p>
                        </div>
                    </div>

                    {{-- Top 5 leaderboard --}}
                    @php $topSale = $teamStats['top5'][0]['total_sales'] ?? 0; @endphp
                    @if ($topSale > 0)
                        <div>
                            <h3 class="mb-3 text-xs font-bold uppercase tracking-wide text-slate-500">Top 5 Performer Tempoh Ini</h3>
                            <div class="space-y-2">
                                @foreach ($teamStats['top5'] as $idx => $row)
                                    @if ($row['total_sales'] > 0)
                                        @php
                                            $rank      = $idx + 1;
                                            $rankClass = match ($rank) { 1 => 'leaderboard-rank-1', 2 => 'leaderboard-rank-2', 3 => 'leaderboard-rank-3', default => 'leaderboard-rank-other' };
                                            $rankIcon  = match ($rank) { 1 => '🥇', 2 => '🥈', 3 => '🥉', default => $rank };
                                            $barWidth  = $topSale > 0 ? min(100, round(($row['total_sales'] / $topSale) * 100)) : 0;
                                        @endphp
                                        <div class="flex items-center gap-3 rounded-lg border border-slate-100 bg-white p-3 shadow-sm">
                                            <span class="leaderboard-rank {{ $rankClass }} shrink-0">{{ $rankIcon }}</span>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-start justify-between gap-2">
                                                    <div class="min-w-0">
                                                        <p class="truncate text-sm font-bold text-slate-950">{{ $row['affiliate']->name }}</p>
                                                        <p class="text-xs text-slate-400">{{ $row['affiliate']->affiliate_code ?: '' }} · {{ $row['level'] }}</p>
                                                    </div>
                                                    <div class="shrink-0 text-right">
                                                        <p class="text-sm font-black text-emerald-700">RM {{ number_format($row['total_sales'], 2) }}</p>
                                                        <p class="text-xs text-slate-400">Komisen: RM {{ number_format($row['personal_commission'], 2) }}</p>
                                                    </div>
                                                </div>
                                                <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                                                    <div class="h-full rounded-full bg-emerald-500 transition-all" style="width: {{ $barWidth }}%"></div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="rounded-lg border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            Tiada rekod jualan team untuk tempoh ini. Tukar bulan/tahun di bawah untuk lihat data lain.
                        </div>
                    @endif
                </div>
            </div>
            @endif

            <div class="app-card overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-slate-950">Team Performance</h2>
                            <p class="mt-1 text-sm text-slate-500">Performance of your own downline only. Sensitive personal data such as full IC is never shown here.</p>
                        </div>
                        @php
                            $numericSorts = ['tiktok_accounts', 'total_sales', 'personal_commission', 'overriding_generated'];
                            $sortUrl = function (string $column) use ($periodFilters, $sortFilters, $numericSorts): string {
                                $isCurrent = $sortFilters['sort'] === $column;
                                $defaultDirection = in_array($column, $numericSorts, true) ? 'desc' : 'asc';
                                $nextDirection = $isCurrent
                                    ? ($sortFilters['direction'] === 'asc' ? 'desc' : 'asc')
                                    : $defaultDirection;

                                return route('affiliate.team', array_filter([
                                    'month' => $periodFilters['month'],
                                    'year' => $periodFilters['year'],
                                    'sort' => $column,
                                    'direction' => $nextDirection,
                                ], fn ($value) => $value !== null && $value !== ''));
                            };
                            $sortIndicator = fn (string $column): string => $sortFilters['sort'] === $column
                                ? ($sortFilters['direction'] === 'asc' ? '↑' : '↓')
                                : '';
                        @endphp
                        <form method="GET" action="{{ route('affiliate.team') }}" class="flex flex-wrap items-end gap-3">
                            @if ($sortFilters['sort'])
                                <input type="hidden" name="sort" value="{{ $sortFilters['sort'] }}">
                                <input type="hidden" name="direction" value="{{ $sortFilters['direction'] }}">
                            @endif
                            @if ($periodFilters['year'] === 'all')
                                <input type="hidden" name="month" value="all">
                            @endif
                            <div>
                                <label for="team-month" class="block text-xs font-bold uppercase text-slate-500">Month</label>
                                <select id="team-month" name="month" class="form-field mt-1 min-w-36" onchange="this.form.submit()" @disabled($periodFilters['year'] === 'all')>
                                    <option value="all" @selected($periodFilters['month'] === 'all')>All Months</option>
                                    @foreach ($months as $value => $label)
                                        <option value="{{ $value }}" @selected($periodFilters['month'] === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="team-year" class="block text-xs font-bold uppercase text-slate-500">Year</label>
                                <select id="team-year" name="year" class="form-field mt-1 min-w-28" onchange="this.form.submit()">
                                    <option value="all" @selected($periodFilters['year'] === 'all')>All Years</option>
                                    @foreach ($availableYears as $year)
                                        <option value="{{ $year }}" @selected($periodFilters['year'] === $year)>{{ $year }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="max-h-[500px] overflow-y-auto overflow-x-auto">
                    <table class="app-table min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="sticky top-0 z-10 bg-slate-50 shadow-sm">
                            <tr>
                                <th class="text-left">
                                    <a href="{{ $sortUrl('name') }}" class="inline-flex cursor-pointer items-center gap-1 hover:text-emerald-700 hover:underline">
                                        Downline Name <span>{{ $sortIndicator('name') }}</span>
                                    </a>
                                </th>
                                <th class="text-left">Affiliate Code</th>
                                <th class="text-left">
                                    <a href="{{ $sortUrl('level') }}" class="inline-flex cursor-pointer items-center gap-1 hover:text-emerald-700 hover:underline">
                                        Level <span>{{ $sortIndicator('level') }}</span>
                                    </a>
                                </th>
                                <th class="text-left">
                                    <a href="{{ $sortUrl('type') }}" class="inline-flex cursor-pointer items-center gap-1 hover:text-emerald-700 hover:underline">
                                        Type <span>{{ $sortIndicator('type') }}</span>
                                    </a>
                                </th>
                                <th class="text-right">
                                    <a href="{{ $sortUrl('tiktok_accounts') }}" class="inline-flex cursor-pointer items-center justify-end gap-1 hover:text-emerald-700 hover:underline">
                                        TikTok Accounts <span>{{ $sortIndicator('tiktok_accounts') }}</span>
                                    </a>
                                </th>
                                <th class="text-right">
                                    <a href="{{ $sortUrl('total_sales') }}" class="inline-flex cursor-pointer items-center justify-end gap-1 hover:text-emerald-700 hover:underline">
                                        Total Sales <span>{{ $sortIndicator('total_sales') }}</span>
                                    </a>
                                </th>
                                <th class="text-right">
                                    <a href="{{ $sortUrl('personal_commission') }}" class="inline-flex cursor-pointer items-center justify-end gap-1 hover:text-emerald-700 hover:underline">
                                        Personal Commission <span>{{ $sortIndicator('personal_commission') }}</span>
                                    </a>
                                </th>
                                <th class="text-right">
                                    <a href="{{ $sortUrl('overriding_generated') }}" class="inline-flex cursor-pointer items-center justify-end gap-1 hover:text-emerald-700 hover:underline">
                                        Overriding Generated <span>{{ $sortIndicator('overriding_generated') }}</span>
                                    </a>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($teamPerformance as $row)
                                <tr>
                                    <td class="whitespace-normal break-words font-semibold text-slate-950">{{ $row['affiliate']->name }}</td>
                                    <td class="font-mono text-slate-700">{{ $row['affiliate']->affiliate_code ?: '-' }}</td>
                                    <td><span class="badge badge-blue">{{ $row['level'] }}</span></td>
                                    <td>
                                        <span class="badge {{ $row['affiliate']->affiliate_type === 'external' ? 'badge-amber' : 'badge-teal' }}">
                                            {{ $row['affiliate']->affiliate_type === 'external' ? 'Affiliate Luar' : 'Inhouse' }}
                                        </span>
                                    </td>
                                    <td class="money">{{ number_format($row['tiktok_account_count']) }}</td>
                                    <td class="money">RM {{ number_format($row['total_sales'], 2) }}</td>
                                    <td class="money">RM {{ number_format($row['personal_commission'], 2) }}</td>
                                    <td class="money">RM {{ number_format($row['overriding_generated'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center text-slate-500">No downline performance data for this period.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="app-card overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div class="min-w-0 flex-1">
                            <label for="team-search" class="block text-xs font-bold uppercase tracking-wide text-slate-500">Search team member</label>
                            <input id="team-search" type="search" placeholder="Name, affiliate code or TikTok username" class="form-field max-w-xl" data-team-search>
                            <p class="mt-2 hidden text-sm font-semibold text-amber-700" data-team-no-match>No matching team member found.</p>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" class="btn-secondary" data-team-expand-all>Expand All</button>
                            <button type="button" class="btn-secondary" data-team-collapse-all>Collapse All</button>
                        </div>
                    </div>
                </div>
                <div class="max-h-[70vh] overflow-y-auto overflow-x-hidden bg-slate-50 p-3 sm:p-5" data-team-tree>
                    @include('affiliate.partials.team-node', ['node' => $teamTree, 'parentId' => '', 'ancestorIds' => ''])
                </div>
            </div>
        </section>
    </main>

    <script>
        (() => {
            const tree = document.querySelector('[data-team-tree]');
            if (! tree) return;
            const nodes = Array.from(tree.querySelectorAll('[data-team-node]'));
            const setExpanded = (id, expanded) => {
                const children = tree.querySelector(`[data-team-children="${id}"]`);
                const toggle = tree.querySelector(`[data-team-toggle="${id}"]`);
                if (! children || ! toggle) return;
                children.classList.toggle('hidden', ! expanded);
                toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            };
            tree.querySelectorAll('[data-team-toggle]').forEach((toggle) => toggle.addEventListener('click', () => {
                const children = tree.querySelector(`[data-team-children="${toggle.dataset.teamToggle}"]`);
                if (children) setExpanded(toggle.dataset.teamToggle, children.classList.contains('hidden'));
            }));
            document.querySelector('[data-team-expand-all]')?.addEventListener('click', () => {
                tree.querySelectorAll('[data-team-children]').forEach((children) => setExpanded(children.dataset.teamChildren, true));
            });
            document.querySelector('[data-team-collapse-all]')?.addEventListener('click', () => {
                tree.querySelectorAll('[data-team-children]').forEach((children) => setExpanded(children.dataset.teamChildren, false));
            });
            document.querySelector('[data-team-search]')?.addEventListener('input', (event) => {
                const term = event.target.value.trim().toLowerCase();
                const visible = new Set();
                let matches = 0;
                nodes.forEach((node) => {
                    const card = node.querySelector(':scope > [data-team-node-card]');
                    card?.classList.remove('ring-2', 'ring-amber-300', 'bg-amber-50');
                    if (term === '' || (node.dataset.search || '').includes(term)) {
                        matches++;
                        visible.add(node.dataset.nodeId);
                        (node.dataset.ancestorIds || '').split(',').filter(Boolean).forEach((id) => {
                            visible.add(id);
                            setExpanded(id, true);
                        });
                        if (term !== '') card?.classList.add('ring-2', 'ring-amber-300', 'bg-amber-50');
                    }
                });
                nodes.forEach((node) => node.classList.toggle('hidden', ! visible.has(node.dataset.nodeId)));
                document.querySelector('[data-team-no-match]')?.classList.toggle('hidden', matches > 0);
                if (term === '') nodes.forEach((node) => node.classList.remove('hidden'));
            });
        })();
    </script>
@endsection
