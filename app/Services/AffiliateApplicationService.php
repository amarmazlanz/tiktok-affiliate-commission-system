<?php

namespace App\Services;

use App\Models\AffiliateApplication;
use App\Models\AffiliateReferral;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AffiliateApplicationService
{
    public function normalizeName(string $value): string
    {
        return Str::lower(preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value));
    }

    public function normalizeNric(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    public function normalizePhone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (str_starts_with($digits, '0')) {
            return '6'.$digits;
        }

        return $digits;
    }

    public function isValidMalaysianMobile(string $value): bool
    {
        $normalized = $this->normalizePhone($value);
        $local = str_starts_with($normalized, '60')
            ? '0'.substr($normalized, 2)
            : $normalized;

        return (bool) preg_match('/^01[0-9][0-9]{7,8}$/', $local);
    }

    public function normalizeEmail(string $value): string
    {
        return Str::lower(trim($value));
    }

    public function normalizeTiktok(string $value): string
    {
        return Str::lower(ltrim(trim($value), '@'));
    }

    public function nricHash(string $normalizedNric): string
    {
        return hash_hmac('sha256', $normalizedNric, (string) config('app.key'));
    }

    public function maskNric(string $normalizedNric): string
    {
        return substr($normalizedNric, 0, 2).'****-**-'.substr($normalizedNric, -4);
    }

    public function fingerprint(
        int $referrerId,
        string $nricHash,
        string $normalizedEmail,
        string $normalizedTiktok,
    ): string {
        return hash('sha256', implode('|', [$referrerId, $nricHash, $normalizedEmail, $normalizedTiktok]));
    }

    public function duplicateAssessment(array $normalized): array
    {
        $flags = [];
        $warnings = [];

        if (AffiliateApplication::query()->where('nric_hash', $normalized['nric_hash'])->exists()) {
            $flags[] = 'NRIC matches an existing application.';
        }

        if (
            DB::table('tiktok_accounts')->where('username_normalized', $normalized['tiktok'])->exists()
            || AffiliateApplication::query()
                ->where(fn ($query) => $query
                    ->where('normalized_tiktok_username', $normalized['tiktok'])
                    ->orWhere('normalized_additional_tiktok_username', $normalized['tiktok']))
                ->exists()
        ) {
            $flags[] = 'TikTok username matches an existing record.';
        }

        if ($normalized['additional_tiktok'] && (
            DB::table('tiktok_accounts')->where('username_normalized', $normalized['additional_tiktok'])->exists()
            || AffiliateApplication::query()
                ->where(fn ($query) => $query
                    ->where('normalized_tiktok_username', $normalized['additional_tiktok'])
                    ->orWhere('normalized_additional_tiktok_username', $normalized['additional_tiktok']))
                ->exists()
        )) {
            $flags[] = 'Additional TikTok username matches an existing record.';
        }

        $localPhone = str_starts_with($normalized['phone'], '60')
            ? '0'.substr($normalized['phone'], 2)
            : $normalized['phone'];
        $cleanPhoneSql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '')";

        if (
            DB::table('affiliates')
                ->whereNotNull('phone')
                ->where(fn ($query) => $query
                    ->whereRaw($cleanPhoneSql.' = ?', [$normalized['phone']])
                    ->orWhereRaw($cleanPhoneSql.' = ?', [$localPhone]))
                ->exists()
            || AffiliateApplication::query()->where('normalized_phone', $normalized['phone'])->exists()
        ) {
            $flags[] = 'Phone number matches an existing record.';
        }

        if (
            DB::table('affiliates')->whereRaw('LOWER(email) = ?', [$normalized['email']])->exists()
            || DB::table('users')->whereRaw('LOWER(email) = ?', [$normalized['email']])->exists()
            || AffiliateApplication::query()->where('normalized_email', $normalized['email'])->exists()
        ) {
            $flags[] = 'Email address matches an existing record.';
        }

        if (
            DB::table('affiliates')
                ->where(fn ($query) => $query
                    ->where('name_normalized', $normalized['name'])
                    ->orWhereRaw('LOWER(name) = ?', [$normalized['name']]))
                ->exists()
            || AffiliateApplication::query()->where('normalized_name', $normalized['name'])->exists()
        ) {
            $warnings[] = 'A similar normalized name already exists.';
        }

        return [
            'status' => $flags === [] ? 'pending' : 'duplicate_review',
            'duplicate_status' => $flags === [] ? ($warnings === [] ? 'clear' : 'name_warning') : 'potential_duplicate',
            'duplicate_notes' => implode("\n", [...$flags, ...$warnings]) ?: null,
        ];
    }

    public function create(AffiliateReferral $referral, array $data): AffiliateApplication
    {
        $nric = $this->normalizeNric($data['nric']);
        $normalized = [
            'name' => $this->normalizeName($data['full_name']),
            'nric_hash' => $this->nricHash($nric),
            'phone' => $this->normalizePhone($data['phone']),
            'email' => $this->normalizeEmail($data['email']),
            'tiktok' => $this->normalizeTiktok($data['tiktok_username']),
            'additional_tiktok' => empty($data['additional_tiktok_username'])
                ? null
                : $this->normalizeTiktok($data['additional_tiktok_username']),
        ];
        $fingerprint = $this->fingerprint(
            $referral->affiliate_id,
            $normalized['nric_hash'],
            $normalized['email'],
            $normalized['tiktok'],
        );
        $existing = AffiliateApplication::query()->where('submission_fingerprint', $fingerprint)->first();

        if ($existing) {
            return $existing;
        }

        $duplicate = $this->duplicateAssessment($normalized);

        try {
            return AffiliateApplication::query()->create([
                'application_reference' => $this->uniqueReference(),
                'referral_code' => $referral->referral_code,
                'referrer_affiliate_id' => $referral->affiliate_id,
                'proposed_upline_id' => $referral->affiliate_id,
                'proposed_group_name' => $referral->affiliate->group_name,
                'proposed_affiliate_type' => in_array($data['proposed_affiliate_type'] ?? null, ['inhouse', 'online'], true)
                    ? $data['proposed_affiliate_type']
                    : null,
                'full_name' => trim($data['full_name']),
                'normalized_name' => $normalized['name'],
                'nric_encrypted' => $nric,
                'nric_hash' => $normalized['nric_hash'],
                'masked_nric' => $this->maskNric($nric),
                'phone' => trim($data['phone']),
                'normalized_phone' => $normalized['phone'],
                'email' => trim($data['email']),
                'normalized_email' => $normalized['email'],
                'tiktok_username' => ltrim(trim($data['tiktok_username']), '@'),
                'normalized_tiktok_username' => $normalized['tiktok'],
                'additional_tiktok_username' => empty($data['additional_tiktok_username'])
                    ? null
                    : ltrim(trim($data['additional_tiktok_username']), '@'),
                'normalized_additional_tiktok_username' => $normalized['additional_tiktok'],
                'notes' => $data['notes'] ?? null,
                ...$duplicate,
                'submission_fingerprint' => $fingerprint,
                'consent_at' => now(),
                'submitted_at' => now(),
            ]);
        } catch (QueryException $exception) {
            $existing = AffiliateApplication::query()->where('submission_fingerprint', $fingerprint)->first();

            if ($existing) {
                return $existing;
            }

            throw $exception;
        }
    }

    private function uniqueReference(): string
    {
        do {
            $reference = 'APP-'.now()->year.'-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (AffiliateApplication::query()->where('application_reference', $reference)->exists());

        return $reference;
    }
}
