<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\AffiliateApplication;
use App\Models\TiktokAccount;
use App\Models\User;
use App\Services\AffiliateApplicationService;
use App\Services\AffiliateReferralService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AffiliateRegistrationController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['needs_action', 'pending', 'duplicate_review', 'approved', 'rejected', 'all'])],
            'group' => ['nullable', 'string', 'max:255'],
            'referrer' => ['nullable', 'integer', 'exists:affiliates,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', Rule::in(['25', '50', '100'])],
        ]);
        $statusFilter = $filters['status'] ?? 'needs_action';

        $applications = AffiliateApplication::query()
            ->select([
                'id',
                'application_reference',
                'referrer_affiliate_id',
                'proposed_upline_id',
                'proposed_group_name',
                'full_name',
                'masked_nric',
                'phone',
                'email',
                'tiktok_username',
                'status',
                'submitted_at',
                'approved_affiliate_id',
            ])
            ->with([
                'referrer:id,name,affiliate_code',
                'proposedUpline:id,name,affiliate_code',
                'approvedAffiliate:id,name,affiliate_code',
            ])
            ->when(filled($filters['search'] ?? null), function (Builder $query) use ($filters): void {
                $search = trim((string) $filters['search']);
                $normalizedTiktok = strtolower(ltrim($search, '@'));

                $query->where(function (Builder $query) use ($search, $normalizedTiktok): void {
                    $query->where('application_reference', 'like', '%'.$search.'%')
                        ->orWhere('full_name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('tiktok_username', 'like', '%'.$search.'%')
                        ->orWhere('normalized_tiktok_username', 'like', '%'.$normalizedTiktok.'%');
                });
            })
            ->when($statusFilter === 'needs_action', fn (Builder $query) => $query->whereIn('status', ['pending', 'duplicate_review']))
            ->when(in_array($statusFilter, ['pending', 'duplicate_review', 'approved', 'rejected'], true), fn (Builder $query) => $query->where('status', $statusFilter))
            ->when(filled($filters['group'] ?? null), fn (Builder $query) => $query->where('proposed_group_name', $filters['group']))
            ->when(filled($filters['referrer'] ?? null), function (Builder $query) use ($filters): void {
                $query->where(function (Builder $query) use ($filters): void {
                    $query->where('referrer_affiliate_id', $filters['referrer'])
                        ->orWhere('proposed_upline_id', $filters['referrer']);
                });
            })
            ->when(filled($filters['from'] ?? null), fn (Builder $query) => $query->whereDate('submitted_at', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn (Builder $query) => $query->whereDate('submitted_at', '<=', $filters['to']))
            ->latest('submitted_at')
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->withQueryString();

        return view('admin.affiliate-registrations.index', [
            'applications' => $applications,
            'filters' => $filters,
            'statusFilter' => $statusFilter,
            'groups' => AffiliateApplication::query()
                ->whereNotNull('proposed_group_name')
                ->distinct()
                ->orderBy('proposed_group_name')
                ->pluck('proposed_group_name'),
            'referrers' => Affiliate::query()
                ->select(['id', 'name', 'affiliate_code'])
                ->where(function (Builder $query): void {
                    $query->whereIn('id', AffiliateApplication::query()->select('referrer_affiliate_id'))
                        ->orWhereIn('id', AffiliateApplication::query()->select('proposed_upline_id'));
                })
                ->orderBy('name')
                ->get(),
            'summaryCounts' => AffiliateApplication::query()
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ]);
    }

    public function show(AffiliateApplication $application): View
    {
        $application->load([
            'referrer:id,name,affiliate_code,group_name',
            'proposedUpline:id,name,affiliate_code,group_name',
            'approvedAffiliate:id,name,affiliate_code',
            'approvedUpline:id,name,affiliate_code',
            'reviewer:id,name',
        ]);

        return view('admin.affiliate-registrations.show', [
            'application' => $application,
            'groups' => Affiliate::query()
                ->whereNotNull('group_name')
                ->distinct()
                ->orderBy('group_name')
                ->pluck('group_name'),
            'uplines' => Affiliate::query()
                ->select(['id', 'name', 'affiliate_code', 'group_name'])
                ->where('status', 'active')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function approve(
        Request $request,
        AffiliateApplication $application,
        AffiliateApplicationService $applications,
        AffiliateReferralService $referrals,
    ): RedirectResponse {
        if ($application->approved_affiliate_id || $application->status === 'approved') {
            return redirect()
                ->route('admin.affiliate-registrations.index')
                ->with('error', 'This application is already approved.');
        }

        if (! in_array($application->status, ['pending', 'duplicate_review'], true)) {
            return redirect()
                ->route('admin.affiliate-registrations.index')
                ->with('error', 'Only pending or duplicate review applications can be approved.');
        }

        $data = $request->validate([
            'affiliate_type' => ['required', Rule::in(['inhouse', 'online'])],
            'group_name' => ['required', 'string', 'max:255'],
            'upline_id' => ['required', 'integer', 'exists:affiliates,id'],
            'upline_change_reason' => [
                Rule::requiredIf(fn (): bool => (int) $request->input('upline_id') !== (int) $application->proposed_upline_id),
                'nullable',
                'string',
                'max:1000',
            ],
            'position' => ['required', Rule::in(['affiliate', 'manager'])],
            'status' => ['required', Rule::in(['active'])],
            'approval_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $duplicateErrors = $this->approvalDuplicateErrors($application, $applications);

        if ($duplicateErrors !== []) {
            return redirect()
                ->route('admin.affiliate-registrations.show', $application->application_reference)
                ->withErrors(['approval' => implode(' ', $duplicateErrors)])
                ->withInput();
        }

        $temporaryPassword = $this->temporaryPassword();

        $affiliate = DB::transaction(function () use ($request, $application, $applications, $referrals, $data, $temporaryPassword): Affiliate {
            $lockedApplication = AffiliateApplication::query()
                ->whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedApplication->status, ['pending', 'duplicate_review'], true)) {
                throw new \RuntimeException('Application has already been reviewed.');
            }

            if ($lockedApplication->approved_affiliate_id) {
                throw new \RuntimeException('Application is already approved.');
            }

            $affiliateCode = $this->generateAffiliateCode($data['group_name']);
            $user = User::query()->create([
                'name' => $lockedApplication->full_name,
                'email' => $lockedApplication->email,
                'affiliate_code' => $affiliateCode,
                'password' => Hash::make($temporaryPassword),
                'must_change_password' => true,
                'role' => 'affiliate',
            ]);

            $affiliate = Affiliate::query()->create([
                'user_id' => $user->id,
                'affiliate_code' => $affiliateCode,
                'upline_id' => $data['upline_id'],
                'group_name' => $data['group_name'],
                'affiliate_type' => $data['affiliate_type'],
                'name' => $lockedApplication->full_name,
                'name_normalized' => $applications->normalizeName($lockedApplication->full_name),
                'email' => $lockedApplication->email,
                'phone' => $lockedApplication->phone,
                'raw_upline_text' => $lockedApplication->proposedUpline?->name,
                'status' => $data['status'],
            ]);

            foreach (array_filter([
                $lockedApplication->tiktok_username,
                $lockedApplication->additional_tiktok_username,
            ]) as $username) {
                TiktokAccount::query()->create([
                    'affiliate_id' => $affiliate->id,
                    'username' => ltrim(trim((string) $username), '@'),
                    'username_normalized' => $applications->normalizeTiktok((string) $username),
                    'status' => 'active',
                ]);
            }

            $referrals->ensureFor($affiliate);

            $lockedApplication->update([
                'status' => 'approved',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'approved_affiliate_id' => $affiliate->id,
                'approved_upline_id' => $data['upline_id'],
                'approved_group_name' => $data['group_name'],
                'approved_affiliate_type' => $data['affiliate_type'],
                'approved_position' => $data['position'],
                'upline_change_reason' => $data['upline_change_reason'] ?? null,
                'approval_notes' => $data['approval_notes'] ?? null,
                'rejection_reason' => null,
            ]);

            return $affiliate;
        });

        Log::notice('Affiliate registration application approved.', [
            'admin_user_id' => $request->user()->id,
            'application_reference' => $application->application_reference,
            'affiliate_id' => $affiliate->id,
        ]);

        return redirect()
            ->route('admin.affiliate-registrations.index')
            ->with('success', 'Application approved successfully.')
            ->with('approved_login', [
                'affiliate_name' => $affiliate->name,
                'affiliate_code' => $affiliate->affiliate_code,
                'login_id' => $affiliate->affiliate_code,
                'temporary_password' => $temporaryPassword,
            ]);
    }

    public function reject(Request $request, AffiliateApplication $application): RedirectResponse
    {
        if (! in_array($application->status, ['pending', 'duplicate_review'], true)) {
            return redirect()
                ->route('admin.affiliate-registrations.index')
                ->with('error', 'Only pending or duplicate review applications can be rejected.');
        }

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $application, $data): void {
            $lockedApplication = AffiliateApplication::query()
                ->whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedApplication->status, ['pending', 'duplicate_review'], true)) {
                throw new \RuntimeException('Application has already been reviewed.');
            }

            $lockedApplication->update([
                'status' => 'rejected',
                'rejection_reason' => $data['rejection_reason'],
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
        });

        Log::notice('Affiliate registration application rejected.', [
            'admin_user_id' => $request->user()->id,
            'application_reference' => $application->application_reference,
        ]);

        return redirect()
            ->route('admin.affiliate-registrations.index')
            ->with('success', 'Application rejected successfully.');
    }

    private function approvalDuplicateErrors(AffiliateApplication $application, AffiliateApplicationService $applications): array
    {
        $errors = [];
        $tiktokUsernames = array_filter([
            $application->tiktok_username,
            $application->additional_tiktok_username,
        ]);

        if (AffiliateApplication::query()
            ->whereKeyNot($application->id)
            ->where('nric_hash', $application->nric_hash)
            ->where('status', 'approved')
            ->exists()
        ) {
            $errors[] = 'Approval blocked because this NRIC already belongs to an approved application.';
        }

        foreach ($tiktokUsernames as $username) {
            if (TiktokAccount::query()->where('username_normalized', $applications->normalizeTiktok((string) $username))->exists()) {
                $errors[] = 'Approval blocked because TikTok username '.ltrim((string) $username, '@').' already exists.';
            }
        }

        if (
            User::query()->whereRaw('LOWER(email) = ?', [strtolower($application->email)])->exists()
            || Affiliate::query()->whereRaw('LOWER(email) = ?', [strtolower($application->email)])->exists()
        ) {
            $errors[] = 'Approval blocked because this email already exists.';
        }

        $normalizedPhone = $applications->normalizePhone($application->phone);
        $localPhone = str_starts_with($normalizedPhone, '60')
            ? '0'.substr($normalizedPhone, 2)
            : $normalizedPhone;
        $cleanPhoneSql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '')";

        if (Affiliate::query()
            ->whereNotNull('phone')
            ->where(fn (Builder $query) => $query
                ->whereRaw($cleanPhoneSql.' = ?', [$normalizedPhone])
                ->orWhereRaw($cleanPhoneSql.' = ?', [$localPhone]))
            ->exists()
        ) {
            $errors[] = 'Approval blocked because this phone number already exists.';
        }

        return array_values(array_unique($errors));
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
        } while (
            Affiliate::query()->where('affiliate_code', $code)->exists()
            || User::query()->where('affiliate_code', $code)->exists()
        );

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
}
