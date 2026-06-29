<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\TiktokAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TiktokAccountController extends Controller
{
    public function store(Request $request, Affiliate $affiliate): RedirectResponse
    {
        $normalizedUsername = $this->normalizeUsername((string) $request->input('username'));
        $request->merge(['username_normalized' => $normalizedUsername]);

        $data = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'username_normalized' => ['required', 'string', 'max:255', Rule::unique('tiktok_accounts', 'username_normalized')],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ], [
            'username_normalized.unique' => 'Username TikTok ini sudah digunakan oleh affiliate lain.',
        ]);

        $affiliate->tiktokAccounts()->create([
            'username' => $data['username'],
            'username_normalized' => $data['username_normalized'],
            'status' => $data['status'],
        ]);

        return redirect()
            ->route('admin.affiliates.show', $affiliate)
            ->with('success', 'TikTok account berjaya ditambah.');
    }

    public function destroy(Affiliate $affiliate, TiktokAccount $tiktokAccount): RedirectResponse
    {
        abort_unless($tiktokAccount->affiliate_id === $affiliate->id, 404);

        $tiktokAccount->update(['status' => 'inactive']);

        return redirect()
            ->route('admin.affiliates.show', $affiliate)
            ->with('success', 'TikTok account telah dinyahaktifkan.');
    }

    public function activate(Affiliate $affiliate, TiktokAccount $tiktokAccount): RedirectResponse
    {
        abort_unless($tiktokAccount->affiliate_id === $affiliate->id, 404);

        $conflictExists = TiktokAccount::query()
            ->where('username_normalized', $tiktokAccount->username_normalized)
            ->where('status', 'active')
            ->whereKeyNot($tiktokAccount->id)
            ->exists();

        if ($conflictExists) {
            return back()->withErrors([
                'username' => 'Username TikTok ini sudah aktif di bawah affiliate lain.',
            ]);
        }

        $tiktokAccount->update(['status' => 'active']);

        return redirect()
            ->route('admin.affiliates.show', $affiliate)
            ->with('success', 'TikTok account telah diaktifkan.');
    }

    public function forceDestroy(Affiliate $affiliate, TiktokAccount $tiktokAccount): RedirectResponse
    {
        abort_unless($tiktokAccount->affiliate_id === $affiliate->id, 404);
        abort_unless($tiktokAccount->status === 'inactive', 403);

        $tiktokAccount->delete();

        return redirect()
            ->route('admin.affiliates.show', $affiliate)
            ->with('success', 'TikTok account telah dipadam secara kekal.');
    }

    private function normalizeUsername(string $username): string
    {
        return str_replace('@', '', strtolower(trim($username)));
    }
}
