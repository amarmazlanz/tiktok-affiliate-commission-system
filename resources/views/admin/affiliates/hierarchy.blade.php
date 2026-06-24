@extends('layouts.auth')

@section('title', 'Affiliate Hierarchy')

@section('content')
    <main class="min-h-screen bg-slate-100">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
                <div>
                    <p class="text-sm font-medium text-emerald-700">Admin</p>
                    <h1 class="text-xl font-semibold text-slate-950">Affiliate Hierarchy</h1>
                </div>
            </div>
        </header>

        <section class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6">
            <div class="app-card p-6 sm:p-7">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-emerald-700">Team Structure</p>
                        <h2 class="mt-1 text-2xl font-black text-slate-950">Affiliate Hierarchy</h2>
                        <p class="mt-2 text-sm text-slate-600">
                            Browse hierarchy by group, type, status, position, affiliate code, name, or TikTok username.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('admin.affiliates.index') }}" class="btn-secondary">Affiliate Management</a>
                        <button type="button" id="hierarchy-expand-one" class="btn-secondary">Expand 1 Level</button>
                        <button type="button" id="hierarchy-expand-all" class="btn-secondary">Expand All</button>
                        <button type="button" id="hierarchy-collapse-all" class="btn-secondary">Collapse All</button>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="stat-card">
                    <p class="stat-label">Total Affiliates</p>
                    <p id="hierarchy-total-affiliates" class="stat-value">{{ number_format($totalAffiliates) }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Total Managers</p>
                    <p id="hierarchy-total-managers" class="stat-value">{{ number_format($totalManagers) }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Root Affiliates</p>
                    <p id="hierarchy-root-affiliates" class="stat-value">{{ number_format($tree->count()) }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Max Hierarchy Depth</p>
                    <p id="hierarchy-max-depth" class="stat-value">{{ number_format($maxDepth) }}</p>
                </div>
            </div>

            <div class="app-card overflow-hidden">
                <div class="border-b border-slate-200 bg-white px-6 py-5">
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-[1.4fr_1fr_1fr_1fr_1fr_220px] xl:items-end">
                        <div>
                            <label for="hierarchy-search" class="block text-sm font-semibold text-slate-700">Search</label>
                            <input id="hierarchy-search" type="search" placeholder="Name, affiliate code or TikTok username" class="form-field">
                        </div>

                        <div>
                            <label for="hierarchy-group-filter" class="block text-sm font-semibold text-slate-700">Group</label>
                            <select id="hierarchy-group-filter" class="form-field">
                                <option value="">All Groups</option>
                                @foreach ($groups as $group)
                                    <option value="{{ $group }}">{{ $group }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="hierarchy-type-filter" class="block text-sm font-semibold text-slate-700">Type</label>
                            <select id="hierarchy-type-filter" class="form-field">
                                <option value="">All</option>
                                <option value="inhouse">Inhouse</option>
                                <option value="external">Affiliate Luar</option>
                            </select>
                        </div>

                        <div>
                            <label for="hierarchy-status-filter" class="block text-sm font-semibold text-slate-700">Status</label>
                            <select id="hierarchy-status-filter" class="form-field">
                                <option value="">All</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>

                        <div>
                            <label for="hierarchy-position-filter" class="block text-sm font-semibold text-slate-700">Position</label>
                            <select id="hierarchy-position-filter" class="form-field">
                                <option value="">All</option>
                                <option value="manager">Manager</option>
                                <option value="affiliate">Affiliate</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-700">Viewing mode</label>
                            <div class="mt-2 grid grid-cols-2 rounded-lg border border-slate-200 bg-slate-50 p-1">
                                <button type="button" data-view-mode="tree" class="rounded-md bg-white px-3 py-2 text-sm font-bold text-emerald-700 shadow-sm">Tree View</button>
                                <button type="button" data-view-mode="table" class="rounded-md px-3 py-2 text-sm font-bold text-slate-600">Table View</button>
                            </div>
                        </div>
                    </div>

                    <p id="hierarchy-search-count" class="mt-4 text-sm font-medium text-slate-500">
                        {{ number_format($totalAffiliates) }} affiliates in hierarchy
                    </p>
                </div>

                @if ($tree->isEmpty())
                    <div class="px-6 py-12 text-center text-sm text-slate-500">
                        No affiliate hierarchy available.
                    </div>
                @else
                    <div id="hierarchy-tree-view">
                        <div class="border-b border-slate-200 bg-white px-5 py-3">
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs font-semibold text-slate-600">
                                <span class="text-[11px] font-black uppercase text-slate-400">Hierarchy legend</span>
                                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-emerald-600"></span>Root Manager</span>
                                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-blue-500"></span>Level 1</span>
                                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-amber-500"></span>Level 2</span>
                                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-slate-400"></span>Level 3+</span>
                                <span class="badge badge-teal">Inhouse</span>
                                <span class="badge badge-amber">Affiliate Luar</span>
                            </div>
                        </div>
                        <div class="max-h-[70vh] overflow-y-auto overflow-x-hidden">
                            <div class="space-y-5 bg-slate-50 p-3 sm:p-6">
                                <div id="hierarchy-no-match" class="hidden rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800">
                                    No matching affiliate found.
                                </div>

                                @foreach ($tree as $rootNode)
                                    <section class="rounded-xl border border-slate-200 bg-white/60 p-2 shadow-sm sm:p-3">
                                        @include('admin.affiliates.partials.hierarchy-node', [
                                            'node' => $rootNode,
                                            'parentId' => '',
                                            'ancestorIds' => '',
                                            'depth' => 0,
                                        ])
                                    </section>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div id="hierarchy-table-view" class="hidden">
                        <div class="overflow-x-auto">
                            <table class="app-table min-w-full divide-y divide-slate-200 text-sm">
                                <thead>
                                    <tr>
                                        <th class="text-left"><button type="button" data-sort-key="name">Affiliate</button></th>
                                        <th class="text-left"><button type="button" data-sort-key="group_name">Group</button></th>
                                        <th class="text-left"><button type="button" data-sort-key="affiliate_type">Type</button></th>
                                        <th class="text-left"><button type="button" data-sort-key="direct_upline">Direct Upline</button></th>
                                        <th class="text-left"><button type="button" data-sort-key="position">Position</button></th>
                                        <th class="text-right"><button type="button" data-sort-key="direct_count">Direct</button></th>
                                        <th class="text-right"><button type="button" data-sort-key="level2_count">L2</button></th>
                                        <th class="text-right"><button type="button" data-sort-key="level3_count">L3</button></th>
                                        <th class="text-right"><button type="button" data-sort-key="total_team_count">Total Team</button></th>
                                        <th class="text-left"><button type="button" data-sort-key="status">Status</button></th>
                                        <th class="text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="hierarchy-table-body" class="divide-y divide-slate-100 bg-white"></tbody>
                            </table>
                        </div>

                        <div class="flex flex-col gap-3 border-t border-slate-200 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <p id="hierarchy-table-count" class="text-sm font-medium text-slate-500"></p>
                            <div class="flex items-center gap-2">
                                <button type="button" id="hierarchy-prev-page" class="btn-secondary px-3 py-2 text-xs">Previous</button>
                                <span id="hierarchy-page-label" class="text-sm font-semibold text-slate-600"></span>
                                <button type="button" id="hierarchy-next-page" class="btn-secondary px-3 py-2 text-xs">Next</button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </section>

        <aside id="hierarchy-detail-panel" class="fixed inset-y-0 right-0 z-50 hidden w-full max-w-md border-l border-slate-200 bg-white shadow-2xl">
            <div class="flex h-full flex-col">
                <div class="flex items-start justify-between border-b border-slate-200 px-6 py-5">
                    <div>
                        <p class="text-xs font-bold uppercase text-emerald-700">Affiliate Detail</p>
                        <h3 id="detail-name" class="mt-1 text-xl font-black text-slate-950"></h3>
                        <p id="detail-code" class="mt-1 font-mono text-sm text-slate-500"></p>
                    </div>
                    <button type="button" id="hierarchy-detail-close" class="rounded-md border border-slate-200 px-3 py-1.5 text-sm font-bold text-slate-600 hover:bg-slate-50">Close</button>
                </div>

                <div class="flex-1 space-y-5 overflow-y-auto px-6 py-5 text-sm">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <p class="text-xs font-bold uppercase text-slate-500">Group</p>
                            <p id="detail-group" class="mt-1 font-semibold text-slate-950"></p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase text-slate-500">Type</p>
                            <p id="detail-type" class="mt-1 font-semibold text-slate-950"></p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase text-slate-500">Position</p>
                            <p id="detail-position" class="mt-1 font-semibold text-slate-950"></p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase text-slate-500">Status</p>
                            <p id="detail-status" class="mt-1 font-semibold text-slate-950"></p>
                        </div>
                    </div>

                    <div>
                        <p class="text-xs font-bold uppercase text-slate-500">Login Access</p>
                        <p id="detail-login" class="mt-1 text-slate-950"></p>
                    </div>

                    <div>
                        <p class="text-xs font-bold uppercase text-slate-500">Direct Upline</p>
                        <p id="detail-upline" class="mt-1 text-slate-950"></p>
                    </div>

                    <div class="grid grid-cols-4 gap-2">
                        <div class="rounded-lg bg-slate-50 p-3 text-center">
                            <p class="text-xs font-bold text-slate-500">Direct</p>
                            <p id="detail-direct-count" class="mt-1 text-lg font-black text-slate-950"></p>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-3 text-center">
                            <p class="text-xs font-bold text-slate-500">L2</p>
                            <p id="detail-l2-count" class="mt-1 text-lg font-black text-slate-950"></p>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-3 text-center">
                            <p class="text-xs font-bold text-slate-500">L3</p>
                            <p id="detail-l3-count" class="mt-1 text-lg font-black text-slate-950"></p>
                        </div>
                        <div class="rounded-lg bg-emerald-50 p-3 text-center">
                            <p class="text-xs font-bold text-emerald-700">Total</p>
                            <p id="detail-total-count" class="mt-1 text-lg font-black text-emerald-800"></p>
                        </div>
                    </div>

                    <div>
                        <p class="text-xs font-bold uppercase text-slate-500">TikTok Accounts</p>
                        <div id="detail-tiktok" class="mt-2 flex flex-wrap gap-2"></div>
                    </div>

                    <div>
                        <p class="text-xs font-bold uppercase text-slate-500">Direct Downlines</p>
                        <div id="detail-downlines" class="mt-2 space-y-2"></div>
                    </div>
                </div>

                <div class="border-t border-slate-200 px-6 py-4">
                    <a id="detail-view-link" href="#" class="btn-primary w-full">View Affiliate</a>
                </div>
            </div>
        </aside>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tableRows = @json($tableRows);
            const nodes = Array.from(document.querySelectorAll('[data-hierarchy-node]'));
            const nodesById = new Map(nodes.map((node) => [node.dataset.nodeId, node]));
            const searchInput = document.getElementById('hierarchy-search');
            const groupFilter = document.getElementById('hierarchy-group-filter');
            const typeFilter = document.getElementById('hierarchy-type-filter');
            const statusFilter = document.getElementById('hierarchy-status-filter');
            const positionFilter = document.getElementById('hierarchy-position-filter');
            const searchCount = document.getElementById('hierarchy-search-count');
            const noMatch = document.getElementById('hierarchy-no-match');
            const treeView = document.getElementById('hierarchy-tree-view');
            const tableView = document.getElementById('hierarchy-table-view');
            const tableBody = document.getElementById('hierarchy-table-body');
            const tableCount = document.getElementById('hierarchy-table-count');
            const pageLabel = document.getElementById('hierarchy-page-label');
            const prevPage = document.getElementById('hierarchy-prev-page');
            const nextPage = document.getElementById('hierarchy-next-page');
            const detailPanel = document.getElementById('hierarchy-detail-panel');
            const totalAffiliatesStat = document.getElementById('hierarchy-total-affiliates');
            const totalManagersStat = document.getElementById('hierarchy-total-managers');
            const rootAffiliatesStat = document.getElementById('hierarchy-root-affiliates');
            const maxDepthStat = document.getElementById('hierarchy-max-depth');
            const perPage = 20;
            let currentPage = 1;
            let sortKey = 'name';
            let sortDirection = 'asc';

            const escapeHtml = (value) => String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');

            const selectedFilters = () => ({
                search: (searchInput?.value || '').trim().toLowerCase(),
                group: groupFilter?.value || '',
                type: typeFilter?.value || '',
                status: statusFilter?.value || '',
                position: positionFilter?.value || '',
            });

            const rowMatches = (row, filters) => {
                const searchText = [
                    row.name,
                    row.affiliate_code,
                    ...(row.tiktok_accounts || []),
                ].join(' ').toLowerCase();

                return (filters.search === '' || searchText.includes(filters.search))
                    && (filters.group === '' || row.group_name === filters.group)
                    && (filters.type === '' || row.affiliate_type === filters.type)
                    && (filters.status === '' || row.status === filters.status)
                    && (filters.position === '' || row.position.toLowerCase() === filters.position);
            };

            const setExpanded = (nodeId, expanded) => {
                const children = document.querySelector(`[data-hierarchy-children="${nodeId}"]`);
                const toggle = document.querySelector(`[data-hierarchy-toggle="${nodeId}"]`);

                if (!children || !toggle) return;

                children.classList.toggle('hidden', !expanded);
                toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                toggle.setAttribute('aria-label', `${expanded ? 'Collapse' : 'Expand'} affiliate branch`);
                const chevron = toggle.querySelector('[data-chevron]');
                chevron?.classList.toggle('-rotate-45', !expanded);
                chevron?.classList.toggle('rotate-45', expanded);
                chevron?.classList.toggle('-translate-y-0.5', expanded);
            };

            const expandParentChain = (node) => {
                (node.dataset.ancestorIds || '').split(',').filter(Boolean).forEach((id) => setExpanded(id, true));
            };

            const applyTreeFilters = () => {
                const filters = selectedFilters();
                const matchingIds = new Set();
                const visibleIds = new Set();

                nodes.forEach((node) => {
                    node.firstElementChild?.classList.remove('ring-2', 'ring-amber-300', 'bg-amber-50');

                    const searchText = [
                        node.dataset.nodeName || '',
                        node.dataset.nodeCode || '',
                        node.dataset.nodeTiktok || '',
                    ].join(' ');
                    const matches = (filters.search === '' || searchText.includes(filters.search))
                        && (filters.group === '' || node.dataset.nodeGroup === filters.group)
                        && (filters.type === '' || node.dataset.nodeType === filters.type)
                        && (filters.status === '' || node.dataset.nodeStatus === filters.status)
                        && (filters.position === '' || node.dataset.nodePosition === filters.position);

                    if (matches) {
                        matchingIds.add(node.dataset.nodeId);
                        visibleIds.add(node.dataset.nodeId);
                        (node.dataset.ancestorIds || '').split(',').filter(Boolean).forEach((id) => visibleIds.add(id));

                        if (filters.search !== '') {
                            node.firstElementChild?.classList.add('ring-2', 'ring-amber-300', 'bg-amber-50');
                            expandParentChain(node);
                        }
                    }
                });

                nodes.forEach((node) => node.classList.toggle('hidden', !visibleIds.has(node.dataset.nodeId)));
                noMatch?.classList.toggle('hidden', matchingIds.size > 0 || nodes.length === 0);

                if (searchCount) {
                    const filtering = filters.search || filters.group || filters.type || filters.status || filters.position;
                    searchCount.textContent = filtering
                        ? `${matchingIds.size.toLocaleString()} matching affiliate${matchingIds.size === 1 ? '' : 's'}`
                        : `${nodes.length.toLocaleString()} affiliates in hierarchy`;
                }

                const matchingRows = tableRows.filter((row) => rowMatches(row, filters));
                const matchingRowIds = new Set(matchingRows.map((row) => String(row.id)));
                const matchingRootCount = matchingRows.filter((row) => {
                    const node = nodesById.get(String(row.id));
                    return node && (node.dataset.parentId === '' || !matchingRowIds.has(node.dataset.parentId));
                }).length;

                if (totalAffiliatesStat) totalAffiliatesStat.textContent = matchingRows.length.toLocaleString();
                if (totalManagersStat) totalManagersStat.textContent = matchingRows.filter((row) => row.position === 'Manager').length.toLocaleString();
                if (rootAffiliatesStat) rootAffiliatesStat.textContent = matchingRootCount.toLocaleString();
                if (maxDepthStat) {
                    const filteredMaxDepth = matchingRows.length
                        ? Math.max(...matchingRows.map((row) => Number(row.depth))) + 1
                        : 0;
                    maxDepthStat.textContent = filteredMaxDepth.toLocaleString();
                }
            };

            const filteredTableRows = () => {
                const filters = selectedFilters();
                const numericKeys = ['direct_count', 'level2_count', 'level3_count', 'total_team_count'];

                return tableRows
                    .filter((row) => rowMatches(row, filters))
                    .sort((a, b) => {
                        const aValue = numericKeys.includes(sortKey) ? Number(a[sortKey]) : String(a[sortKey] ?? '').toLowerCase();
                        const bValue = numericKeys.includes(sortKey) ? Number(b[sortKey]) : String(b[sortKey] ?? '').toLowerCase();
                        const result = aValue > bValue ? 1 : (aValue < bValue ? -1 : 0);

                        return sortDirection === 'asc' ? result : -result;
                    });
            };

            const renderTable = () => {
                if (!tableBody) return;

                const rows = filteredTableRows();
                const totalPages = Math.max(1, Math.ceil(rows.length / perPage));
                currentPage = Math.min(currentPage, totalPages);
                const start = (currentPage - 1) * perPage;
                const pageRows = rows.slice(start, start + perPage);

                tableBody.innerHTML = pageRows.length
                    ? pageRows.map((row) => `
                        <tr>
                            <td class="font-medium text-slate-950">${escapeHtml(row.name)}<div class="font-mono text-xs font-normal text-slate-500">${escapeHtml(row.affiliate_code)}</div></td>
                            <td><span class="badge badge-blue">${escapeHtml(row.group_name)}</span></td>
                            <td><span class="badge ${row.affiliate_type === 'external' ? 'badge-amber' : 'badge-teal'}">${row.affiliate_type === 'external' ? 'Affiliate Luar' : 'Inhouse'}</span></td>
                            <td class="text-slate-700">${escapeHtml(row.direct_upline)}</td>
                            <td><span class="badge ${row.position === 'Manager' ? 'badge-green' : 'badge-gray'}">${escapeHtml(row.position)}</span></td>
                            <td class="money text-slate-700">${row.direct_count}</td>
                            <td class="money text-slate-700">${row.level2_count}</td>
                            <td class="money text-slate-700">${row.level3_count}</td>
                            <td class="money font-semibold text-emerald-700">${row.total_team_count}</td>
                            <td><span class="badge ${row.status === 'active' ? 'badge-green' : 'badge-gray'}">${escapeHtml(row.status.charAt(0).toUpperCase() + row.status.slice(1))}</span></td>
                            <td class="text-right"><a href="${escapeHtml(row.url)}" class="btn-secondary px-3 py-1.5 text-xs">View</a></td>
                        </tr>
                    `).join('')
                    : '<tr><td colspan="11" class="px-5 py-10 text-center text-slate-500">No matching affiliate found.</td></tr>';

                const end = rows.length === 0 ? 0 : Math.min(start + perPage, rows.length);
                if (tableCount) tableCount.textContent = `Showing ${rows.length === 0 ? 0 : start + 1}-${end} of ${rows.length.toLocaleString()} affiliates`;
                if (pageLabel) pageLabel.textContent = `Page ${currentPage} of ${totalPages}`;

                if (prevPage && nextPage) {
                    prevPage.disabled = currentPage <= 1;
                    nextPage.disabled = currentPage >= totalPages;
                    prevPage.classList.toggle('opacity-50', prevPage.disabled);
                    nextPage.classList.toggle('opacity-50', nextPage.disabled);
                }
            };

            const applyAllFilters = () => {
                currentPage = 1;
                applyTreeFilters();
                renderTable();
            };

            const openDetail = (detail) => {
                document.getElementById('detail-name').textContent = detail.name;
                document.getElementById('detail-code').textContent = detail.affiliate_code;
                document.getElementById('detail-group').textContent = detail.group_name;
                document.getElementById('detail-type').textContent = detail.affiliate_type;
                document.getElementById('detail-position').textContent = detail.position;
                document.getElementById('detail-status').textContent = detail.status;
                document.getElementById('detail-login').textContent = detail.login_access;
                document.getElementById('detail-upline').textContent = detail.direct_upline;
                document.getElementById('detail-direct-count').textContent = detail.direct_count;
                document.getElementById('detail-l2-count').textContent = detail.level2_count;
                document.getElementById('detail-l3-count').textContent = detail.level3_count;
                document.getElementById('detail-total-count').textContent = detail.total_team_count;
                document.getElementById('detail-view-link').href = detail.url;

                document.getElementById('detail-tiktok').innerHTML = detail.tiktok_accounts?.length
                    ? detail.tiktok_accounts.map((account) => `<span class="rounded bg-slate-100 px-2 py-1 font-mono text-xs font-semibold text-slate-700">${escapeHtml(account)}</span>`).join('')
                    : '<span class="text-slate-500">No TikTok accounts.</span>';

                document.getElementById('detail-downlines').innerHTML = detail.direct_downlines?.length
                    ? detail.direct_downlines.map((downline) => `<div class="rounded-lg border border-slate-200 px-3 py-2 text-slate-700">${escapeHtml(downline.name)} <span class="font-mono text-xs text-slate-500">(${escapeHtml(downline.affiliate_code)})</span></div>`).join('')
                    : '<span class="text-slate-500">No direct downlines.</span>';

                detailPanel?.classList.remove('hidden');
            };

            document.querySelectorAll('[data-hierarchy-toggle]').forEach((toggle) => {
                toggle.addEventListener('click', () => {
                    const children = document.querySelector(`[data-hierarchy-children="${toggle.dataset.hierarchyToggle}"]`);
                    if (children) setExpanded(toggle.dataset.hierarchyToggle, children.classList.contains('hidden'));
                });
            });

            document.querySelectorAll('[data-open-node-detail]').forEach((button) => {
                button.addEventListener('click', () => {
                    const node = button.closest('[data-hierarchy-node]');
                    openDetail(JSON.parse(node.dataset.nodeDetail));
                });
            });

            document.getElementById('hierarchy-detail-close')?.addEventListener('click', () => detailPanel?.classList.add('hidden'));
            document.getElementById('hierarchy-expand-one')?.addEventListener('click', () => {
                document.querySelectorAll('[data-hierarchy-children]').forEach((children) => {
                    const owner = document.querySelector(`[data-node-id="${children.dataset.hierarchyChildren}"]`);
                    setExpanded(children.dataset.hierarchyChildren, owner?.dataset.nodeDepth === '0');
                });
            });
            document.getElementById('hierarchy-expand-all')?.addEventListener('click', () => {
                document.querySelectorAll('[data-hierarchy-children]').forEach((children) => setExpanded(children.dataset.hierarchyChildren, true));
            });
            document.getElementById('hierarchy-collapse-all')?.addEventListener('click', () => {
                document.querySelectorAll('[data-hierarchy-children]').forEach((children) => setExpanded(children.dataset.hierarchyChildren, false));
            });

            document.querySelectorAll('[data-view-mode]').forEach((button) => {
                button.addEventListener('click', () => {
                    const mode = button.dataset.viewMode;
                    treeView?.classList.toggle('hidden', mode !== 'tree');
                    tableView?.classList.toggle('hidden', mode !== 'table');
                    document.querySelectorAll('[data-view-mode]').forEach((modeButton) => {
                        const isActive = modeButton.dataset.viewMode === mode;
                        modeButton.classList.toggle('bg-white', isActive);
                        modeButton.classList.toggle('text-emerald-700', isActive);
                        modeButton.classList.toggle('shadow-sm', isActive);
                        modeButton.classList.toggle('text-slate-600', !isActive);
                    });
                });
            });

            document.querySelectorAll('[data-sort-key]').forEach((button) => {
                button.addEventListener('click', () => {
                    const nextKey = button.dataset.sortKey;
                    sortDirection = sortKey === nextKey && sortDirection === 'asc' ? 'desc' : 'asc';
                    sortKey = nextKey;
                    renderTable();
                });
            });

            [searchInput, groupFilter, typeFilter, statusFilter, positionFilter].forEach((input) => {
                input?.addEventListener('input', applyAllFilters);
                input?.addEventListener('change', applyAllFilters);
            });
            prevPage?.addEventListener('click', () => {
                currentPage = Math.max(1, currentPage - 1);
                renderTable();
            });
            nextPage?.addEventListener('click', () => {
                currentPage++;
                renderTable();
            });

            applyAllFilters();
        });
    </script>
@endsection
