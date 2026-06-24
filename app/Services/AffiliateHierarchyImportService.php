<?php

namespace App\Services;

use App\Models\Affiliate;
use Illuminate\Support\Collection;

class AffiliateHierarchyImportService
{
    private array $groupAffiliateCache = [];

    private array $knownAliasMap = [];

    private array $uplineMap = [];

    public function resolve(array &$results): void
    {
        $affiliateIds = collect($results)->pluck('affiliate_id')->filter()->unique()->values();

        if ($affiliateIds->isEmpty()) {
            return;
        }

        $affiliates = Affiliate::query()
            ->whereKey($affiliateIds)
            ->get();
        $groups = $affiliates->pluck('group_name')->filter()->unique()->values();

        $this->groupAffiliateCache = Affiliate::query()
            ->whereIn('group_name', $groups)
            ->get()
            ->groupBy('group_name')
            ->all();
        $this->uplineMap = Affiliate::query()
            ->whereIn('group_name', $groups)
            ->pluck('upline_id', 'id')
            ->all();

        // Register every self-marker before resolving any relationship so later
        // rows can use short aliases declared by an upline's own row.
        $this->buildKnownAliasMap(
            collect($this->groupAffiliateCache)
                ->flatMap(fn ($groupAffiliates) => $groupAffiliates)
                ->values()
        );

        $plans = $affiliates
            ->mapWithKeys(fn (Affiliate $affiliate): array => [
                $affiliate->id => $this->planAffiliate($affiliate),
            ])
            ->all();

        $proposedUplineMap = $this->uplineMap;
        foreach ($plans as $affiliateId => $plan) {
            $proposedUplineMap[$affiliateId] = $plan['status'] === 'matched'
                ? $plan['upline']->id
                : null;
        }

        $cycleAffiliateIds = collect($plans)
            ->filter(fn (array $plan): bool => $plan['status'] === 'matched')
            ->filter(fn (array $plan): bool => $this->wouldCreateCycle(
                $plan['affiliate']->id,
                $plan['upline']->id,
                $proposedUplineMap,
            ))
            ->keys()
            ->map(fn ($id): int => (int) $id)
            ->all();

        $outcomes = [];

        foreach ($plans as $affiliateId => $plan) {
            $affiliate = $plan['affiliate'];

            if (in_array((int) $affiliateId, $cycleAffiliateIds, true)) {
                $remark = 'Hierarchy conflict: assigning this upline would create a cycle.';
                $affiliate->update([
                    'upline_id' => null,
                    'hierarchy_import_status' => 'cycle_prevented',
                    'hierarchy_import_remark' => $remark,
                ]);
                $this->uplineMap[$affiliateId] = null;
                $outcomes[$affiliateId] = $this->outcome(
                    'Hierarchy Conflict',
                    'Hierarchy Conflict',
                    $remark,
                    $plan['self_reference_detected'],
                    $plan['self_reference_levels'],
                    registeredAliases: $plan['registered_aliases'],
                    cyclePrevented: true,
                );

                continue;
            }

            if ($plan['status'] === 'unresolved') {
                $affiliate->update([
                    'upline_id' => null,
                    'hierarchy_import_status' => 'needs_mapping',
                    'hierarchy_import_remark' => $plan['remark'],
                ]);
                $this->uplineMap[$affiliateId] = null;
                $outcomes[$affiliateId] = $this->outcome(
                    'Needs Mapping',
                    'Needs Mapping',
                    $plan['remark'],
                    $plan['self_reference_detected'],
                    $plan['self_reference_levels'],
                    registeredAliases: $plan['registered_aliases'],
                );

                continue;
            }

            if ($plan['status'] === 'none') {
                $remark = $plan['self_reference_detected']
                    ? 'Self-reference alias detected and no valid higher upline was found.'
                    : null;
                $affiliate->update([
                    'upline_id' => null,
                    'hierarchy_import_status' => $plan['self_reference_detected'] ? 'self_reference_no_upline' : 'no_upline',
                    'hierarchy_import_remark' => $remark,
                ]);
                $this->uplineMap[$affiliateId] = null;
                $outcomes[$affiliateId] = $this->outcome(
                    'No Upline',
                    'No Upline',
                    $remark ?: '-',
                    $plan['self_reference_detected'],
                    $plan['self_reference_levels'],
                    registeredAliases: $plan['registered_aliases'],
                );

                continue;
            }

            $level = $plan['level'];
            $upline = $plan['upline'];
            $affiliate->update([
                'upline_id' => $upline->id,
                'hierarchy_import_status' => $level === 1 ? 'linked' : 'shifted_to_l'.$level,
                'hierarchy_import_remark' => $plan['self_reference_detected']
                    ? 'Self-reference alias detected. Effective upline shifted to L'.$level.'.'
                    : null,
            ]);
            $this->uplineMap[$affiliateId] = $upline->id;

            $uplineMatch = match ($level) {
                2 => 'Shifted to L2',
                3 => 'Shifted to L3',
                default => 'Linked',
            };
            $outcomes[$affiliateId] = $this->outcome(
                $uplineMatch,
                $uplineMatch,
                $plan['self_reference_detected']
                    ? 'Self-reference alias detected. Effective upline shifted to L'.$level.'.'
                    : '-',
                $plan['self_reference_detected'],
                $plan['self_reference_levels'],
                $level > 1 ? $level : null,
                registeredAliases: $plan['registered_aliases'],
                linked: true,
            );
        }

        // Validate L2/L3 only after all direct uplines have been saved. This
        // makes the result independent of Excel row order.
        foreach ($plans as $affiliateId => $plan) {
            if ($plan['status'] !== 'matched' || in_array((int) $affiliateId, $cycleAffiliateIds, true)) {
                continue;
            }

            $validationRemark = $this->validateUpperLevels(
                $plan['affiliate']->fresh('upline.upline.upline'),
                $plan['level'],
            );

            if (! $validationRemark) {
                continue;
            }

            $plan['affiliate']->update([
                'hierarchy_import_status' => 'needs_review',
                'hierarchy_import_remark' => $validationRemark,
            ]);
            $outcomes[$affiliateId]['upline_match'] = 'Needs Review';
            $outcomes[$affiliateId]['status'] = 'Needs Review';
            $outcomes[$affiliateId]['error'] = $validationRemark;
        }

        foreach ($results as &$result) {
            if (! $result['affiliate_id'] || ! isset($outcomes[$result['affiliate_id']])) {
                continue;
            }

            $result = array_merge($result, $outcomes[$result['affiliate_id']]);
        }
    }

