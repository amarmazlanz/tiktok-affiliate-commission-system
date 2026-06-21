<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\TiktokAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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

    public function store(Request $request): View
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $parsed = $this->parseCsv($request->file('csv_file'));

        if (! empty($parsed['missing_columns'])) {
            return view('admin.affiliates.import-result', [
                'results' => [],
                'summary' => [
                    'total_rows' => 0,
                    'created' => 0,
                    'updated' => 0,
                    'tiktok_added' => 0,
                    'already_exists' => 0,
                    'skipped' => 0,
                    'missing_columns' => $parsed['missing_columns'],
                ],
            ]);
        }

        $results = [];
        $validRows = [];
        $emailsInCsv = collect($parsed['rows'])
            ->pluck('email')
            ->filter()
            ->map(fn (string $email): string => strtolower(trim($email)))
            ->all();
        $emailLookup = array_flip($emailsInCsv);
        $seenTiktokUsernames = [];
        $emailUplines = [];

        foreach ($parsed['rows'] as $row) {
            $result = [
                'row' => $row['row_number'],
                'name' => $row['name'],
                'email' => $row['email'],
                'status' => 'Skipped',
                'temporary_password' => '-',
                'error' => null,
            ];

            $email = strtolower(trim($row['email']));
            $uplineEmail = strtolower(trim($row['upline_email']));
            $normalizedUsername = $this->normalizeUsername($row['tiktok_username']);

            if ($row['name'] === '' || $email === '' || $row['tiktok_username'] === '') {
                $result['error'] = 'Name, email, and tiktok_username are required.';
                $results[] = $result;
                continue;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $result['error'] = 'Invalid email format.';
                $results[] = $result;
                continue;
            }

            $existingUser = User::query()->where('email', $email)->first();
            if ($existingUser && $existingUser->role === 'admin') {
                $result['error'] = 'This email belongs to an admin user and cannot be imported as affiliate.';
                $results[] = $result;
                continue;
            }

            if (isset($emailUplines[$email]) && $emailUplines[$email] !== $uplineEmail) {
                $result['error'] = 'Repeated email has conflicting upline_email.';
                $results[] = $result;
                continue;
            }

            $emailUplines[$email] = $uplineEmail;

            if ($uplineEmail !== '' && ! isset($emailLookup[$uplineEmail]) && ! Affiliate::query()->where('email', $uplineEmail)->exists()) {
                $result['error'] = 'Upline email not found.';
                $results[] = $result;
                continue;
            }

            if ($uplineEmail !== '' && $uplineEmail === $email) {
                $result['error'] = 'Affiliate cannot use own email as upline.';
                $results[] = $result;
                continue;
            }

            if (isset($seenTiktokUsernames[$normalizedUsername]) && $seenTiktokUsernames[$normalizedUsername] !== $email) {
                $result['error'] = 'TikTok username is duplicated in this CSV.';
                $results[] = $result;
                continue;
            }

            $existingTiktokAccount = TiktokAccount::query()
                ->where('username_normalized', $normalizedUsername)
                ->with('affiliate')
                ->first();

            if ($existingTiktokAccount && strtolower($existingTiktokAccount->affiliate?->email ?? '') !== $email) {
                $result['error'] = 'TikTok username already belongs to another affiliate.';
                $results[] = $result;
                continue;
            }

            $seenTiktokUsernames[$normalizedUsername] = $email;
            $validRows[] = [
                'email' => $email,
                'row' => $row,
                'result_index' => count($results),
                'normalized_username' => $normalizedUsername,
                'is_new_user' => ! $existingUser,
            ];
            $results[] = $result;
        }

        $validEmails = array_fill_keys(array_column($validRows, 'email'), true);
        do {
            $removedInvalidUpline = false;

            foreach ($validRows as $key => $payload) {
                $email = $payload['email'];
                $uplineEmail = strtolower(trim($payload['row']['upline_email']));

                if ($uplineEmail === '') {
                    continue;
                }

                $uplineAlreadyExists = Affiliate::query()->where('email', $uplineEmail)->exists();

                if (! $uplineAlreadyExists && ! isset($validEmails[$uplineEmail])) {
                    $results[$payload['result_index']]['status'] = 'Skipped';
                    $results[$payload['result_index']]['temporary_password'] = '-';
                    $results[$payload['result_index']]['error'] = 'Upline email not found or skipped.';
                    unset($validRows[$key], $validEmails[$email]);
                    $removedInvalidUpline = true;
                }
            }
        } while ($removedInvalidUpline);

        DB::transaction(function () use (&$validRows, &$results): void {
            $processedAffiliates = [];

            foreach ($validRows as &$payload) {
                $email = $payload['email'];
                $row = $payload['row'];
                $temporaryPassword = '-';
                $firstRowForEmail = ! isset($processedAffiliates[$email]);

                if ($firstRowForEmail) {
                    $user = User::query()->where('email', $email)->first();
                    if (! $user) {
                        $temporaryPassword = Str::random(12);
                        $user = User::create([
                            'name' => $row['name'],
                            'email' => $email,
                            'password' => Hash::make($temporaryPassword),
                            'role' => 'affiliate',
                        ]);
                    } else {
                        $user->update([
                            'name' => $row['name'],
                            'role' => 'affiliate',
                        ]);
                    }

                    $affiliate = Affiliate::query()->where('email', $email)->first();
                    if (! $affiliate) {
                        $affiliate = Affiliate::create([
                            'user_id' => $user->id,
                            'upline_id' => null,
                            'name' => $row['name'],
                            'email' => $email,
                            'phone' => $row['phone'] ?: null,
                            'status' => 'active',
                        ]);
                    } else {
                        $affiliate->update([
                            'user_id' => $user->id,
                            'name' => $row['name'],
                            'email' => $email,
                            'phone' => $row['phone'] ?: null,
                            'status' => 'active',
                        ]);
                    }

                    $processedAffiliates[$email] = [
                        'affiliate' => $affiliate,
                        'is_new_user' => $payload['is_new_user'],
                        'temporary_password' => $temporaryPassword,
                    ];
                } else {
                    $affiliate = $processedAffiliates[$email]['affiliate'];
                }

                $existingTiktokAccount = TiktokAccount::query()
                    ->where('username_normalized', $payload['normalized_username'])
                    ->first();

                if ($existingTiktokAccount) {
                    $status = 'Already Exists';
                } else {
                    $affiliate->tiktokAccounts()->create([
                        'username' => $row['tiktok_username'],
                        'username_normalized' => $payload['normalized_username'],
                        'status' => 'active',
                    ]);

                    if (! $firstRowForEmail) {
                        $status = 'TikTok Account Added';
                    } elseif ($processedAffiliates[$email]['is_new_user']) {
                        $status = 'Created';
                    } else {
                        $status = 'Updated';
                    }
                }

                $payload['affiliate'] = $affiliate;
                $payload['status'] = $status;
                $payload['temporary_password'] = $firstRowForEmail
                    ? $processedAffiliates[$email]['temporary_password']
                    : '-';
            }
            unset($payload);

            $hierarchyUpdated = [];

            foreach ($validRows as $payload) {
                $email = $payload['email'];

                if (isset($hierarchyUpdated[$email])) {
                    $results[$payload['result_index']]['status'] = $payload['status'];
                    $results[$payload['result_index']]['temporary_password'] = $payload['temporary_password'];
                    $results[$payload['result_index']]['error'] = '-';
                    continue;
                }

                $uplineEmail = strtolower(trim($payload['row']['upline_email']));
                $upline = $uplineEmail === ''
                    ? null
                    : Affiliate::query()->where('email', $uplineEmail)->first();

                $payload['affiliate']->update([
                    'upline_id' => $upline?->id,
                ]);

                $hierarchyUpdated[$email] = true;

                $results[$payload['result_index']]['status'] = $payload['status'];
                $results[$payload['result_index']]['temporary_password'] = $payload['temporary_password'];
                $results[$payload['result_index']]['error'] = '-';
            }
        });

        return view('admin.affiliates.import-result', [
            'results' => $results,
            'summary' => [
                'total_rows' => count($results),
                'created' => collect($results)->where('status', 'Created')->count(),
                'updated' => collect($results)->where('status', 'Updated')->count(),
                'tiktok_added' => collect($results)->where('status', 'TikTok Account Added')->count(),
                'already_exists' => collect($results)->where('status', 'Already Exists')->count(),
                'skipped' => collect($results)->where('status', 'Skipped')->count(),
                'missing_columns' => [],
            ],
        ]);
    }

    private function parseCsv(UploadedFile $file): array
    {
        $requiredColumns = ['name', 'email', 'phone', 'tiktok_username', 'upline_email'];
        $handle = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($handle);

        if (! $header) {
            return ['rows' => [], 'missing_columns' => $requiredColumns];
        }

        $header = array_map(fn ($column): string => $this->normalizeHeader((string) $column), $header);
        $missingColumns = array_values(array_diff($requiredColumns, $header));

        if (! empty($missingColumns)) {
            fclose($handle);

            return ['rows' => [], 'missing_columns' => $missingColumns];
        }

        $rows = [];
        $rowNumber = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if (count(array_filter($data, fn ($value): bool => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $row = array_combine($header, array_slice(array_pad($data, count($header), ''), 0, count($header)));
            $rows[] = [
                'row_number' => $rowNumber,
                'name' => trim((string) ($row['name'] ?? '')),
                'email' => strtolower(trim((string) ($row['email'] ?? ''))),
                'phone' => trim((string) ($row['phone'] ?? '')),
                'tiktok_username' => trim((string) ($row['tiktok_username'] ?? '')),
                'upline_email' => strtolower(trim((string) ($row['upline_email'] ?? ''))),
            ];
        }

        fclose($handle);

        return ['rows' => $rows, 'missing_columns' => []];
    }

    private function normalizeHeader(string $header): string
    {
        return strtolower(trim(str_replace("\xEF\xBB\xBF", '', $header)));
    }

    private function normalizeUsername(string $username): string
    {
        return str_replace('@', '', strtolower(trim($username)));
    }
}
