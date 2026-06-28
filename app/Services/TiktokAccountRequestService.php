<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\AffiliateTiktokAccountRequest;
use App\Models\TiktokAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TiktokAccountRequestService
{
    public function normalizeUsername(string $value): string
    {
        return strtolower(ltrim(trim($value), '@'));
    }

    public function hasActiveConflict(string $normalizedUsername): bool
    {
        return TiktokAccount::query()
            ->where('username_normalized', $normalizedUsername)
            ->where('status', 'active')
            ->exists();
    }

    public function submit(Affiliate $affiliate, string $requestedUsername): AffiliateTiktokAccountRequest
    {
        $username = ltrim(trim($requestedUsername), '@');
        $normalized = $this->normalizeUsername($requestedUsername);

        if ($normalized === '') {
            throw ValidationException::withMessages(['username' => 'Please enter a TikTok username.']);
        }

        if ($this->hasActiveConflict($normalized)) {
            throw ValidationException::withMessages(['username' => 'This TikTok username is already active and cannot be requested.']);
        }

        if (
            AffiliateTiktokAccountRequest::query()
                ->where('normalized_username', $normalized)
                ->where('status', 'pending_review')
                ->exists()
        ) {
            throw ValidationException::withMessages(['username' => 'This TikTok username already has a pending request.']);
        }

        return $affiliate->tiktokAccountRequests()->create([
            'requested_username' => $username,
            'normalized_username' => $normalized,
            'status' => 'pending_review',
        ]);
    }

    public function approve(AffiliateTiktokAccountRequest $request, User $admin): TiktokAccount
    {
        return DB::transaction(function () use ($request, $admin): TiktokAccount {
            $locked = AffiliateTiktokAccountRequest::query()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'pending_review') {
                throw ValidationException::withMessages(['status' => 'This request has already been reviewed.']);
            }

            $existing = TiktokAccount::query()
                ->where('username_normalized', $locked->normalized_username)
                ->first();

            if ($existing && (int) $existing->affiliate_id !== (int) $locked->affiliate_id) {
                throw ValidationException::withMessages(['username' => 'This TikTok username already belongs to another affiliate.']);
            }

            if ($existing) {
                $existing->update(['status' => 'active', 'username' => $locked->requested_username]);
                $account = $existing;
            } else {
                $account = TiktokAccount::query()->create([
                    'affiliate_id' => $locked->affiliate_id,
                    'username' => $locked->requested_username,
                    'username_normalized' => $locked->normalized_username,
                    'status' => 'active',
                ]);
            }

            $locked->update([
                'status' => 'approved',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            return $account;
        });
    }

    public function reject(AffiliateTiktokAccountRequest $request, User $admin, string $reason): void
    {
        DB::transaction(function () use ($request, $admin, $reason): void {
            $locked = AffiliateTiktokAccountRequest::query()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'pending_review') {
                throw ValidationException::withMessages(['status' => 'This request has already been reviewed.']);
            }

            $locked->update([
                'status' => 'rejected',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);
        });
    }
}
