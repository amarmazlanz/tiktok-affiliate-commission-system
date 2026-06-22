<?php

namespace App\Services;

use App\Models\Affiliate;

class AffiliateHierarchyImportService
{
    public function resolve(array &$results): void
    {
        $outcomes = [];

        foreach (collect($results)->pluck('affiliate_id')->filter()->unique()->values() as $affiliateId) {
            $affiliate = Affiliate::query()->find($affiliateId);

            if (! $affiliate) {
                continue;
            }

            $outcomes[$affiliate->id] = $this->resolveAffiliate($affiliate);
        }

        foreach ($results as &$result) {
            if (! $result['affiliate_id'] || ! isset($outcomes[$result['affiliate_id']])) {
                continue;
            }

            $result = array_merge($result, $outcomes[$result['affiliate_id']]);
        }
    }

    private function resolveAffiliate(Affiliate $affiliate): array
    {
        $candidates = [
            1 => $this->nullableString($affiliate->raw_l1),
            2 => $this->nullableString($affiliate->raw_l2),
            3 => $this->nullableString($affiliate->raw_l3),
        ];

        $selfReferenceDetected = false;
        $selfReferenceLevels = [];

        foreach ($candidates as $level => $rawValue) {
            if ($this->isNoUplineValue($rawValue)) {
                continue;
            }

            if ($this->aliasMatchesAffiliate((string) $rawValue, $affiliate)) {
                $selfReferenceDetected = true;
                $selfReferenceLevels[] = $level;
                continue;
            }

            $match = $this->resolveAffiliateAlias((string) $rawValue, (string) $affiliate->group_name, $affiliate->id);

            if ($match['status'] !== 'matched') {
                $affiliate->update([
                    'upline_id' => null,
                    'hierarchy_import_status' => 'needs_mapping',
                    'hierarchy_import_remark' => $match['remark'],
                ]);

                return $this->outcome(
                    'Needs Mapping',
                    'Needs Mapping',
                    $match['remark'],
                    $selfReferenceDetected,
                    $selfReferenceLevels,
                );
            }

            /** @var Affiliate $upline */
            $upline = $match['affiliate'];

            if ($this->wouldCreateCycle($affiliate, $upline)) {
                $remark = 'Hierarchy conflict: assigning this upline would create a cycle.';

                $affiliate->update([
                    'upline_id' => null,
                    'hierarchy_import_status' => 'cycle_prevented',
                    'hierarchy_import_remark' => $remark,
                ]);

                return $this->outcome(
                    'Hierarchy Conflict',
                    'Hierarchy Conflict',
                    $remark,
                    $selfReferenceDetected,
                    $selfReferenceLevels,
                    cyclePrevented: true,
                );
            }

            $affiliate->update([
                'upline_id' => $upline->id,
                'hierarchy_import_status' => $level === 1 ? 'linked' : 'shifted_to_l'.$level,
                'hierarchy_import_remark' => $selfReferenceDetected
                    ? 'Self-reference alias detected. Effective upline shifted to L'.$level.'.'
                    : null,
            ]);

            $uplineMatch = match ($level) {
                2 => 'Shifted to L2',
                3 => 'Shifted to L3',
                default => 'Linked',
            };

            $validationRemark = $this->validateUpperLevels($affiliate->fresh('upline.upline'), $level);

            if ($validationRemark) {
                $affiliate->update([
                    'hierarchy_import_status' => 'needs_review',
                    'hierarchy_import_remark' => $validationRemark,
                ]);

                return $this->outcome(
                    'Needs Review',
                    'Needs Review',
                    $validationRemark,
                    $selfReferenceDetected,
                    $selfReferenceLevels,
                    shiftedToLevel: $level > 1 ? $level : null,
                );
            }

            return $this->outcome(
                $uplineMatch,
                $uplineMatch,
                $selfReferenceDetected ? 'Self-reference alias detected. Effective upline shifted to L'.$level.'.' : '-',
                $selfReferenceDetected,
                $selfReferenceLevels,
                shiftedToLevel: $level > 1 ? $level : null,
                linked: true,
            );
        }

        $affiliate->update([
            'upline_id' => null,
            'hierarchy_import_status' => $selfReferenceDetected ? 'self_reference_no_upline' : 'no_upline',
            'hierarchy_import_remark' => $selfReferenceDetected
                ? 'Self-reference alias detected and no valid higher upline was found.'
                : null,
        ]);

        return $this->outcome(
            'No Upline',
            'No Upline',
            $selfReferenceDetected ? 'Self-reference alias detected and no valid higher upline was found.' : '-',
            $selfReferenceDetected,
            $selfReferenceLevels,
        );
    }

    private function outcome(
        string $uplineMatch,
        string $status,
        ?string $remark,
        bool $selfReferenceDetected = false,
        array $selfReferenceLevels = [],
        ?int $shiftedToLevel = null,
        bool $cyclePrevented = false,
        bool $linked = false,
    ): array {
        $outcome = [
            'upline_match' => $uplineMatch,
            'error' => $remark ?: '-',
            'self_reference_detected' => $selfReferenceDetected,
            'self_reference_levels' => $selfReferenceLevels,
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

            $match = $this->resolveAffiliateAlias((string) $rawValue, (string) $affiliate->group_name, $affiliate->id);

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

        $aliasTokens = $this->aliasTokens($normalizedAlias);

        $matches = Affiliate::query()
            ->where('group_name', $groupName)
            ->when($excludeId, fn ($query) => $query->whereKeyNot($excludeId))
            ->get()
            ->filter(function (Affiliate $affiliate) use ($normalizedAlias, $aliasTokens): bool {
                $candidate = $this->normalizeAlias($affiliate->name);

                if ($candidate === $normalizedAlias || str_contains(' '.$candidate.' ', ' '.$normalizedAlias.' ')) {
                    return true;
                }

                if ($aliasTokens === []) {
                    return false;
                }

                return collect($aliasTokens)->every(fn (string $token): bool => str_contains(' '.$candidate.' ', ' '.$token.' '));
            })
            ->values();

        if ($matches->count() === 1) {
            return ['status' => 'matched', 'affiliate' => $matches->first(), 'remark' => null];
        }

        if ($matches->count() > 1) {
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

    private function wouldCreateCycle(Affiliate $affiliate, Affiliate $proposedUpline): bool
    {
        if ((int) $affiliate->id === (int) $proposedUpline->id) {
            return true;
        }

        $visited = [];
        $current = $proposedUpline;

        while ($current) {
            if ((int) $current->id === (int) $affiliate->id) {
                return true;
            }

            if (in_array((int) $current->id, $visited, true)) {
                return true;
            }

            $visited[] = (int) $current->id;
            $current = $current->upline;
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