    private function buildKnownAliasMap(Collection $affiliates): void
    {
        $this->knownAliasMap = [];

        foreach ($affiliates as $affiliate) {
            foreach ([$affiliate->raw_l1, $affiliate->raw_l2, $affiliate->raw_l3] as $rawValue) {
                $alias = $this->nullableString($rawValue);

                if ($this->isNoUplineValue($alias) || ! $this->aliasMatchesAffiliate((string) $alias, $affiliate)) {
                    continue;
                }

                $normalizedAlias = $this->normalizeAlias((string) $alias);
                $this->knownAliasMap[$affiliate->group_name][$normalizedAlias][$affiliate->id] = true;
            }
        }
    }

    private function planAffiliate(Affiliate $affiliate): array
    {
        $candidates = [
            1 => $this->nullableString($affiliate->raw_l1),
            2 => $this->nullableString($affiliate->raw_l2),
            3 => $this->nullableString($affiliate->raw_l3),
        ];
        $selfReferenceLevels = [];
        $registeredAliases = $this->registeredAliasesFor($affiliate);

        foreach ($candidates as $level => $rawValue) {
            if ($this->isNoUplineValue($rawValue)) {
                continue;
            }

            if ($this->aliasMatchesAffiliate((string) $rawValue, $affiliate)) {
                $selfReferenceLevels[] = $level;
                continue;
            }

            $match = $this->resolveAffiliateAlias(
                (string) $rawValue,
                (string) $affiliate->group_name,
                $affiliate->id,
            );

            if ($match['status'] !== 'matched') {
                return [
                    'status' => 'unresolved',
                    'affiliate' => $affiliate,
                    'upline' => null,
                    'level' => null,
                    'remark' => $match['remark'],
                    'self_reference_detected' => $selfReferenceLevels !== [],
                    'self_reference_levels' => $selfReferenceLevels,
                    'registered_aliases' => $registeredAliases,
                ];
            }

            return [
                'status' => 'matched',
                'affiliate' => $affiliate,
                'upline' => $match['affiliate'],
                'level' => $level,
                'remark' => null,
                'self_reference_detected' => $selfReferenceLevels !== [],
                'self_reference_levels' => $selfReferenceLevels,
                'registered_aliases' => $registeredAliases,
            ];
        }

        return [
            'status' => 'none',
            'affiliate' => $affiliate,
            'upline' => null,
            'level' => null,
            'remark' => null,
            'self_reference_detected' => $selfReferenceLevels !== [],
            'self_reference_levels' => $selfReferenceLevels,
            'registered_aliases' => $registeredAliases,
        ];
    }

