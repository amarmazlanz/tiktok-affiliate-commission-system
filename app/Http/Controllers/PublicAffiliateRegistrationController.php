<?php

namespace App\Http\Controllers;

use App\Models\AffiliateApplication;
use App\Models\AffiliateReferral;
use App\Services\AffiliateApplicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PublicAffiliateRegistrationController extends Controller
{
    public function show(string $referralCode): View|Response
    {
        $referral = $this->activeReferral($referralCode);

        if (! $referral) {
            return $this->invalidReferralResponse();
        }

        return view('public.affiliate-registration', [
            'isValid' => true,
            'referral' => $referral,
        ]);
    }

    public function store(
        Request $request,
        string $referralCode,
        AffiliateApplicationService $applications,
    ): RedirectResponse|Response {
        $referral = $this->activeReferral($referralCode);

        if (! $referral) {
            return $this->invalidReferralResponse();
        }

        $validator = Validator::make($request->all(), [
            'full_name' => ['required', 'string', 'max:255', 'regex:/^[\pL\s.\'@-]+$/u'],
            'nric' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail) use ($applications): void {
                if (! preg_match('/^\d{12}$/', $applications->normalizeNric((string) $value))) {
                    $fail('Please enter a valid 12-digit Malaysian NRIC.');
                }
            }],
            'phone' => ['required', 'string', 'max:30', function (string $attribute, mixed $value, \Closure $fail) use ($applications): void {
                $phone = $applications->normalizePhone((string) $value);

                if (! preg_match('/^60\d{8,11}$/', $phone)) {
                    $fail('Please enter a valid Malaysian phone number.');
                }
            }],
            'email' => ['required', 'email:rfc', 'max:255'],
            'tiktok_username' => ['required', 'string', 'max:100', 'regex:/^@?[A-Za-z0-9._]+$/'],
            'tiktok_username_confirmation' => ['required', 'same:tiktok_username'],
            'additional_tiktok_username' => ['nullable', 'string', 'max:100', 'regex:/^@?[A-Za-z0-9._]+$/'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'consent' => ['accepted'],
            'website' => ['nullable', 'max:0'],
            'referral_code' => ['nullable', Rule::in([$referral->referral_code])],
        ], [
            'full_name.regex' => 'Full name contains unsupported characters.',
            'tiktok_username.regex' => 'TikTok username may contain letters, numbers, underscore, and period only.',
            'tiktok_username_confirmation.same' => 'TikTok username confirmation does not match.',
            'additional_tiktok_username.regex' => 'Additional TikTok username format is invalid.',
            'consent.accepted' => 'You must confirm the consent statement.',
            'website.max' => 'Unable to submit this application.',
            'referral_code.in' => 'The referral code is invalid.',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('public.affiliate-registration.show', $referral->referral_code)
                ->withErrors($validator)
                ->withInput($request->except(['nric', 'website']));
        }

        $validated = $validator->validated();

        if (
            ! empty($validated['additional_tiktok_username'])
            && $applications->normalizeTiktok($validated['additional_tiktok_username'])
                === $applications->normalizeTiktok($validated['tiktok_username'])
        ) {
            return redirect()
                ->route('public.affiliate-registration.show', $referral->referral_code)
                ->withErrors(['additional_tiktok_username' => 'Additional TikTok username must be different.'])
                ->withInput($request->except(['nric', 'website']));
        }

        $application = $applications->create($referral, $validated);

        return redirect()->route('public.affiliate-registration.submitted', [
            'applicationReference' => $application->application_reference,
        ]);
    }

    public function success(string $applicationReference): View|Response
    {
        $application = AffiliateApplication::query()
            ->with('referrer:id,name')
            ->where('application_reference', strtoupper(trim($applicationReference)))
            ->first();

        if (! $application) {
            return response(view('public.application-not-found'), 404);
        }

        return view('public.affiliate-registration-success', compact('application'));
    }

    private function activeReferral(string $referralCode): ?AffiliateReferral
    {
        return AffiliateReferral::query()
            ->with('affiliate:id,name,affiliate_code,group_name,status')
            ->where('referral_code', strtoupper(trim($referralCode)))
            ->where('is_active', true)
            ->whereHas('affiliate', fn ($query) => $query->where('status', 'active'))
            ->first();
    }

    private function invalidReferralResponse(): Response
    {
        return response(view('public.affiliate-registration', [
            'isValid' => false,
            'referral' => null,
        ]), 404);
    }
}
