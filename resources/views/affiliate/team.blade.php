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
