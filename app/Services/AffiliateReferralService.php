<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\AffiliateReferral;
use Illuminate\Database\QueryException;

class AffiliateReferralService
{
    public function ensureFor(Affiliate $affiliate): AffiliateReferral
    {
        if ($affiliate->relationLoaded('referral') && $affiliate->referral) {
            return $affiliate->referral;
        }

        $existing = AffiliateReferral::query()->where('affiliate_id', $affiliate->id)->first();

        if ($existing) {
            $affiliate->setRelation('referral', $existing);

            return $existing;
        }

        for ($attempt = 0; $attempt < 10; $attempt++) {
            try {
                $referral = AffiliateReferral::query()->create([
                    'affiliate_id' => $affiliate->id,
                    'referral_code' => $this->generateCode($affiliate->group_name),
                    'is_active' => true,
                ]);
                $affiliate->setRelation('referral', $referral);

                return $referral;
            } catch (QueryException $exception) {
                $existing = AffiliateReferral::query()->where('affiliate_id', $affiliate->id)->first();

                if ($existing) {
                    $affiliate->setRelation('referral', $existing);

                    return $existing;
                }

                if ($attempt === 9) {
                    throw $exception;
                }
            }
        }

        throw new \RuntimeException('Unable to create a unique affiliate referral code.');
    }

    public function generateCode(?string $groupName): string
    {
        return $this->prefix($groupName).'-'.$this->randomToken();
    }

    private function prefix(?string $groupName): string
    {
        $normalized = strtolower(trim((string) $groupName));

        return match (true) {
            str_contains($normalized, 'titan') => 'TIT',
            str_contains($normalized, 'aurora') => 'AUR',
            str_contains($normalized, 'kaizen') => 'KAI',
            str_contains($normalized, 'swg') => 'SWG',
            default => 'AFF',
        };
    }

    private function randomToken(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $token = '';

        for ($index = 0; $index < 6; $index++) {
            $token .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $token;
    }
}
