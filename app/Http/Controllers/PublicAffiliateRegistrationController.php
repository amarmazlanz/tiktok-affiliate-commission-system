<?php

namespace App\Http\Controllers;

use App\Models\AffiliateReferral;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PublicAffiliateRegistrationController extends Controller
{
    public function __invoke(string $referralCode): View|Response
    {
        $referral = AffiliateReferral::query()
            ->with('affiliate:id,name,status')
            ->where('referral_code', strtoupper(trim($referralCode)))
            ->first();
        $isValid = $referral
            && $referral->is_active
            && $referral->affiliate?->status === 'active';

        $view = view('public.affiliate-registration', [
            'isValid' => $isValid,
            'referrerName' => $isValid ? $referral->affiliate->name : null,
        ]);

        return $isValid ? $view : response($view, 404);
    }
}
