<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AffiliateLoginReportController extends Controller
{
    public function show(Request $request): View
    {
        $filters = $this->validatedFilters($request);
        $query = $this->reportQuery($filters);

        if (! empty($filters['selected'])) {
            $query->whereKey($filters['selected']);
        }

        $credentials = [];
        if ($request->filled('credential_batch')) {
            $credentials = (array) $request->session()->pull(
                'affiliate_login_report.'.(string) $request->input('credential_batch'),
                []
            );
        }

        return view('admin.affiliates.login-report', [
            'affiliates' => $query->get(),
            'credentials' => $credentials,
            'filters' => $filters,
            'generatedAt' => now(),
            'generatedBy' => $request->user(),
        ]);
    }

    public function confirm(Request $request): View|RedirectResponse
    {
        $filters = $this->validatedFilters($request);
        $scope = $this->validatedScope($request);
        $previewLimit = 20;

        if ($scope === 'selected' && empty($filters['selected'])) {
            return back()->with('error', 'Select at least one affiliate before generating temporary passwords.');
        }

        $affectedQuery = $this->generationQuery($filters, $scope);
        $totalAffected = (clone $affectedQuery)->count();

        if ($totalAffected === 0) {
            return back()->with('error', 'No eligible affiliate login accounts were found.');
        }

        $affectedCounts = [
            'total' => $totalAffected,
            'inhouse' => (clone $affectedQuery)->where('affiliate_type', 'inhouse')->count(),
            'external' => (clone $affectedQuery)->where('affiliate_type', 'external')->count(),
            'never_logged_in' => (clone $affectedQuery)->whereHas('user', fn (Builder $query) => $query->whereNull('last_login_at'))->count(),
            'must_change_password' => (clone $affectedQuery)->whereHas('user', fn (Builder $query) => $query->where('must_change_password', true))->count(),
            'existing_passwords_replaced' => (clone $affectedQuery)->whereNotNull('user_id')->count(),
        ];

        $affiliates = (clone $affectedQuery)
            ->orderBy('group_name')
            ->orderBy('name')
            ->limit($previewLimit)
            ->get();

        $confirmationToken = (string) Str::uuid();
        $request->session()->put('affiliate_password_generation_confirmation.'.$confirmationToken, [
            'admin_user_id' => $request->user()->id,
            'filters' => $filters,
            'scope' => $scope,
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        return view('admin.affiliates.password-generation-confirm', [
            'affiliates' => $affiliates,
            'affectedCounts' => $affectedCounts,
            'confirmationToken' => $confirmationToken,
            'filters' => $filters,
            'previewLimit' => $previewLimit,
            'scope' => $scope,
            'scopeLabel' => $this->scopeLabel($scope),
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'confirmation_token' => ['required', 'uuid'],
        ]);
        $sessionKey = 'affiliate_password_generation_confirmation.'.$data['confirmation_token'];
        $confirmation = $request->session()->pull($sessionKey);

        if (
            ! is_array($confirmation)
            || ($confirmation['admin_user_id'] ?? null) !== $request->user()->id
            || ($confirmation['expires_at'] ?? 0) < now()->timestamp
        ) {
            throw ValidationException::withMessages([
                'confirmation_token' => 'This password generation confirmation has expired. Please review the affiliates again.',
            ]);
        }

        $credentials = [];
        $affectedUserIds = [];
        $filters = $confirmation['filters'] ?? [];
        $scope = $confirmation['scope'] ?? 'never_logged_in';
        $affectedCount = (clone $this->generationQuery($filters, $scope))->count();

        if ($affectedCount === 0) {
            return redirect()
                ->route('admin.affiliates.index')
                ->with('error', 'No eligible affiliate login accounts were found.');
        }

        $this->generationQuery($filters, $scope)
            ->orderBy('id')
            ->chunkById(100, function ($affiliates) use ($request, &$credentials, &$affectedUserIds): void {
                DB::transaction(function () use ($request, $affiliates, &$credentials, &$affectedUserIds): void {
                    $existingEmails = User::query()
                        ->whereIn('email', $affiliates
                            ->pluck('email')
                            ->filter()
                            ->values())
                        ->pluck('email')
                        ->flip();

                    foreach ($affiliates as $affiliate) {
                        $temporaryPassword = $this->temporaryPassword();

                        if (! $affiliate->user) {
                            $affiliateCode = $affiliate->affiliate_code ?: $this->generateAffiliateCode($affiliate->group_name ?: 'AFF');
                            $email = $affiliate->email && ! $existingEmails->has($affiliate->email)
                                ? $affiliate->email
                                : null;

                            $user = User::query()->create([
                                'name' => $affiliate->name,
                                'email' => $email,
                                'affiliate_code' => $affiliateCode,
                                'password' => Hash::make($temporaryPassword),
                                'must_change_password' => true,
                                'role' => 'affiliate',
                            ]);

                            if ($email) {
                                $existingEmails->put($email, true);
                            }

                            $affiliate->forceFill(['user_id' => $user->id, 'affiliate_code' => $affiliateCode])->save();
                            $affiliate->setRelation('user', $user);
                        } else {
                            $affiliate->user->forceFill([
                                'password' => Hash::make($temporaryPassword),
                                'role' => 'affiliate',
                                'must_change_password' => true,
                            ])->save();
                        }

                        $affiliate->forceFill([
                            'password_reset_at' => now(),
                            'password_reset_by' => $request->user()->id,
                        ])->save();

                        $credentials[$affiliate->id] = $temporaryPassword;
                        $affectedUserIds[] = $affiliate->user_id;
                    }
                });
            });

        Log::notice('Affiliate temporary passwords generated by admin.', [
            'admin_user_id' => $request->user()->id,
            'generated_at' => now()->toIso8601String(),
            'affected_user_ids' => $affectedUserIds,
            'count' => count($affectedUserIds),
            'scope' => $confirmation['scope'] ?? null,
        ]);

        $batch = (string) Str::uuid();
        $request->session()->put('affiliate_login_report.'.$batch, $credentials);

        $query = array_filter([
            'group' => $filters['group'] ?? null,
            'type' => $filters['type'] ?? null,
            'status' => $filters['status'] ?? null,
            'search' => $filters['search'] ?? null,
            'selected' => $scope === 'selected' ? ($filters['selected'] ?? null) : null,
            'credential_batch' => $batch,
        ]);

        return redirect()->route('admin.affiliates.login-report', $query);
    }

    private function generationQuery(array $filters, string $scope): Builder
    {
        $query = $this->accountStatusQuery($filters);

        return match ($scope) {
            'never_logged_in' => $query->whereHas('user', fn (Builder $query) => $query->whereNull('last_login_at')),
            'must_change_password' => $query->whereHas('user', fn (Builder $query) => $query->where('must_change_password', true)),
            'selected' => $query->whereKey($filters['selected'] ?? []),
            'filtered' => $query,
        };
    }

    private function accountStatusQuery(array $filters): Builder
    {
        return Affiliate::query()
            ->select([
                'id',
                'user_id',
                'affiliate_code',
                'group_name',
                'affiliate_type',
                'name',
                'email',
                'status',
            ])
            ->with('user:id,email,affiliate_code,must_change_password,last_login_at,password_changed_at')
            ->when(! empty($filters['group']), fn (Builder $query) => $query->where('group_name', $filters['group']))
            ->when(! empty($filters['type']), fn (Builder $query) => $query->where('affiliate_type', $filters['type']))
            ->when(! empty($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(! empty($filters['search']), function (Builder $query) use ($filters): void {
                $search = $filters['search'];
                $normalizedUsername = strtolower(ltrim($search, '@'));

                $query->where(function (Builder $query) use ($search, $normalizedUsername): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('affiliate_code', 'like', '%'.$search.'%')
                        ->orWhereHas('tiktokAccounts', fn (Builder $query) => $query
                            ->where('username', 'like', '%'.$search.'%')
                            ->orWhere('username_normalized', 'like', '%'.$normalizedUsername.'%'));
                });
            });
    }

    private function reportQuery(array $filters): Builder
    {
        return Affiliate::query()
            ->select([
                'id',
                'user_id',
                'upline_id',
                'affiliate_code',
                'group_name',
                'affiliate_type',
                'name',
                'email',
                'status',
            ])
            ->with([
                'user:id,email,affiliate_code,must_change_password,last_login_at,password_changed_at',
                'upline:id,name',
                'tiktokAccounts:id,affiliate_id,username_normalized',
            ])
            ->when(! empty($filters['group']), fn (Builder $query) => $query->where('group_name', $filters['group']))
            ->when(! empty($filters['type']), fn (Builder $query) => $query->where('affiliate_type', $filters['type']))
            ->when(! empty($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(! empty($filters['search']), function (Builder $query) use ($filters): void {
                $search = $filters['search'];
                $normalizedUsername = strtolower(ltrim($search, '@'));

                $query->where(function (Builder $query) use ($search, $normalizedUsername): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('affiliate_code', 'like', '%'.$search.'%')
                        ->orWhereHas('tiktokAccounts', fn (Builder $query) => $query
                            ->where('username', 'like', '%'.$search.'%')
                            ->orWhere('username_normalized', 'like', '%'.$normalizedUsername.'%'));
                });
            })
            ->orderBy('group_name')
            ->orderBy('name');
    }

    private function validatedScope(Request $request): string
    {
        return $request->validate([
            'generation_scope' => ['required', Rule::in([
                'never_logged_in',
                'must_change_password',
                'selected',
                'filtered',
            ])],
        ])['generation_scope'];
    }

    private function scopeLabel(string $scope): string
    {
        return match ($scope) {
            'never_logged_in' => 'Never Logged In',
            'must_change_password' => 'Must Change Password',
            'selected' => 'Selected Affiliates',
            'filtered' => 'All Filtered Affiliates',
        };
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'group' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(['inhouse', 'external'])],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'search' => ['nullable', 'string', 'max:255'],
            'selected' => ['nullable', 'array'],
            'selected.*' => ['integer', 'distinct', 'exists:affiliates,id'],
            'credential_batch' => ['nullable', 'uuid'],
        ]);
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
}
