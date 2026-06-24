<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AffiliateController extends Controller
{
    public function index(Request $request): View
    {
        $affiliates = Affiliate::query()
            ->with(['upline', 'user', 'tiktokAccounts'])
            ->withCount(['directDownlines', 'tiktokAccounts'])
            ->when($request->filled('group'), fn ($query) => $query->where('group_name', (string) $request->input('group')))
            ->when($request->filled('type'), fn ($query) => $query->where('affiliate_type', (string) $request->input('type')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', (string) $request->input('status')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim((string) $request->input('search'));

                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('affiliate_code', 'like', '%'.$search.'%')
                        ->orWhereHas('tiktokAccounts', fn ($query) => $query->where('username', 'like', '%'.$search.'%')
                            ->orWhere('username_normalized', 'like', '%'.strtolower(ltrim($search, '@')).'%'));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.affiliates.index', [
            'affiliates' => $affiliates,
            'groups' => Affiliate::query()
                ->whereNotNull('group_name')
                ->distinct()
                ->orderBy('group_name')
                ->pluck('group_name'),
            'filters' => $request->only(['group', 'type', 'status', 'search']),
        ]);
    }

    public function create(): View
    {
        return view('admin.affiliates.create', [
            'affiliate' => new Affiliate(['status' => 'active']),
            'uplines' => Affiliate::query()->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function show(Affiliate $affiliate): View
    {
        $affiliate->load([
            'upline',
            'passwordResetBy',
            'tiktokAccounts' => fn ($query) => $query->latest(),
            'directDownlines' => fn ($query) => $query->withCount('tiktokAccounts')->orderBy('name'),
        ])->loadCount('directDownlines');

        return view('admin.affiliates.show', compact('affiliate'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email', 'unique:affiliates,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'upline_id' => ['nullable', 'exists:affiliates,id'],
        ]);

        DB::transaction(function () use ($data): void {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make('password'),
                'must_change_password' => true,
                'role' => 'affiliate',
            ]);

            Affiliate::create([
                'user_id' => $user->id,
                'upline_id' => $data['upline_id'] ?? null,
                'affiliate_type' => 'inhouse',
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'status' => $data['status'],
            ]);
        });

        return redirect()
            ->route('admin.affiliates.index')
            ->with('success', 'Affiliate berjaya ditambah.');
    }

    public function edit(Affiliate $affiliate): View
    {
        return view('admin.affiliates.edit', [
            'affiliate' => $affiliate,
            'uplines' => Affiliate::query()
                ->whereKeyNot($affiliate->id)
                ->where('status', 'active')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, Affiliate $affiliate): RedirectResponse
    {
        $emailRules = [
            $affiliate->user ? 'required' : 'nullable',
            'email',
            'max:255',
            Rule::unique('users', 'email')->ignore($affiliate->user_id),
            Rule::unique('affiliates', 'email')->ignore($affiliate->id),
        ];

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => $emailRules,
            'phone' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'upline_id' => [
                'nullable',
                'exists:affiliates,id',
                Rule::notIn([$affiliate->id]),
            ],
        ]);

        DB::transaction(function () use ($affiliate, $data): void {
            $affiliate->update([
                'upline_id' => $data['upline_id'] ?? null,
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'status' => $data['status'],
            ]);

            $userUpdate = ['name' => $data['name']];

            if (! empty($data['email'])) {
                $userUpdate['email'] = $data['email'];
            }

            $affiliate->user?->update($userUpdate);
        });

        return redirect()
            ->route('admin.affiliates.index')
            ->with('success', 'Affiliate berjaya dikemaskini.');
    }

    public function destroy(Affiliate $affiliate): RedirectResponse
    {
        $affiliate->update(['status' => 'inactive']);

        return redirect()
            ->route('admin.affiliates.index')
            ->with('success', 'Affiliate telah dinyahaktifkan.');
    }

    public function resetPassword(Request $request, Affiliate $affiliate): RedirectResponse
    {
        if ($affiliate->affiliate_type === 'external' || ! $affiliate->user) {
            return redirect()
                ->route('admin.affiliates.show', $affiliate)
                ->with('error', 'Affiliate ini tidak mempunyai akaun login.');
        }

        $temporaryPassword = $this->temporaryPassword();

        DB::transaction(function () use ($request, $affiliate, $temporaryPassword): void {
            $affiliate->user->update([
                'password' => Hash::make($temporaryPassword),
                'role' => 'affiliate',
                'must_change_password' => true,
            ]);

            $affiliate->update([
                'password_reset_at' => now(),
                'password_reset_by' => $request->user()->id,
            ]);
        });

        Log::notice('Affiliate password reset by admin.', [
            'admin_user_id' => $request->user()->id,
            'affiliate_id' => $affiliate->id,
            'reset_at' => now()->toIso8601String(),
        ]);

        return redirect()
            ->route('admin.affiliates.show', $affiliate)
            ->with('success', 'Password affiliate berjaya direset.')
            ->with('reset_password', [
                'name' => $affiliate->name,
                'login_id' => $affiliate->affiliate_code
                    ?: ($affiliate->user?->email ?? $affiliate->email),
                'temporary_password' => $temporaryPassword,
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
}
