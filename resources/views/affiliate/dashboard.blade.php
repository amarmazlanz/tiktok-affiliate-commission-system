@extends('layouts.auth')

@section('title', 'Affiliate Dashboard')

@section('content')
    @php
        $money = fn ($value) => 'RM '.number_format((float) $value, 2);
        $statusBadge = fn (?string $status) => $status === 'active' ? 'badge-green' : 'badge-gray';
        $typeLabel = ($affiliate?->affiliate_type ?? 'inhouse') === 'external' ? 'Affiliate Luar' : 'Inhouse';
        $typeBadge = ($affiliate?->affiliate_type ?? 'inhouse') === 'external' ? 'badge-amber' : 'badge-teal';
    @endphp

    <main class="min-h-screen bg-slate-100">
        <section class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6">
            @if (session('error'))
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    {{ session('error') }}
                </div>
            @endif

            @if (! $affiliate)
                <div class="app-card p-6 shadow-sm">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-950">Selamat datang, {{ auth()->user()->name }}</h2>
                            <p class="mt-2 text-sm text-slate-600">Profil affiliate belum dipautkan kepada akaun login ini.</p>
                        </div>
                        <a href="{{ route('affiliate.password.edit') }}" class="btn-secondary">Change Password</a>
                    </div>
                </div>
            @else
                <div class="app-card overflow-hidden">
                    <div class="border-b border-slate-200 bg-white px-6 py-5">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <p class="text-sm font-bold text-emerald-700">Profile Overview</p>
                                <h2 class="mt-1 max-w-4xl break-words text-2xl font-black leading-tight text-slate-950">{{ $affiliate->name }}</h2>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <span class="badge badge-gray">{{ $affiliate->affiliate_code }}</span>
                                    <span class="badge badge-blue">{{ $affiliate->group_name ?: 'No Group' }}</span>
                                    <span class="badge {{ $profileSummary['position'] === 'Manager' ? 'badge-green' : 'badge-gray' }}">{{ $profileSummary['position'] }}</span>
                                    <span class="badge {{ $statusBadge($affiliate->status) }}">{{ ucfirst($affiliate->status) }}</span>
                                    <span class="badge {{ $typeBadge }}">{{ $typeLabel }}</span>
                                </div>
                            </div>
                            <a href="{{ route('affiliate.password.edit') }}" class="btn-primary">Change Password</a>
                        </div>
                    </div>

                    <div class="grid gap-4 p-6 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <p class="stat-label">{{ $profileSummary['login_label_type'] }}</p>
                            <p class="mt-2 break-words text-sm font-bold text-slate-950">{{ $profileSummary['login_label'] ?: '-' }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <p class="stat-label">Direct Upline</p>
                            <p class="mt-2 break-words text-sm font-bold text-slate-950">{{ $affiliate->upline?->name ?? '-' }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <p class="stat-label">Team</p>
                            <p class="mt-2 text-sm font-bold text-slate-950">{{ number_format($profileSummary['direct_downline_count']) }} direct · {{ number_format($profileSummary['total_team_size']) }} total</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <p class="stat-label">TikTok Accounts</p>
                            <p class="mt-2 text-sm font-bold text-slate-950">{{ number_format($profileSummary['tiktok_accounts_count']) }} accounts</p>
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="stat-card">
                        <p class="stat-label">Total Sales</p>
                        <p class="stat-value stat-value-money">{{ $money($personalSales) }}</p>
                    </div>
                    <div class="stat-card">
                        <p class="stat-label">Personal Commission</p>
                        <p class="stat-value stat-value-money">{{ $money($commissionSummary['personal']) }}</p>
                    </div>
                    <div class="stat-card">
                        <p class="stat-label">Manager Bonus</p>
                        <p class="stat-value stat-value-money">{{ $money($commissionSummary['manager_bonus']) }}</p>
                    </div>
                    <div class="stat-card">
                        <p class="stat-label">Total Commission</p>
                        <p class="stat-value stat-value-money">{{ $money($commissionSummary['total']) }}</p>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div class="app-card p-5">
                        <p class="stat-label">L1 Earnings</p>
                        <p class="mt-2 text-xl font-black text-slate-950">{{ $money($commissionSummary['l1_overriding'] + $commissionSummary['l1_split_seller'] + $commissionSummary['l1_split_upline']) }}</p>
                    </div>
                    <div class="app-card p-5">
                        <p class="stat-label">L2 Earnings</p>
                        <p class="mt-2 text-xl font-black text-slate-950">{{ $money($commissionSummary['l2_overriding']) }}</p>
                    </div>
                    <div class="app-card p-5">
                        <p class="stat-label">L3 Earnings</p>
                        <p class="mt-2 text-xl font-black text-slate-950">{{ $money($commissionSummary['l3_overriding']) }}</p>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="app-card overflow-hidden">
                        <div class="border-b border-slate-200 px-6 py-5">
                            <h2 class="text-lg font-semibold text-slate-950">TikTok Accounts</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="app-table min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="text-left">Username</th>
                                        <th class="text-left">Normalized</th>
                                        <th class="text-left">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @forelse ($tiktokAccounts as $account)
                                        <tr>
                                            <td class="whitespace-normal break-words font-medium leading-snug text-slate-950">{{ $account->username }}</td>
                                            <td class="whitespace-normal break-words text-slate-700">{{ $account->username_normalized }}</td>
                                            <td><span class="badge {{ $statusBadge($account->status) }}">{{ ucfirst($account->status) }}</span></td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="px-4 py-8 text-center text-slate-500">Belum ada TikTok account.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="app-card overflow-hidden">
                        <div class="border-b border-slate-200 px-6 py-5">
                            <h2 class="text-lg font-semibold text-slate-950">Direct Downline</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ number_format($profileSummary['direct_downline_count']) }} direct affiliates</p>
                        </div>
                        <div class="max-h-[460px] overflow-y-auto overflow-x-auto">
                            <table class="app-table min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="sticky top-0 z-10 bg-slate-50 shadow-sm">
                                    <tr>
                                        <th class="text-left">Name</th>
                                        <th class="text-left">Affiliate Code</th>
                                        <th class="text-left">Type</th>
                                        <th class="text-left">TikTok Accounts</th>
                                        <th class="text-left">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @forelse ($directDownlines as $downline)
                                        <tr class="hover:bg-slate-50">
                                            <td class="whitespace-normal break-words font-medium leading-snug text-slate-950">{{ $downline->name }}</td>
                                            <td class="whitespace-normal break-words font-mono leading-snug text-slate-700">{{ $downline->affiliate_code ?: '-' }}</td>
                                            <td>
                                                <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold {{ $downline->affiliate_type === 'external' ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700' }}">
                                                    {{ $downline->affiliate_type === 'external' ? 'Affiliate Luar' : 'Inhouse' }}
                                                </span>
                                            </td>
                                            <td class="max-w-xs">
                                                @php
                                                    $accounts = $downline->tiktokAccounts->take(3);
                                                    $moreCount = max(0, $downline->tiktok_accounts_count - 3);
                                                @endphp
                                                @if ($accounts->isEmpty())
                                                    <span class="text-slate-500">-</span>
                                                @else
                                                    <div class="flex flex-wrap gap-1.5">
                                                        @foreach ($accounts as $account)
                                                            <span class="inline-flex max-w-full rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-semibold text-slate-700">
                                                                <span class="break-all">{{ $account->username }}</span>
                                                            </span>
                                                        @endforeach
                                                        @if ($moreCount > 0)
                                                            <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">+{{ $moreCount }} more</span>
                                                        @endif
                                                    </div>
                                                @endif
                                            </td>
                                            <td><span class="badge {{ $statusBadge($downline->status) }}">{{ ucfirst($downline->status) }}</span></td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-4 py-8 text-center text-slate-500">Belum ada direct downline.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="app-card overflow-hidden">
                    <div class="border-b border-slate-200 px-6 py-5">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-950">Commission Breakdown</h2>
                                <p class="mt-1 text-sm text-slate-500">
                                    Showing {{ number_format($commissionEntries->firstItem() ?? 0) }}-{{ number_format($commissionEntries->lastItem() ?? 0) }} of {{ number_format($commissionEntries->total()) }} received entries
                                </p>
                            </div>

                            <form method="GET" action="{{ route('affiliate.dashboard') }}" class="grid w-full gap-3 sm:grid-cols-2 lg:w-auto lg:grid-cols-[180px_220px_160px_auto]" data-commission-filter-form>
                                <div>
                                    <label for="commission_type" class="block text-xs font-bold uppercase tracking-wide text-slate-500">Commission Type</label>
                                    <select id="commission_type" name="commission_type" class="form-field" data-auto-submit-select>
                                        <option value="">All Types</option>
                                        @foreach ($entryTypeLabels as $type => $label)
                                            <option value="{{ $type }}" @selected(($commissionFilters['commission_type'] ?? '') === $type)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label for="source_affiliate" class="block text-xs font-bold uppercase tracking-wide text-slate-500">Source Affiliate</label>
                                    <select id="source_affiliate" name="source_affiliate" class="form-field" data-auto-submit-select>
                                        <option value="">All Sources</option>
                                        @foreach ($commissionSourceOptions as $source)
                                            <option value="{{ $source->id }}" @selected((string) ($commissionFilters['source_affiliate'] ?? '') === (string) $source->id)>
                                                {{ $source->name }}{{ $source->affiliate_code ? ' ('.$source->affiliate_code.')' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label for="commission_period" class="block text-xs font-bold uppercase tracking-wide text-slate-500">Period</label>
                                    <select id="commission_period" name="commission_period" class="form-field" data-auto-submit-select>
                                        <option value="">All Periods</option>
                                        @foreach ($commissionPeriodOptions as $periodOption)
                                            @php
                                                $periodValue = sprintf('%04d-%02d', $periodOption->year, $periodOption->month);
                                                $periodLabel = \Carbon\Carbon::create((int) $periodOption->year, (int) $periodOption->month, 1)->format('F Y');
                                            @endphp
                                            <option value="{{ $periodValue }}" @selected(($commissionFilters['commission_period'] ?? '') === $periodValue)>{{ $periodLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="flex self-end">
                                    <a href="{{ route('affiliate.dashboard') }}" class="btn-secondary">Reset</a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="max-h-[520px] overflow-y-auto overflow-x-auto">
                        <table class="app-table min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="sticky top-0 z-10 bg-slate-50 shadow-sm">
                                <tr>
                                    <th class="text-left">Source Affiliate</th>
                                    <th class="text-left">Commission Type</th>
                                    <th class="text-left">Order ID</th>
                                    <th class="text-right">Eligible/Base Amount</th>
                                    <th class="text-right">Rate</th>
                                    <th class="text-right">Commission Earned</th>
                                    <th class="text-left">Date / Period</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @forelse ($commissionEntries as $entry)
                                    <tr class="hover:bg-slate-50">
                                        <td class="whitespace-normal break-words font-medium leading-snug text-slate-950">
                                            {{ $entry->sourceAffiliate?->name ?? '-' }}
                                            @if ($entry->sourceAffiliate?->affiliate_code)
                                                <span class="mt-1 block font-mono text-xs text-slate-500">{{ $entry->sourceAffiliate->affiliate_code }}</span>
                                            @endif
                                        </td>
                                        <td><span class="badge badge-blue">{{ $entryTypeLabels[$entry->commission_type] ?? str($entry->commission_type)->headline() }}</span></td>
                                        <td class="whitespace-nowrap font-mono text-slate-700">{{ $entry->tiktokOrder?->order_id ?? '-' }}</td>
                                        <td class="money text-slate-700">{{ $money($entry->base_amount) }}</td>
                                        <td class="money text-slate-700">{{ number_format((float) $entry->rate * 100, 2) }}%</td>
                                        <td class="money font-bold text-slate-950">{{ $money($entry->commission_amount) }}</td>
                                        <td class="whitespace-nowrap text-slate-700">
                                            @if ($entry->commissionRun)
                                                {{ \Carbon\Carbon::create((int) $entry->commissionRun->year, (int) $entry->commissionRun->month, 1)->format('M Y') }}
                                            @else
                                                {{ $entry->created_at?->format('d/m/Y') ?? '-' }}
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-4 py-8 text-center text-slate-500">No commission entries for the selected filters.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-slate-100 bg-slate-50 px-6 py-4">
                        {{ $commissionEntries->links() }}
                    </div>
                </div>

                <div class="app-card overflow-hidden">
                    <div class="border-b border-slate-200 px-6 py-5">
                        <h2 class="text-lg font-semibold text-slate-950">Recent Orders</h2>
                        <p class="mt-1 text-sm text-slate-500">Latest {{ $recentOrders->count() }} orders</p>
                    </div>
                    <div class="max-h-[460px] overflow-y-auto overflow-x-auto">
                        <table class="app-table min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="sticky top-0 z-10 bg-slate-50 shadow-sm">
                                <tr>
                                    <th class="text-left">Order ID</th>
                                    <th class="text-left">Creator Username</th>
                                    <th class="text-left">Status</th>
                                    <th class="text-right">Estimated Base</th>
                                    <th class="text-left">Time Created</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @forelse ($recentOrders as $order)
                                    <tr class="hover:bg-slate-50">
                                        <td class="whitespace-nowrap font-medium text-slate-950">{{ $order->order_id }}</td>
                                        <td class="whitespace-normal break-words text-slate-700">{{ $order->creator_username }}</td>
                                        <td><span class="badge {{ $order->order_status === 'Settled' ? 'badge-green' : 'badge-gray' }}">{{ $order->order_status }}</span></td>
                                        <td class="money text-slate-700">{{ $money($order->estimated_commission_base) }}</td>
                                        <td class="whitespace-nowrap text-slate-700">{{ $order->time_created?->format('d/m/Y H:i') ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-8 text-center text-slate-500">Belum ada order.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </section>
    </main>

    <script>
        document.querySelectorAll('[data-auto-submit-select]').forEach((select) => {
            select.addEventListener('change', () => {
                const form = select.form;

                if (! form || form.dataset.submitting === '1') {
                    return;
                }

                form.dataset.submitting = '1';
                form.querySelectorAll('[data-auto-submit-select]').forEach((field) => {
                    if (field !== select) {
                        field.disabled = true;
                    }
                });
                form.submit();
            });
        });
    </script>
@endsection
