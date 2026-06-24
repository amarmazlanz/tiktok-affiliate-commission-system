<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\TiktokAccount;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AffiliateExcelSyncService
{
    public function __construct(private AffiliateHierarchyImportService $hierarchyImporter)
    {
    }

    public function import(array $parsedRows, array $options, User $admin, string $fileName): array
    {
        $profiles = $this->aggregateProfiles($parsedRows);
        $state = $this->buildState($profiles);
        $results = [];
        $importedAffiliateIds = [];
        $profileUsernames = [];

        foreach (array_chunk($profiles, 250) as $batch) {
            DB::transaction(function () use (
                $batch,
                $options,
                &$state,
                &$results,
                &$importedAffiliateIds,
                &$profileUsernames,
            ): void {
                foreach ($batch as $profile) {
                    $outcome = $this->syncProfile($profile, $options, $state);
                    $results[] = $outcome['result'];

                    if ($outcome['affiliate']) {
                        $affiliateId = $outcome['affiliate']->id;
                        $importedAffiliateIds[$affiliateId] = $affiliateId;
                        $profileUsernames[$affiliateId] = $outcome['imported_usernames'];
                    }
                }
            });
        }

        $missingResults = $options['import_mode'] === 'sync'
            ? $this->handleMissingAffiliates(
                $profiles,
                array_values($importedAffiliateIds),
                $options['missing_affiliates'],
                $state['all_affiliates'],
            )
            : [];
        $results = array_merge($results, $missingResults);

        if ($options['import_mode'] === 'sync' && $options['tiktok_sync'] === 'mirror') {
            DB::transaction(function () use (&$results, $profileUsernames): void {
                foreach ($profileUsernames as $affiliateId => $usernames) {
                    $markedInactive = TiktokAccount::query()
                        ->where('affiliate_id', $affiliateId)
                        ->whereNotIn('username_normalized', $usernames ?: ['__none__'])
                        ->where('status', '!=', 'inactive')
                        ->update(['status' => 'inactive']);

                    if ($markedInactive > 0) {
                        foreach ($results as &$result) {
                            if (($result['affiliate_id'] ?? null) === $affiliateId) {
                                $result['tiktok_marked_inactive'] = $markedInactive;
                                break;
                            }
                        }
                    }
                }
            });
        }

        $hierarchyResultIndexes = [];
        $hierarchyResults = [];

        foreach ($results as $index => $result) {
            if (
                empty($result['affiliate_id'])
                || in_array($result['status'], ['Skipped Existing', 'Ambiguous Match', 'Missing From Latest'], true)
            ) {
                continue;
            }

            $hierarchyResultIndexes[] = $index;
            $hierarchyResults[] = $result;
        }

        if ($hierarchyResults !== []) {
            DB::transaction(function () use (&$hierarchyResults, $options): void {
                if ($options['import_mode'] === 'sync') {
                    Affiliate::query()
                        ->whereKey(collect($hierarchyResults)->pluck('affiliate_id')->filter()->all())
                        ->update([
                            'upline_id' => null,
                            'hierarchy_import_status' => null,
                            'hierarchy_import_remark' => null,
                        ]);
                }

                $this->hierarchyImporter->resolve($hierarchyResults);
            });

            $resolvedAffiliates = Affiliate::query()
                ->with('upline:id,name')
                ->whereKey(collect($hierarchyResults)->pluck('affiliate_id')->filter()->all())
                ->get()
                ->keyBy('id');

            foreach ($hierarchyResults as $position => $hierarchyResult) {
                $index = $hierarchyResultIndexes[$position];
                $previousUplineId = $results[$index]['previous_upline_id'] ?? null;
                $resolvedAffiliate = $resolvedAffiliates->get($hierarchyResult['affiliate_id']);
                $newUplineId = $resolvedAffiliate?->upline_id;

                $results[$index] = array_merge($results[$index], $hierarchyResult, [
                    'new_upline_id' => $newUplineId,
                    'new_upline' => $resolvedAffiliate?->upline?->name ?: '-',
                    'hierarchy_changed' => (int) $previousUplineId !== (int) $newUplineId,
                    'previous_upline_replaced' => $previousUplineId && (int) $previousUplineId !== (int) $newUplineId,
                ]);
            }
        }

        $summary = $this->summarize($results, count($parsedRows));

        Log::notice('Affiliate management file synchronized.', [
            'admin_user_id' => $admin->id,
            'uploaded_at' => now()->toIso8601String(),
            'file_name' => $fileName,
            'mode' => $options['import_mode'],
            'tiktok_sync' => $options['tiktok_sync'],
            'missing_affiliates' => $options['missing_affiliates'],
            'counts' => $summary,
        ]);

        return compact('results', 'summary');
    }

    private function aggregateProfiles(array $rows): array
    {
        $profiles = [];

        foreach ($rows as $row) {
            $name = $this->normalizeDisplayName((string) ($row['name'] ?? ''));
            $group = $this->normalizeDisplayName((string) ($row['group_name'] ?? 'Unknown Group'));
            $type = ($row['affiliate_type'] ?? 'inhouse') === 'external' ? 'external' : 'inhouse';
            $code = strtoupper(trim((string) ($row['affiliate_code'] ?? '')));
            $key = $code !== ''
                ? 'code|'.$code
                : 'profile|'.strtolower($group).'|'.$type.'|'.$this->normalizeName($name);

            if (! isset($profiles[$key])) {
                $profiles[$key] = [
                    'affiliate_code' => $code ?: null,
                    'group_name' => $group,
                    'affiliate_type' => $type,
                    'name' => $name,
                    'name_normalized' => $this->normalizeName($name),
                    'phone' => $this->nullableString($row['phone'] ?? null),
                    'status' => $this->normalizeStatus($row['status'] ?? null),
                    'raw_l1' => $this->nullableString($row['raw_l1'] ?? null),
                    'raw_l2' => $this->nullableString($row['raw_l2'] ?? null),
                    'raw_l3' => $this->nullableString($row['raw_l3'] ?? null),
                    'raw_import_data' => [],
                    'usernames' => [],
                ];
            }

            $username = $this->normalizeUsername((string) ($row['tiktok_username'] ?? ''));
            if ($this->isValidUsername($username)) {
                $profiles[$key]['usernames'][$username] = ltrim(trim((string) $row['tiktok_username']), '@');
            }

            $profiles[$key]['raw_import_data'][] = $row['raw'] ?? $row;
        }

        return array_values($profiles);
    }

    private function buildState(array $profiles): array
    {
        $allAffiliates = Affiliate::query()
            ->with(['user', 'upline', 'tiktokAccounts'])
            ->get();
        $byCode = [];
        $byIdentity = [];
        $byNameGroup = [];
        $accountsByUsername = [];

        foreach ($allAffiliates as $affiliate) {
            if ($affiliate->affiliate_code) {
                $byCode[strtoupper($affiliate->affiliate_code)] = $affiliate;
            }

            $identity = $this->identityKey(
                $affiliate->name,
                (string) $affiliate->group_name,
                (string) $affiliate->affiliate_type,
            );
            $byIdentity[$identity][] = $affiliate;
            $byNameGroup[$this->nameGroupKey($affiliate->name, (string) $affiliate->group_name)][] = $affiliate;

            foreach ($affiliate->tiktokAccounts as $account) {
                $accountsByUsername[$account->username_normalized] = $account;
            }
        }

        $prefixes = collect($profiles)
            ->pluck('group_name')
            ->map(fn (string $group): string => $this->groupPrefix($group))
            ->unique();
        $latestCodeNumberByPrefix = [];

        foreach ($prefixes as $prefix) {
            $latestCodeNumberByPrefix[$prefix] = $allAffiliates
                ->pluck('affiliate_code')
                ->filter(fn ($code): bool => str_starts_with((string) $code, $prefix.'-'))
                ->map(fn ($code): int => (int) Str::after((string) $code, $prefix.'-'))
                ->max() ?? 0;
        }

        return [
            'all_affiliates' => $allAffiliates,
            'by_code' => $byCode,
            'by_identity' => $byIdentity,
            'by_name_group' => $byNameGroup,
            'accounts_by_username' => $accountsByUsername,
            'latest_code_number_by_prefix' => $latestCodeNumberByPrefix,
            'used_codes' => $allAffiliates->pluck('affiliate_code')->filter()->flip()->all()
                + User::query()->whereNotNull('affiliate_code')->pluck('affiliate_code')->filter()->flip()->all(),
        ];
    }

    private function syncProfile(array $profile, array $options, array &$state): array
    {
        $match = $this->matchAffiliate($profile, $state);

        if ($match['status'] === 'ambiguous') {
            return [
                'affiliate' => null,
                'imported_usernames' => [],
                'result' => $this->baseResult($profile, [
                    'status' => 'Ambiguous Match',
                    'error' => $match['remark'],
                ]),
            ];
        }

        /** @var Affiliate|null $affiliate */
        $affiliate = $match['affiliate'];

        if ($affiliate && $options['import_mode'] === 'append') {
            return [
                'affiliate' => $affiliate,
                'imported_usernames' => [],
                'result' => $this->baseResult($profile, [
                    'affiliate_id' => $affiliate->id,
                    'affiliate_code' => $affiliate->affiliate_code ?: '-',
                    'status' => 'Skipped Existing',
                    'profile_status' => 'Skipped Existing',
                    'error' => 'Existing affiliate was not changed in Append New Affiliates Only mode.',
                ]),
            ];
        }

        $created = ! $affiliate;
        $temporaryPassword = '-';
        $previous = [
            'group' => $affiliate?->group_name ?: '-',
            'type' => $affiliate?->affiliate_type ?: '-',
            'upline_id' => $affiliate?->upline_id,
            'upline' => $affiliate?->upline?->name ?: '-',
        ];

        if (! $affiliate) {
            $code = $profile['affiliate_code'] ?: $this->generateAffiliateCode($profile['group_name'], $state);
            $affiliate = new Affiliate([
                'affiliate_code' => $code,
                'status' => $profile['status'] ?: 'active',
            ]);
        }

        $changed = $created || $this->profileChanged($affiliate, $profile);
        $affiliate->fill([
            'name' => $profile['name'],
            'name_normalized' => $profile['name_normalized'],
            'group_name' => $profile['group_name'],
            'affiliate_type' => $profile['affiliate_type'],
            'phone' => $profile['phone'] ?? $affiliate->phone,
            'raw_upline_text' => $profile['raw_l1'],
            'raw_l1' => $profile['raw_l1'],
            'raw_l2' => $profile['raw_l2'],
            'raw_l3' => $profile['raw_l3'],
            'raw_import_data' => $profile['raw_import_data'],
            'status' => $profile['status'] ?: ($affiliate->status ?: 'active'),
        ]);
        $affiliate->save();

        if ($profile['affiliate_type'] === 'external' && $affiliate->user) {
            $user = $affiliate->user;
            $affiliate->forceFill(['user_id' => null, 'email' => null])->save();
            $user->delete();
            $affiliate->setRelation('user', null);
            $changed = true;
        } elseif ($profile['affiliate_type'] === 'inhouse' && ! $affiliate->user) {
            $temporaryPassword = $this->temporaryPassword();
            $email = strtolower($affiliate->affiliate_code).'@inhouse.local';
            $user = User::create([
                'name' => $affiliate->name,
                'email' => $email,
                'affiliate_code' => $affiliate->affiliate_code,
                'password' => Hash::make($temporaryPassword),
                'must_change_password' => true,
                'role' => 'affiliate',
            ]);
            $affiliate->forceFill(['user_id' => $user->id, 'email' => $email])->save();
            $affiliate->setRelation('user', $user);
            $changed = true;
        } else {
            $affiliate->user?->update([
                'name' => $affiliate->name,
                'affiliate_code' => $affiliate->affiliate_code,
                'role' => 'affiliate',
            ]);
        }

        $identity = $this->identityKey($affiliate->name, (string) $affiliate->group_name, (string) $affiliate->affiliate_type);
        if (! $state['all_affiliates']->contains('id', $affiliate->id)) {
            $state['all_affiliates']->push($affiliate);
        }
        $state['by_identity'][$identity] = [$affiliate];
        $state['by_name_group'][$this->nameGroupKey($affiliate->name, (string) $affiliate->group_name)] = [$affiliate];
        $state['by_code'][strtoupper((string) $affiliate->affiliate_code)] = $affiliate;

        $tiktokAdded = [];
        $tiktokUnchanged = [];
        $tiktokConflicts = [];

        foreach ($profile['usernames'] as $normalized => $display) {
            $existingAccount = $state['accounts_by_username'][$normalized] ?? null;

            if ($existingAccount && (int) $existingAccount->affiliate_id !== (int) $affiliate->id) {
                $tiktokConflicts[] = $display;
                continue;
            }

            if ($existingAccount) {
                if ($existingAccount->status !== 'active') {
                    $existingAccount->update(['status' => 'active']);
                }
                $tiktokUnchanged[] = $display;
                continue;
            }

            $account = $affiliate->tiktokAccounts()->create([
                'username' => $display,
                'username_normalized' => $normalized,
                'status' => 'active',
            ]);
            $state['accounts_by_username'][$normalized] = $account;
            $tiktokAdded[] = $display;
        }

        $status = $created
            ? ($profile['affiliate_type'] === 'external' ? 'External Created' : 'Inhouse Created')
            : ($changed ? 'Profile Updated' : 'Unchanged');

        return [
            'affiliate' => $affiliate,
            'imported_usernames' => array_keys($profile['usernames']),
            'result' => $this->baseResult($profile, [
                'affiliate_id' => $affiliate->id,
                'affiliate_code' => $affiliate->affiliate_code ?: '-',
                'status' => $status,
                'profile_status' => $status,
                'temporary_password' => $temporaryPassword,
                'previous_group' => $previous['group'],
                'new_group' => $affiliate->group_name ?: '-',
                'previous_type' => $previous['type'],
                'new_type' => $affiliate->affiliate_type,
                'previous_upline_id' => $previous['upline_id'],
                'previous_upline' => $previous['upline'],
                'tiktok_added' => $tiktokAdded,
                'tiktok_unchanged' => $tiktokUnchanged,
                'tiktok_conflicts' => $tiktokConflicts,
                'tiktok_status' => $tiktokConflicts !== []
                    ? 'Username Conflict'
                    : ($tiktokAdded !== [] ? 'TikTok Account Added' : 'Already Exists'),
                'error' => $tiktokConflicts !== []
                    ? 'TikTok username already belongs to another affiliate: '.implode(', ', $tiktokConflicts)
                    : '-',
            ]),
        ];
    }

    private function matchAffiliate(array $profile, array $state): array
    {
        if ($profile['affiliate_code'] && isset($state['by_code'][$profile['affiliate_code']])) {
            return ['status' => 'matched', 'affiliate' => $state['by_code'][$profile['affiliate_code']]];
        }

        $identity = $this->identityKey($profile['name'], $profile['group_name'], $profile['affiliate_type']);
        $identityMatches = collect($state['by_identity'][$identity] ?? [])->unique('id')->values();

        if ($identityMatches->count() === 1) {
            return ['status' => 'matched', 'affiliate' => $identityMatches->first()];
        }

        if ($identityMatches->count() > 1) {
            return ['status' => 'ambiguous', 'affiliate' => null, 'remark' => 'Multiple affiliates match the same normalized name, group and type.'];
        }

        $nameGroupMatches = collect($state['by_name_group'][$this->nameGroupKey($profile['name'], $profile['group_name'])] ?? [])
            ->unique('id')
            ->values();

        if ($nameGroupMatches->count() === 1) {
            return ['status' => 'matched', 'affiliate' => $nameGroupMatches->first()];
        }

        if ($nameGroupMatches->count() > 1) {
            return ['status' => 'ambiguous', 'affiliate' => null, 'remark' => 'Multiple affiliates share this normalized name and group.'];
        }

        $usernameOwnerIds = collect(array_keys($profile['usernames']))
            ->map(fn (string $username) => $state['accounts_by_username'][$username]->affiliate_id ?? null)
            ->filter()
            ->unique()
            ->values();

        if ($usernameOwnerIds->count() === 1) {
            $affiliate = $state['all_affiliates']->firstWhere('id', $usernameOwnerIds->first());

            return $affiliate
                ? ['status' => 'matched', 'affiliate' => $affiliate]
                : ['status' => 'none', 'affiliate' => null];
        }

        if ($usernameOwnerIds->count() > 1) {
            return ['status' => 'ambiguous', 'affiliate' => null, 'remark' => 'TikTok usernames point to multiple existing affiliates.'];
        }

        return ['status' => 'none', 'affiliate' => null];
    }

    private function handleMissingAffiliates(array $profiles, array $importedIds, string $strategy, Collection $allAffiliates): array
    {
        $groups = collect($profiles)->pluck('group_name')->unique();
        $missing = $allAffiliates
            ->whereIn('group_name', $groups)
            ->reject(fn (Affiliate $affiliate): bool => in_array($affiliate->id, $importedIds, true));
        $results = [];

        foreach ($missing as $affiliate) {
            if ($strategy === 'inactive') {
                $affiliate->update(['status' => 'inactive']);
            }

            $results[] = [
                'affiliate_id' => $affiliate->id,
                'sheet' => $affiliate->group_name,
                'section' => $affiliate->affiliate_type,
                'name' => $affiliate->name,
                'affiliate_code' => $affiliate->affiliate_code ?: '-',
                'tiktok_username' => '-',
                'raw_l1' => $affiliate->raw_l1 ?: '-',
                'upline_match' => '-',
                'status' => 'Missing From Latest',
                'tiktok_status' => '-',
                'temporary_password' => '-',
                'error' => match ($strategy) {
                    'inactive' => 'Affiliate was missing from the latest file and marked inactive.',
                    'review' => 'Affiliate is missing from the latest file and requires manual review.',
                    default => 'Affiliate was missing from the latest file and kept unchanged.',
                },
                'missing_action' => $strategy,
            ];
        }

        return $results;
    }

    private function summarize(array $results, int $totalRows): array
    {
        $collection = collect($results);

        return [
            'total_rows' => $totalRows,
            'new_created' => $collection->whereIn('profile_status', ['Inhouse Created', 'External Created'])->count(),
            'inhouse_created' => $collection->where('profile_status', 'Inhouse Created')->count(),
            'external_created' => $collection->where('profile_status', 'External Created')->count(),
            'updated' => $collection->where('profile_status', 'Profile Updated')->count(),
            'unchanged' => $collection->where('profile_status', 'Unchanged')->count(),
            'tiktok_added' => $collection->sum(fn (array $result): int => count($result['tiktok_added'] ?? [])),
            'tiktok_unchanged' => $collection->sum(fn (array $result): int => count($result['tiktok_unchanged'] ?? [])),
            'tiktok_marked_inactive' => $collection->sum('tiktok_marked_inactive'),
            'hierarchy_linked' => $collection->whereIn('upline_match', ['Linked', 'Shifted to L2', 'Shifted to L3'])->count(),
            'hierarchy_updated' => $collection->where('hierarchy_changed', true)->count(),
            'hierarchy_unchanged' => $collection->where('hierarchy_changed', false)->count(),
            'previous_upline_replaced' => $collection->where('previous_upline_replaced', true)->count(),
            'self_reference_detected' => $collection->where('self_reference_detected', true)->count(),
            'shifted_to_l2' => $collection->where('shifted_to_l2', true)->count(),
            'shifted_to_l3' => $collection->where('shifted_to_l3', true)->count(),
            'needs_mapping' => $collection->whereIn('upline_match', ['Needs Mapping', 'Needs Review'])->count(),
            'cycle_prevented' => $collection->where('cycle_prevented', true)->count(),
            'username_conflicts' => $collection->where('tiktok_status', 'Username Conflict')->count(),
            'skipped' => $collection->whereIn('status', ['Skipped', 'Skipped Existing', 'Ambiguous Match'])->count(),
            'missing_affiliates' => $collection->where('status', 'Missing From Latest')->count(),
            'missing_columns' => [],
        ];
    }

    private function baseResult(array $profile, array $overrides = []): array
    {
        return array_merge([
            'affiliate_id' => null,
            'sheet' => $profile['group_name'],
            'section' => $profile['affiliate_type'],
            'name' => $profile['name'] ?: '-',
            'affiliate_code' => $profile['affiliate_code'] ?: '-',
            'tiktok_username' => implode(', ', array_values($profile['usernames'])) ?: '-',
            'raw_l1' => $profile['raw_l1'] ?: '-',
            'upline_match' => '-',
            'status' => 'Skipped',
            'profile_status' => 'Skipped',
            'tiktok_status' => '-',
            'temporary_password' => '-',
            'error' => null,
            'previous_group' => '-',
            'new_group' => $profile['group_name'],
            'previous_type' => '-',
            'new_type' => $profile['affiliate_type'],
            'previous_upline' => '-',
            'new_upline' => '-',
            'tiktok_added' => [],
            'tiktok_unchanged' => [],
            'tiktok_conflicts' => [],
            'tiktok_marked_inactive' => 0,
        ], $overrides);
    }

    private function profileChanged(Affiliate $affiliate, array $profile): bool
    {
        return $affiliate->name !== $profile['name']
            || $affiliate->group_name !== $profile['group_name']
            || $affiliate->affiliate_type !== $profile['affiliate_type']
            || $affiliate->raw_l1 !== $profile['raw_l1']
            || $affiliate->raw_l2 !== $profile['raw_l2']
            || $affiliate->raw_l3 !== $profile['raw_l3']
            || ($profile['status'] && $affiliate->status !== $profile['status']);
    }

    private function generateAffiliateCode(string $groupName, array &$state): string
    {
        $prefix = $this->groupPrefix($groupName);
        $latest = (int) ($state['latest_code_number_by_prefix'][$prefix] ?? 0);

        do {
            $latest++;
            $code = sprintf('%s-%04d', $prefix, $latest);
        } while (isset($state['used_codes'][$code]));

        $state['latest_code_number_by_prefix'][$prefix] = $latest;
        $state['used_codes'][$code] = true;

        return $code;
    }

    private function identityKey(string $name, string $group, string $type): string
    {
        return strtolower($this->normalizeDisplayName($group)).'|'.$type.'|'.$this->normalizeName($name);
    }

    private function nameGroupKey(string $name, string $group): string
    {
        return strtolower($this->normalizeDisplayName($group)).'|'.$this->normalizeName($name);
    }

    private function groupPrefix(string $groupName): string
    {
        $normalized = strtolower($groupName);

        return match (true) {
            str_contains($normalized, 'titan') => 'TIT',
            str_contains($normalized, 'aurora') => 'AUR',
            str_contains($normalized, 'swg') => 'SWG',
            str_contains($normalized, 'kaizen') => 'KAI',
            default => strtoupper(substr(preg_replace('/[^a-z0-9]/i', '', $groupName) ?: 'AFF', 0, 3)),
        };
    }

    private function normalizeDisplayName(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value));
    }

    private function normalizeName(string $value): string
    {
        return strtolower($this->normalizeDisplayName($value));
    }

    private function normalizeUsername(string $value): string
    {
        return strtolower(ltrim(trim($value), '@'));
    }

    private function isValidUsername(string $value): bool
    {
        return $value !== ''
            && ! in_array($value, ['-', 'belum scan', 'creators not found', 'creator not found', 'not found', 'n/a', 'na', 'tiada'], true)
            && (bool) preg_match('/^[a-z0-9._-]{2,}$/i', $value);
    }

    private function normalizeStatus(mixed $value): ?string
    {
        $status = strtolower(trim((string) $value));

        return in_array($status, ['active', 'inactive'], true) ? $status : null;
    }

    private function temporaryPassword(): string
    {
        $characters = [
            chr(random_int(65, 90)),
            chr(random_int(97, 122)),
            (string) random_int(0, 9),
            '!@#$%&*'[random_int(0, 6)],
        ];
        $pool = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%&*';

        while (count($characters) < 12) {
            $characters[] = $pool[random_int(0, strlen($pool) - 1)];
        }

        for ($index = count($characters) - 1; $index > 0; $index--) {
            $swap = random_int(0, $index);
            [$characters[$index], $characters[$swap]] = [$characters[$swap], $characters[$index]];
        }

        return implode('', $characters);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