    private function registeredAliasesFor(Affiliate $affiliate): array
    {
        return collect($this->knownAliasMap[$affiliate->group_name] ?? [])
            ->filter(fn (array $affiliateIds): bool => isset($affiliateIds[$affiliate->id]))
            ->keys()
            ->values()
            ->all();
    }

    private function outcome(
        string $uplineMatch,
        string $status,
        ?string $remark,
        bool $selfReferenceDetected = false,
        array $selfReferenceLevels = [],
        ?int $shiftedToLevel = null,
        array $registeredAliases = [],
        bool $cyclePrevented = false,
        bool $linked = false,
    ): array {
        $outcome = [
            'upline_match' => $uplineMatch,
            'error' => $remark ?: '-',
            'self_reference_detected' => $selfReferenceDetected,
            'self_reference_levels' => $selfReferenceLevels,
            'registered_aliases' => $registeredAliases,
            'shifted_to_l2' => $shiftedToLevel === 2,
            'shifted_to_l3' => $shiftedToLevel === 3,
            'cycle_prevented' => $cyclePrevented,
            'hierarchy_linked' => $linked,
        ];

        if (in_array($status, ['Needs Mapping', 'Needs Review', 'Hierarchy Conflict'], true)) {
            $outcome['status'] = $status;
        }

        return $outcome;
    }

    private function validateUpperLevels(Affiliate $affiliate, int $directLevel): ?string
    {
        $checks = [];

        if ($directLevel === 1) {
            $checks[2] = $affiliate->upline?->upline;
            $checks[3] = $affiliate->upline?->upline?->upline;
        } elseif ($directLevel === 2) {
            $checks[3] = $affiliate->upline?->upline;
        }

        foreach ($checks as $level => $expectedAffiliate) {
            $rawValue = $this->nullableString($level === 2 ? $affiliate->raw_l2 : $affiliate->raw_l3);

            if ($this->isNoUplineValue($rawValue)) {
                continue;
            }

            $match = $this->resolveAffiliateAlias(
                (string) $rawValue,
                (string) $affiliate->group_name,
                $affiliate->id,
            );

            if ($match['status'] !== 'matched') {
                return 'L'.$level.' needs mapping: '.$rawValue;
            }

            if ($expectedAffiliate && (int) $expectedAffiliate->id !== (int) $match['affiliate']->id) {
                return 'L'.$level.' does not match the selected upline chain.';
            }
        }

        return null;
    }

