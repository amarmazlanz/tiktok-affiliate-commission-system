<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\TiktokAccount;
use App\Models\User;
use App\Services\AffiliateHierarchyImportService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AffiliateImportController extends Controller
{
    public function create(): View
    {
        return view('admin.affiliates.import');
    }

    public function store(Request $request, AffiliateHierarchyImportService $hierarchyImporter): View
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:51200'],
        ]);

        $file = $request->file('csv_file');
        $parsedRows = strtolower($file->getClientOriginalExtension()) === 'xlsx'
            ? $this->parseXlsx($file)
            : $this->parseCsv($file);

        $results = [];

        DB::transaction(function () use ($parsedRows, &$results, $hierarchyImporter): void {
            $processedAffiliates = [];
            $profileStatusByKey = [];

            foreach ($parsedRows as $row) {
                $result = $this->importAffiliateRow($row, $processedAffiliates, $profileStatusByKey);
                $results[] = $result;
            }

            $hierarchyImporter->resolve($results);
        });

        $resultCollection = collect($results);

        return view('admin.affiliates.import-result', [
            'results' => $results,
            'summary' => [
                'total_rows' => $resultCollection->count(),
                'inhouse_created' => $resultCollection->where('status', 'Inhouse Created')->count(),
                'external_created' => $resultCollection->where('status', 'External Created')->count(),
                'updated' => $resultCollection->where('status', 'Profile Updated')->count(),
                'tiktok_added' => $resultCollection->where('tiktok_status', 'TikTok Account Added')->count(),
                'hierarchy_linked' => $resultCollection->whereIn('upline_match', ['Linked', 'Shifted to L2', 'Shifted to L3'])->count(),
                'self_reference_detected' => $resultCollection->where('self_reference_detected', true)->count(),
                'shifted_to_l2' => $resultCollection->where('shifted_to_l2', true)->count(),
                'shifted_to_l3' => $resultCollection->where('shifted_to_l3', true)->count(),
                'needs_mapping' => $resultCollection->whereIn('upline_match', ['Needs Mapping', 'Needs Review'])->count(),
                'cycle_prevented' => $resultCollection->where('cycle_prevented', true)->count(),
                'username_conflicts' => $resultCollection->where('tiktok_status', 'Username Conflict')->count(),
                'skipped' => $resultCollection->where('status', 'Skipped')->count(),
                'missing_columns' => [],
            ],
        ]);
    }

    private function importAffiliateRow(array $row, array &$processedAffiliates, array &$profileStatusByKey): array
    {
        $originalName = $this->normalizeDisplayName($row['name'] ?? '');
        $nameNormalized = $this->normalizeName($originalName);
        $groupName = $this->normalizeDisplayName($row['group_name'] ?? 'Unknown Group');
        $affiliateType = ($row['affiliate_type'] ?? 'inhouse') === 'external' ? 'external' : 'inhouse';
        $rawUsername = trim((string) ($row['tiktok_username'] ?? ''));
        $usernameNormalized = $this->normalizeUsername($rawUsername);
        $affiliateKey = strtolower($groupName).'|'.$affiliateType.'|'.$nameNormalized;

        $result = [
            'affiliate_id' => null,
            'sheet' => $groupName,
            'section' => $affiliateType,
            'name' => $originalName ?: '-',
            'affiliate_code' => '-',
            'tiktok_username' => $rawUsername ?: '-',
            'raw_l1' => $this->nullableString($row['raw_l1'] ?? null) ?: '-',
            'upline_match' => '-',
            'status' => 'Skipped',
            'tiktok_status' => '-',
            'temporary_password' => '-',
            'error' => null,
        ];

        if ($originalName === '' || $nameNormalized === '') {
            $result['error'] = 'Name is missing or invalid.';

            return $result;
        }

        $affiliate = $processedAffiliates[$affiliateKey] ?? Affiliate::query()
            ->where('group_name', $groupName)
            ->where('affiliate_type', $affiliateType)
            ->where('name_normalized', $nameNormalized)
            ->first();

        $profileStatus = $profileStatusByKey[$affiliateKey] ?? null;
        $temporaryPassword = '-';

        if (! $affiliate) {
            $affiliateCode = $this->generateAffiliateCode($groupName);
            $user = null;
            $email = null;

            if ($affiliateType === 'inhouse') {
                $temporaryPassword = Str::random(12);
                $email = strtolower($affiliateCode).'@inhouse.local';
                $user = User::create([
                    'name' => $originalName,
                    'email' => $email,
                    'affiliate_code' => $affiliateCode,
                    'password' => Hash::make($temporaryPassword),
                    'role' => 'affiliate',
                ]);
            }

            $affiliate = Affiliate::create([
                'user_id' => $user?->id,
                'affiliate_code' => $affiliateCode,
                'upline_id' => null,
                'group_name' => $groupName,
                'affiliate_type' => $affiliateType,
                'name' => $originalName,
                'name_normalized' => $nameNormalized,
                'email' => $email,
                'phone' => $this->nullableString($row['phone'] ?? null),
                'raw_upline_text' => $this->nullableString($row['raw_l1'] ?? null),
                'raw_l1' => $this->nullableString($row['raw_l1'] ?? null),
                'raw_l2' => $this->nullableString($row['raw_l2'] ?? null),
                'raw_l3' => $this->nullableString($row['raw_l3'] ?? null),
                'raw_import_data' => $row['raw'] ?? [],
                'status' => 'active',
            ]);

            $profileStatus = $affiliateType === 'inhouse' ? 'Inhouse Created' : 'External Created';
            $profileStatusByKey[$affiliateKey] = $profileStatus;
        } else {
            $affiliate->update([
                'name' => $originalName,
                'name_normalized' => $nameNormalized,
                'phone' => $this->nullableString($row['phone'] ?? null) ?? $affiliate->phone,
                'raw_upline_text' => $this->nullableString($row['raw_l1'] ?? null) ?? $affiliate->raw_upline_text,
                'raw_l1' => $this->nullableString($row['raw_l1'] ?? null) ?? $affiliate->raw_l1,
                'raw_l2' => $this->nullableString($row['raw_l2'] ?? null) ?? $affiliate->raw_l2,
                'raw_l3' => $this->nullableString($row['raw_l3'] ?? null) ?? $affiliate->raw_l3,
                'raw_import_data' => $row['raw'] ?? $affiliate->raw_import_data,
                'status' => $affiliate->status ?: 'active',
            ]);

            if ($affiliateType === 'inhouse' && ! $affiliate->user) {
                $temporaryPassword = Str::random(12);
                $user = User::create([
                    'name' => $originalName,
                    'email' => strtolower($affiliate->affiliate_code).'@inhouse.local',
                    'affiliate_code' => $affiliate->affiliate_code,
                    'password' => Hash::make($temporaryPassword),
                    'role' => 'affiliate',
                ]);

                $affiliate->update([
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
            } else {
                $affiliate->user?->update([
                    'name' => $originalName,
                    'affiliate_code' => $affiliate->affiliate_code,
                    'role' => 'affiliate',
                ]);
            }

            $profileStatus ??= 'Profile Updated';
        }

        $processedAffiliates[$affiliateKey] = $affiliate;

        $result['affiliate_id'] = $affiliate->id;
        $result['affiliate_code'] = $affiliate->affiliate_code ?: '-';
        $result['status'] = $profileStatus;
        $result['temporary_password'] = $temporaryPassword;

        if (! $this->isValidUsername($rawUsername)) {
            $result['tiktok_status'] = 'Skipped';
            $result['error'] = 'No valid TikTok username in this row.';

            return $result;
        }

        $existingAccount = TiktokAccount::query()
            ->where('username_normalized', $usernameNormalized)
            ->first();

        if ($existingAccount && (int) $existingAccount->affiliate_id !== (int) $affiliate->id) {
            $result['tiktok_status'] = 'Username Conflict';
            $result['error'] = 'TikTok username already belongs to another affiliate.';

            return $result;
        }

        if ($existingAccount) {
            $result['tiktok_status'] = 'Already Exists';
            $result['error'] = '-';

            return $result;
        }

        $affiliate->tiktokAccounts()->create([
            'username' => ltrim($rawUsername, '@'),
            'username_normalized' => $usernameNormalized,
            'status' => 'active',
        ]);

        $result['tiktok_status'] = 'TikTok Account Added';
        $result['error'] = '-';

        return $result;
    }

    private function resolveHierarchy(array &$results): void
    {
        foreach ($results as &$result) {
            if (! $result['affiliate_id']) {
                continue;
            }

            $affiliate = Affiliate::query()->with('upline.upline')->find($result['affiliate_id']);

            if (! $affiliate) {
                continue;
            }

            $rawL1 = $this->nullableString($affiliate->raw_l1);

            if ($this->isNoUplineValue($rawL1)) {
                $result['upline_match'] = 'No Upline';
                $affiliate->update([
                    'hierarchy_import_status' => 'no_upline',
                    'hierarchy_import_remark' => null,
                ]);
                continue;
            }

            if ($this->aliasMatchesAffiliate((string) $rawL1, $affiliate)) {
                $result['upline_match'] = 'No Upline';
                $affiliate->update([
                    'upline_id' => null,
                    'hierarchy_import_status' => 'self_reference_no_upline',
                    'hierarchy_import_remark' => 'L1 value matches the affiliate name and was treated as no upline.',
                ]);
                continue;
            }

            $match = $this->resolveAffiliateAlias((string) $rawL1, (string) $affiliate->group_name, $affiliate->id);

            if ($match['status'] !== 'matched') {
                $result['upline_match'] = 'Needs Mapping';
                $result['status'] = 'Needs Mapping';
                $result['error'] = $match['remark'];
                $affiliate->update([
                    'hierarchy_import_status' => 'needs_mapping',
                    'hierarchy_import_remark' => $match['remark'],
                ]);
                continue;
            }

            $upline = $match['affiliate'];
            $affiliate->update([
                'upline_id' => $upline->id,
                'hierarchy_import_status' => 'linked',
                'hierarchy_import_remark' => null,
            ]);

            $result['upline_match'] = 'Linked';

            $validationRemark = $this->validateL2L3($affiliate->fresh('upline.upline'), $result);

            if ($validationRemark) {
                $result['upline_match'] = 'Needs Review';
                $result['status'] = 'Needs Review';
                $result['error'] = $validationRemark;
                $affiliate->update([
                    'hierarchy_import_status' => 'needs_review',
                    'hierarchy_import_remark' => $validationRemark,
                ]);
            }
        }
    }

    private function validateL2L3(Affiliate $affiliate, array $result): ?string
    {
        $rawL2 = $this->nullableString($affiliate->raw_l2);
        $rawL3 = $this->nullableString($affiliate->raw_l3);

        if (! $this->isNoUplineValue($rawL2)) {
            $l2Match = $this->resolveAffiliateAlias((string) $rawL2, (string) $affiliate->group_name, $affiliate->id);

            if ($l2Match['status'] !== 'matched') {
                return 'L2 needs mapping: '.$rawL2;
            }

            if ($affiliate->upline?->upline_id && (int) $affiliate->upline->upline_id !== (int) $l2Match['affiliate']->id) {
                return 'L2 does not match the selected L1 upline chain.';
            }
        }

        if (! $this->isNoUplineValue($rawL3) && $affiliate->upline?->upline) {
            $l3Match = $this->resolveAffiliateAlias((string) $rawL3, (string) $affiliate->group_name, $affiliate->id);

            if ($l3Match['status'] !== 'matched') {
                return 'L3 needs mapping: '.$rawL3;
            }

            if ($affiliate->upline->upline?->upline_id && (int) $affiliate->upline->upline->upline_id !== (int) $l3Match['affiliate']->id) {
                return 'L3 does not match the selected upline chain.';
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

        $aliasTokens = collect(explode(' ', $normalizedAlias))
            ->filter(fn (string $token): bool => strlen($token) >= 3)
            ->values()
            ->all();

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

        $aliasTokens = collect(explode(' ', $normalizedAlias))
            ->filter(fn (string $token): bool => strlen($token) >= 3)
            ->values();

        return $aliasTokens->isNotEmpty()
            && $aliasTokens->every(fn (string $token): bool => str_contains(' '.$candidate.' ', ' '.$token.' '));
    }

    private function parseXlsx(UploadedFile $file): array
    {
        $zipEntries = $this->readZipEntries((string) $file->getRealPath());

        if ($zipEntries === []) {
            return [];
        }

        $sharedStrings = $this->readSharedStrings($zipEntries);
        $workbookXml = $zipEntries['xl/workbook.xml'] ?? null;
        $relationshipsXml = $zipEntries['xl/_rels/workbook.xml.rels'] ?? null;

        if (! $workbookXml || ! $relationshipsXml) {
            return [];
        }

        $workbook = simplexml_load_string((string) $workbookXml);
        $relationships = simplexml_load_string((string) $relationshipsXml);
        $relationshipTargets = [];

        foreach ($relationships->Relationship ?? [] as $relationship) {
            $relationshipTargets[(string) $relationship['Id']] = 'xl/'.ltrim((string) $relationship['Target'], '/');
        }

        $rows = [];

        foreach ($workbook->sheets->sheet ?? [] as $sheet) {
            $sheetName = $this->normalizeDisplayName((string) $sheet['name']);
            $attributes = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $target = $relationshipTargets[(string) $attributes['id']] ?? null;
            $sheetContent = $target ? ($zipEntries[$target] ?? null) : null;

            if (! $sheetContent) {
                continue;
            }

            $sheetXml = simplexml_load_string((string) $sheetContent);
            $rows = array_merge($rows, $this->extractAffiliateRows($sheetName, $this->readSheetRows($sheetXml, $sharedStrings)));
        }

        return $rows;
    }

    private function extractAffiliateRows(string $groupName, array $sheetRows): array
    {
        $rows = [];
        $section = null;
        $headers = [];
        $lastProfile = [];

        foreach ($sheetRows as $row) {
            $detectedSection = $this->detectSection($row);

            if ($detectedSection) {
                $section = $detectedSection;
                $headers = [];
                $lastProfile = [];
                continue;
            }

            if ($this->isManagementHeader($row)) {
                $headers = collect($row)->mapWithKeys(fn ($value, $index) => [$index => $this->normalizeHeader((string) $value)])->all();
                continue;
            }

            if (! $section || $headers === []) {
                continue;
            }

            $mapped = $this->mapManagementRow($row, $headers);

            if ($mapped['name'] === '' && ($mapped['tiktok_username'] ?? '') !== '' && $lastProfile !== []) {
                $mapped = array_merge($mapped, Arr::only($lastProfile, ['name', 'phone', 'raw_l1', 'raw_l2', 'raw_l3']));
            }

            if ($mapped['name'] === '') {
                continue;
            }

            $lastProfile = Arr::only($mapped, ['name', 'phone', 'raw_l1', 'raw_l2', 'raw_l3']);
            $usernames = $this->splitUsernames($mapped['tiktok_username'] ?? '');

            if ($usernames === []) {
                $usernames = [''];
            }

            foreach ($usernames as $username) {
                $rows[] = [
                    'group_name' => $groupName,
                    'affiliate_type' => $section,
                    'name' => $mapped['name'],
                    'phone' => $mapped['phone'] ?? '',
                    'tiktok_username' => $username,
                    'raw_l1' => $mapped['raw_l1'] ?? '',
                    'raw_l2' => $mapped['raw_l2'] ?? '',
                    'raw_l3' => $mapped['raw_l3'] ?? '',
                    'raw' => $row,
                ];
            }
        }

        return $rows;
    }

    private function mapManagementRow(array $row, array $headers): array
    {
        return [
            'name' => $this->firstValue($row, $this->findColumns($headers, ['nama penuh', 'name', 'nama'])),
            'phone' => $this->firstValue($row, $this->findColumns($headers, ['phone', 'telefon', 'whatsapp', 'contact'])),
            'tiktok_username' => $this->firstValue($row, $this->findColumns($headers, ['user id tiktok', 'tiktok', 'username'])),
            'raw_l1' => $this->firstValue($row, $this->findColumns($headers, ['manager (l1)', 'manager l1', 'l1', 'manager'], ['senior', 'general'])),
            'raw_l2' => $this->firstValue($row, $this->findColumns($headers, ['senior manager (l2)', 'senior manager', 'l2'])),
            'raw_l3' => $this->firstValue($row, $this->findColumns($headers, ['general manager (l3)', 'general manager', 'l3'])),
        ];
    }

    private function parseCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($handle) ?: [];
        $headers = array_map(fn ($column): string => $this->normalizeHeader((string) $column), $header);
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            $row = array_combine($headers, array_slice(array_pad($data, count($headers), ''), 0, count($headers))) ?: [];

            foreach ($this->splitUsernames($row['user id tiktok'] ?? $row['tiktok_username'] ?? $row['username'] ?? '') ?: [''] as $username) {
                $rows[] = [
                    'group_name' => $this->normalizeDisplayName((string) ($row['group_name'] ?? $row['group'] ?? 'CSV Import')),
                    'affiliate_type' => strtolower((string) ($row['affiliate_type'] ?? $row['type'] ?? 'inhouse')) === 'external' ? 'external' : 'inhouse',
                    'name' => $row['nama penuh'] ?? $row['name'] ?? $row['nama'] ?? '',
                    'phone' => $row['phone'] ?? '',
                    'tiktok_username' => $username,
                    'raw_l1' => $row['manager (l1)'] ?? $row['manager'] ?? $row['l1'] ?? '',
                    'raw_l2' => $row['senior manager (l2)'] ?? $row['senior manager'] ?? $row['l2'] ?? '',
                    'raw_l3' => $row['general manager (l3)'] ?? $row['general manager'] ?? $row['l3'] ?? '',
                    'raw' => $row,
                ];
            }
        }

        fclose($handle);

        return $rows;
    }

    private function readZipEntries(string $path): array
    {
        $data = file_get_contents($path);

        if ($data === false || ! function_exists('gzinflate')) {
            return [];
        }

        $endOfCentralDirectory = strrpos($data, "\x50\x4b\x05\x06");

        if ($endOfCentralDirectory === false) {
            return [];
        }

        $centralDirectorySize = $this->unpackLong(substr($data, $endOfCentralDirectory + 12, 4));
        $centralDirectoryOffset = $this->unpackLong(substr($data, $endOfCentralDirectory + 16, 4));
        $offset = $centralDirectoryOffset;
        $entries = [];

        while ($offset < $centralDirectoryOffset + $centralDirectorySize && substr($data, $offset, 4) === "\x50\x4b\x01\x02") {
            $method = $this->unpackShort(substr($data, $offset + 10, 2));
            $compressedSize = $this->unpackLong(substr($data, $offset + 20, 4));
            $nameLength = $this->unpackShort(substr($data, $offset + 28, 2));
            $extraLength = $this->unpackShort(substr($data, $offset + 30, 2));
            $commentLength = $this->unpackShort(substr($data, $offset + 32, 2));
            $localHeaderOffset = $this->unpackLong(substr($data, $offset + 42, 4));
            $name = str_replace('\\', '/', substr($data, $offset + 46, $nameLength));

            if (substr($data, $localHeaderOffset, 4) !== "\x50\x4b\x03\x04") {
                $offset += 46 + $nameLength + $extraLength + $commentLength;
                continue;
            }

            $localNameLength = $this->unpackShort(substr($data, $localHeaderOffset + 26, 2));
            $localExtraLength = $this->unpackShort(substr($data, $localHeaderOffset + 28, 2));
            $compressed = substr($data, $localHeaderOffset + 30 + $localNameLength + $localExtraLength, $compressedSize);
            $content = match ($method) {
                0 => $compressed,
                8 => gzinflate($compressed),
                default => false,
            };

            if ($content !== false) {
                $entries[$name] = $content;
            }

            $offset += 46 + $nameLength + $extraLength + $commentLength;
        }

        return $entries;
    }

    private function readSharedStrings(array $zipEntries): array
    {
        $xml = $zipEntries['xl/sharedStrings.xml'] ?? null;

        if (! $xml) {
            return [];
        }

        $sharedStrings = [];
        $strings = simplexml_load_string((string) $xml);

        foreach ($strings->si ?? [] as $string) {
            $parts = [];

            if (isset($string->t)) {
                $parts[] = (string) $string->t;
            }

            foreach ($string->r ?? [] as $run) {
                $parts[] = (string) $run->t;
            }

            $sharedStrings[] = implode('', $parts);
        }

        return $sharedStrings;
    }

    private function readSheetRows(\SimpleXMLElement $sheetXml, array $sharedStrings): array
    {
        $rows = [];

        foreach ($sheetXml->sheetData->row ?? [] as $row) {
            $cells = [];

            foreach ($row->c ?? [] as $cell) {
                $column = preg_replace('/\d+/', '', (string) $cell['r']);
                $index = $this->columnToIndex($column);
                $type = (string) $cell['t'];
                $value = (string) ($cell->v ?? '');

                if ($type === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string) ($cell->is->t ?? '');
                }

                $cells[$index] = trim($value);
            }

            if (count(array_filter($cells, fn ($value) => trim((string) $value) !== '')) > 0) {
                ksort($cells);
                $rows[] = $cells;
            }
        }

        return $rows;
    }

    private function detectSection(array $row): ?string
    {
        $text = strtolower(implode(' ', array_map(fn ($value) => trim((string) $value), $row)));

        if (str_contains($text, 'affiliate luar')) {
            return 'external';
        }

        if (str_contains($text, 'inhouse')) {
            return 'inhouse';
        }

        return null;
    }

    private function isManagementHeader(array $row): bool
    {
        $text = strtolower(implode(' ', array_map(fn ($value) => trim((string) $value), $row)));

        return str_contains($text, 'nama penuh') && (str_contains($text, 'tiktok') || str_contains($text, 'user id'));
    }

    private function splitUsernames(string $value): array
    {
        return collect(preg_split('/[\s,;|]+/', $value) ?: [])
            ->map(fn (string $username): string => trim($username))
            ->filter(fn (string $username): bool => $this->isValidUsername($username))
            ->unique(fn (string $username): string => $this->normalizeUsername($username))
            ->values()
            ->all();
    }

    private function isValidUsername(string $username): bool
    {
        $value = strtolower(trim($username));
        $value = ltrim($value, '@');

        if ($value === '' || in_array($value, ['-', 'belum scan', 'creators not found', 'creator not found', 'not found', 'n/a', 'na', 'tiada'], true)) {
            return false;
        }

        if (str_contains($value, ' ') && ! str_contains($value, '_')) {
            return false;
        }

        return (bool) preg_match('/^[a-z0-9._-]{2,}$/i', $value);
    }

    private function generateAffiliateCode(string $groupName): string
    {
        $prefix = $this->groupPrefix($groupName);
        $latest = Affiliate::query()
            ->where('affiliate_code', 'like', $prefix.'-%')
            ->pluck('affiliate_code')
            ->map(fn ($code): int => (int) Str::after((string) $code, $prefix.'-'))
            ->max() ?? 0;

        do {
            $latest++;
            $code = sprintf('%s-%04d', $prefix, $latest);
        } while (Affiliate::query()->where('affiliate_code', $code)->exists() || User::query()->where('affiliate_code', $code)->exists());

        return $code;
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

    private function normalizeUsername(string $username): string
    {
        return strtolower(ltrim(trim($username), '@'));
    }

    private function normalizeName(string $name): string
    {
        return strtolower($this->normalizeDisplayName($name));
    }

    private function normalizeAlias(string $name): string
    {
        $value = strtolower($this->normalizeDisplayName($name));
        $value = preg_replace('/^(puan|en|encik|cik|tuan)\.?\s+/i', '', $value);
        $value = preg_replace('/[^a-z0-9\s]/i', ' ', (string) $value);

        return trim(preg_replace('/\s+/', ' ', (string) $value));
    }

    private function normalizeDisplayName(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', $name));
    }

    private function normalizeHeader(string $header): string
    {
        return strtolower(trim(str_replace("\xEF\xBB\xBF", '', $header)));
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

    private function findColumns(array $headers, array $include, array $exclude = []): array
    {
        return collect($headers)
            ->filter(function (string $header) use ($include, $exclude): bool {
                $matched = collect($include)->contains(fn (string $keyword): bool => str_contains($header, $keyword));
                $excluded = collect($exclude)->contains(fn (string $keyword): bool => str_contains($header, $keyword));

                return $matched && ! $excluded;
            })
            ->keys()
            ->all();
    }

    private function firstValue(array $row, array $columns): string
    {
        foreach ($columns as $column) {
            $value = trim((string) Arr::get($row, $column, ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function unpackShort(string $bytes): int
    {
        return unpack('v', $bytes)[1] ?? 0;
    }

    private function unpackLong(string $bytes): int
    {
        return unpack('V', $bytes)[1] ?? 0;
    }

    private function columnToIndex(string $column): int
    {
        $index = 0;

        foreach (str_split($column) as $char) {
            $index = $index * 26 + (ord(strtoupper($char)) - 64);
        }

        return $index - 1;
    }
}
