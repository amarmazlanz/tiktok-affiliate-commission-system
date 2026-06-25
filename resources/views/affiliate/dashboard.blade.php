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
            @if (session('success'))
                <div data-success-toast class="fixed right-4 top-24 z-[70] flex max-w-sm items-start gap-3 rounded-lg border border-emerald-200 bg-white px-4 py-3 text-sm font-semibold text-emerald-800 shadow-xl transition duration-200 sm:right-6">
                    <span class="flex-1">{{ session('success') }}</span>
                    <button type="button" data-dismiss-toast class="rounded p-1 text-emerald-600 hover:bg-emerald-50" aria-label="Dismiss notification">&times;</button>
                </div>
            @endif

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

                <div class="relative" data-period-region>
                    <div class="hidden rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700" data-period-error>
                        Unable to load commission summary. Please try again.
                    </div>
                    <div class="pointer-events-none absolute inset-0 z-20 hidden items-start justify-center rounded-xl bg-white/60 pt-20 backdrop-blur-[1px]" data-period-loading>
                        <div class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 shadow-lg">
                            <span class="h-4 w-4 animate-spin rounded-full border-2 border-emerald-600 border-t-transparent"></span>
                            Loading...
                        </div>
                    </div>
                    <div data-commission-summary-container>
                        @include('affiliate.partials.commission-summary')
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-[360px_minmax(0,1fr)]">
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
                        <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
                            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                                <div>
                                    <h2 class="text-lg font-semibold text-slate-950">My Team Hierarchy</h2>
                                    <p class="mt-1 text-sm text-slate-500">Your own downline branch only.</p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" class="btn-secondary px-3 py-2 text-xs" data-team-expand-all>Expand All</button>
                                    <button type="button" class="btn-secondary px-3 py-2 text-xs" data-team-collapse-all>Collapse All</button>
                                </div>
                            </div>

                            <div class="mt-4">
                                <label for="team-search" class="block text-xs font-bold uppercase tracking-wide text-slate-500">Search team member</label>
                                <input id="team-search" type="search" placeholder="Name, affiliate code or TikTok username" class="form-field" data-team-search>
                                <p class="mt-2 hidden text-sm font-semibold text-amber-700" data-team-no-match>No matching team member found.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3 border-b border-slate-200 bg-slate-50 p-4 sm:grid-cols-4">
                            <div><p class="stat-label">Direct</p><p class="mt-1 text-xl font-black text-slate-950">{{ number_format($teamSummary['direct_count']) }}</p></div>
                            <div><p class="stat-label">Total Team</p><p class="mt-1 text-xl font-black text-slate-950">{{ number_format($teamSummary['total_count']) }}</p></div>
                            <div><p class="stat-label">Level 2</p><p class="mt-1 text-xl font-black text-slate-950">{{ number_format($teamSummary['level_2_count']) }}</p></div>
                            <div><p class="stat-label">Level 3+</p><p class="mt-1 text-xl font-black text-slate-950">{{ number_format($teamSummary['level_3_plus_count']) }}</p></div>
                        </div>

                        <div class="max-h-[620px] overflow-y-auto overflow-x-hidden bg-slate-50 p-3 sm:p-5" data-team-tree>
                            @include('affiliate.partials.team-node', [
                                'node' => $teamTree,
                                'parentId' => '',
                                'ancestorIds' => '',
                            ])
                        </div>
                    </div>
                </div>

                <div data-commission-breakdown-container>
                    @include('affiliate.partials.commission-breakdown')
                </div>

                <div class="app-card overflow-hidden">
                    <div class="border-b border-slate-200 px-6 py-5">
                        <h2 class="text-lg font-semibold text-slate-950">Recent Orders</h2>
                        <p class="mt-1 text-sm text-slate-500">Latest {{ $recentOrders->count() }} orders across all periods</p>
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
        (() => {
            const toast = document.querySelector('[data-success-toast]');
            if (! toast) return;

            const dismiss = () => {
                toast.classList.add('opacity-0', 'translate-y-[-0.5rem]');
                window.setTimeout(() => toast.remove(), 200);
            };

            document.querySelector('[data-dismiss-toast]')?.addEventListener('click', dismiss);
            window.setTimeout(dismiss, 4500);
        })();

        (() => {
            const tree = document.querySelector('[data-team-tree]');
            if (! tree) return;

            const nodes = Array.from(tree.querySelectorAll('[data-team-node]'));
            const searchInput = document.querySelector('[data-team-search]');
            const noMatch = document.querySelector('[data-team-no-match]');

            const setExpanded = (nodeId, expanded) => {
                const children = tree.querySelector(`[data-team-children="${nodeId}"]`);
                const toggle = tree.querySelector(`[data-team-toggle="${nodeId}"]`);
                if (! children || ! toggle) return;

                children.classList.toggle('hidden', ! expanded);
                toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                const chevron = toggle.querySelector('[data-team-chevron]');
                chevron?.classList.toggle('-rotate-45', ! expanded);
                chevron?.classList.toggle('rotate-45', expanded);
                chevron?.classList.toggle('-translate-y-0.5', expanded);
            };

            const expandParentChain = (node) => {
                (node.dataset.ancestorIds || '')
                    .split(',')
                    .filter(Boolean)
                    .forEach((id) => setExpanded(id, true));
            };

            tree.querySelectorAll('[data-team-toggle]').forEach((toggle) => {
                toggle.addEventListener('click', () => {
                    const children = tree.querySelector(`[data-team-children="${toggle.dataset.teamToggle}"]`);
                    if (children) setExpanded(toggle.dataset.teamToggle, children.classList.contains('hidden'));
                });
            });

            document.querySelector('[data-team-expand-all]')?.addEventListener('click', () => {
                tree.querySelectorAll('[data-team-children]').forEach((children) => {
                    setExpanded(children.dataset.teamChildren, true);
                });
            });

            document.querySelector('[data-team-collapse-all]')?.addEventListener('click', () => {
                tree.querySelectorAll('[data-team-children]').forEach((children) => {
                    setExpanded(children.dataset.teamChildren, false);
                });
            });

            searchInput?.addEventListener('input', () => {
                const term = searchInput.value.trim().toLowerCase();
                const visibleIds = new Set();
                let matches = 0;

                nodes.forEach((node) => {
                    node.querySelector(':scope > [data-team-node-card]')?.classList.remove('ring-2', 'ring-amber-300', 'bg-amber-50');

                    if (term === '' || (node.dataset.search || '').includes(term)) {
                        matches++;
                        visibleIds.add(node.dataset.nodeId);
                        (node.dataset.ancestorIds || '').split(',').filter(Boolean).forEach((id) => visibleIds.add(id));

                        if (term !== '') {
                            node.querySelector(':scope > [data-team-node-card]')?.classList.add('ring-2', 'ring-amber-300', 'bg-amber-50');
                            expandParentChain(node);
                        }
                    }
                });

                nodes.forEach((node) => node.classList.toggle('hidden', ! visibleIds.has(node.dataset.nodeId)));
                noMatch?.classList.toggle('hidden', matches > 0);

                if (term === '') {
                    nodes.forEach((node) => node.classList.remove('hidden'));
                    tree.querySelectorAll('[data-team-children]').forEach((children) => {
                        const owner = tree.querySelector(`[data-node-id="${children.dataset.teamChildren}"]`);
                        setExpanded(children.dataset.teamChildren, owner?.dataset.depth === '0');
                    });
                }
            });
        })();

        (() => {
            let activeRequest = null;

            const setLoading = (loading) => {
                const loadingPanel = document.querySelector('[data-period-loading]');
                const summary = document.querySelector('[data-commission-summary-container]');
                const breakdown = document.querySelector('[data-commission-breakdown-container]');

                loadingPanel?.classList.toggle('hidden', ! loading);
                loadingPanel?.classList.toggle('flex', loading);
                summary?.classList.toggle('opacity-60', loading);
                breakdown?.classList.toggle('opacity-60', loading);
                document.querySelectorAll('[data-period-select]').forEach((field) => {
                    field.disabled = loading;
                });
            };

            const showError = (show) => {
                document.querySelector('[data-period-error]')?.classList.toggle('hidden', ! show);
            };

            const loadPeriod = async (url, updateHistory = true) => {
                activeRequest?.abort();
                const requestController = new AbortController();
                activeRequest = requestController;
                const requestUrl = new URL(url, window.location.origin);
                requestUrl.searchParams.set('ajax', '1');

                setLoading(true);
                showError(false);

                try {
                    const response = await fetch(requestUrl, {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        signal: requestController.signal,
                    });

                    if (! response.ok) {
                        throw new Error('Unable to load commission summary.');
                    }

                    const data = await response.json();
                    document.querySelector('[data-commission-summary-container]').innerHTML = data.html;
                    document.querySelector('[data-commission-breakdown-container]').innerHTML = data.breakdownHtml;

                    const browserUrl = new URL(requestUrl);
                    browserUrl.searchParams.delete('ajax');
                    browserUrl.searchParams.delete('commission_page');
                    browserUrl.searchParams.set('month', data.month);
                    browserUrl.searchParams.set('year', data.year);

                    if (data.sourceAffiliate === null) {
                        browserUrl.searchParams.delete('source_affiliate');
                    }

                    if (updateHistory) {
                        window.history.replaceState({}, '', browserUrl);
                    }
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        showError(true);
                    }
                } finally {
                    if (activeRequest === requestController) {
                        setLoading(false);
                    }
                }
            };

            document.addEventListener('change', (event) => {
                const periodSelect = event.target.closest('[data-period-select]');

                if (periodSelect) {
                    const form = periodSelect.form;
                    const month = form?.querySelector('[name="month"]');
                    const year = form?.querySelector('[name="year"]');

                    if (! form || ! month || ! year) {
                        return;
                    }

                    if (year.value === 'all') {
                        month.value = 'all';
                    }

                    const url = new URL(window.location.href);
                    url.searchParams.set('month', month.value);
                    url.searchParams.set('year', year.value);
                    url.searchParams.delete('commission_page');
                    loadPeriod(url);

                    return;
                }

                const autoSubmitSelect = event.target.closest('[data-auto-submit-select]');

                if (autoSubmitSelect) {
                    const form = autoSubmitSelect.form;

                    if (! form || form.dataset.submitting === '1') {
                        return;
                    }

                    form.dataset.submitting = '1';
                    form.submit();
                }
            });

            window.addEventListener('popstate', () => loadPeriod(window.location.href, false));
        })();
    </script>
@endsection