    private function resolveAffiliateAlias(string $alias, string $groupName, ?int $excludeId = null): array
    {
        $normalizedAlias = $this->normalizeAlias($alias);

        if ($normalizedAlias === '') {
            return ['status' => 'none', 'affiliate' => null, 'remark' => 'No upline value.'];
        }

        $knownIds = collect(array_keys($this->knownAliasMap[$groupName][$normalizedAlias] ?? []))
            ->map(fn ($id): int => (int) $id)
            ->when($excludeId, fn (Collection $ids) => $ids->reject(fn (int $id): bool => $id === (int) $excludeId))
            ->values();

        $aliasTokens = $this->aliasTokens($normalizedAlias);
        $groupAffiliates = collect($this->groupAffiliateCache[$groupName] ?? []);
        $fuzzyIds = $groupAffiliates
            ->when($excludeId, fn (Collection $affiliates) => $affiliates->where('id', '!=', $excludeId))
            ->filter(function (Affiliate $affiliate) use ($normalizedAlias, $aliasTokens): bool {
                $candidate = $this->normalizeAlias($affiliate->name);

                if ($candidate === $normalizedAlias || str_contains(' '.$candidate.' ', ' '.$normalizedAlias.' ')) {
                    return true;
                }

                return $aliasTokens !== []
                    && collect($aliasTokens)->every(fn (string $token): bool => str_contains(' '.$candidate.' ', ' '.$token.' '));
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);
        $matchIds = $knownIds
            ->merge($fuzzyIds)
            ->unique()
            ->values();

        if ($matchIds->count() === 1) {
            return [
                'status' => 'matched',
                'affiliate' => $groupAffiliates->firstWhere('id', $matchIds->first()),
                'remark' => null,
            ];
        }

        if ($matchIds->count() > 1) {
            return ['status' => 'ambiguous', 'affiliate' => null, 'remark' => 'Ambiguous upline alias: '.$alias];
        }

        return ['status' => 'missing', 'affiliate' => null, 'remark' => 'Upline alias not found in this group: '.$alias];
    }

    private function aliasMatchesAffiliate(string $alias, Affiliate $affiliate): bool
    {
        $normalizedAlias = $this->normalizeAlias($alias);
        $candidate = $this->normalizeAlias($affiliate->name);

        if ($normalizedAlias === '' || $candidate === '') {
            return false;
        }

        if ($candidate === $normalizedAlias || str_contains(' '.$candidate.' ', ' '.$normalizedAlias.' ')) {
            return true;
        }

        $aliasTokens = collect($this->aliasTokens($normalizedAlias));

        return $aliasTokens->isNotEmpty()
            && $aliasTokens->every(fn (string $token): bool => str_contains(' '.$candidate.' ', ' '.$token.' '));
    }

    private function wouldCreateCycle(int $affiliateId, int $proposedUplineId, array $uplineMap): bool
    {
        if ($affiliateId === $proposedUplineId) {
            return true;
        }

        $visited = [];
        $currentId = $proposedUplineId;

        while ($currentId) {
            if ($currentId === $affiliateId || in_array($currentId, $visited, true)) {
                return true;
            }

            $visited[] = $currentId;
            $currentId = (int) ($uplineMap[$currentId] ?? 0);
        }

        return false;
    }

    private function aliasTokens(string $normalizedAlias): array
    {
        $ignoredTokens = ['affiliate', 'manager', 'senior', 'general'];

        return collect(explode(' ', $normalizedAlias))
            ->filter(fn (string $token): bool => strlen($token) >= 3 && ! in_array($token, $ignoredTokens, true))
            ->values()
            ->all();
    }

    private function normalizeAlias(string $name): string
    {
        $value = strtolower(trim(preg_replace('/\s+/', ' ', $name)));
        $value = preg_replace('/^(puan|en|encik|cik|tuan)\.?\s+/i', '', (string) $value);
        $value = preg_replace('/[^a-z0-9\s]/i', ' ', (string) $value);

        return trim(preg_replace('/\s+/', ' ', (string) $value));
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function isNoUplineValue(?string $value): bool
    {
        $value = strtolower(trim((string) $value));

        return $value === '' || in_array($value, ['-', 'tiada', 'none', 'n/a', 'na'], true);
    }
}
