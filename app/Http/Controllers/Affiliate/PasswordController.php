<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Services\AffiliateApplicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();
        $affiliate = $user->affiliate;
        $affiliate?->loadMissing('tiktokAccounts');

        return view('affiliate.password', [
            'affiliate' => $affiliate,
            'loginId' => $user->affiliate_code ?: $user->email,
            'loginAccessStatus' => $user->role === 'affiliate' ? 'Active' : 'Unavailable',
            'maskedNric' => $affiliate
                ? DB::table('affiliate_applications')
                    ->where('approved_affiliate_id', $affiliate->id)
                    ->value('masked_nric')
                : null,
        ]);
    }

    public function updateProfile(Request $request, AffiliateApplicationService $applications): RedirectResponse
    {
        $user = $request->user();
        $affiliate = $user->affiliate;

        abort_unless($affiliate, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
                Rule::unique('affiliates', 'email')->ignore($affiliate->id),
            ],
            'phone' => ['required', 'string', 'max:30', function (string $attribute, mixed $value, \Closure $fail) use ($applications): void {
                if (! $applications->isValidMalaysianMobile((string) $value)) {
                    $fail('Please enter a valid Malaysian mobile number.');
                }
            }],
        ]);

        $phone = $this->formatLocalPhone($applications->normalizePhone($data['phone']));

        DB::transaction(function () use ($user, $affiliate, $data, $phone): void {
            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
            ]);

            $affiliate->update([
                'name' => $data['name'],
                'name_normalized' => strtolower(preg_replace('/\s+/', ' ', trim($data['name']))),
                'email' => $data['email'],
                'phone' => $phone,
            ]);
        });

        return redirect()
            ->route('affiliate.settings')
            ->with('success', 'Profile information updated successfully.');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Current password is incorrect.',
            ]);
        }

        if (Hash::check($data['password'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'password' => 'New password must be different from the current password.',
            ]);
        }

        $request->user()->forceFill([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        $request->session()->regenerate();

        return redirect()
            ->route('affiliate.dashboard')
            ->with('success', 'Password changed successfully.');
    }

    private function formatLocalPhone(string $normalizedPhone): string
    {
        $local = str_starts_with($normalizedPhone, '60')
            ? '0'.substr($normalizedPhone, 2)
            : $normalizedPhone;
        $digits = preg_replace('/\D+/', '', $local) ?? '';

        if (strlen($digits) <= 3) {
            return $digits;
        }

        return substr($digits, 0, 3).'-'.substr($digits, 3);
    }
}
