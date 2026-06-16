<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AffiliateController extends Controller
{
    public function index(): View
    {
        $affiliates = Affiliate::query()
            ->with('upline')
            ->withCount('directDownlines')
            ->latest()
            ->paginate(15);

        return view('admin.affiliates.index', compact('affiliates'));
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
                'role' => 'affiliate',
            ]);

            Affiliate::create([
                'user_id' => $user->id,
                'upline_id' => $data['upline_id'] ?? null,
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
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($affiliate->user_id),
                Rule::unique('affiliates', 'email')->ignore($affiliate->id),
            ],
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
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'status' => $data['status'],
            ]);

            $affiliate->user->update([
                'name' => $data['name'],
                'email' => $data['email'],
            ]);
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
}
