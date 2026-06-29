<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Models\TiktokAccount;
use App\Services\TiktokAccountRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TiktokAccountController extends Controller
{
    public function index(Request $request): View
    {
        $affiliate = $request->user()->affiliate;
        abort_unless($affiliate, 404);

        return view('affiliate.tiktok-accounts', [
            'affiliate' => $affiliate,
            'tiktokAccounts' => $affiliate->tiktokAccounts()
                ->select(['id', 'affiliate_id', 'username', 'username_normalized', 'status', 'created_at'])
                ->latest()
                ->get(),
            'pendingRequests' => $affiliate->tiktokAccountRequests()
                ->whereIn('status', ['pending_review', 'rejected'])
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request, TiktokAccountRequestService $requests): RedirectResponse
    {
        $affiliate = $request->user()->affiliate;
        abort_unless($affiliate, 404);

        $data = $request->validate([
            'username' => ['required', 'string', 'max:100', 'regex:/^@?[A-Za-z0-9._]+$/'],
        ]);

        $requests->submit($affiliate, $data['username']);

        return redirect()
            ->route('affiliate.tiktok-accounts')
            ->with('success', 'TikTok account request submitted. It is pending admin review.');
    }

    public function updateStatus(Request $request, TiktokAccount $account): RedirectResponse
    {
        $affiliate = $request->user()->affiliate;
        abort_unless($affiliate, 404);
        abort_unless((int) $account->affiliate_id === (int) $affiliate->id, 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        if ($data['status'] === 'active') {
            $conflictExists = TiktokAccount::query()
                ->where('username_normalized', $account->username_normalized)
                ->where('status', 'active')
                ->where('affiliate_id', '!=', $affiliate->id)
                ->exists();

            if ($conflictExists) {
                return back()->withErrors([
                    'status' => 'This TikTok username is already active under another affiliate.',
                ]);
            }
        }

        $account->forceFill(['status' => $data['status']])->save();

        return redirect()
            ->route('affiliate.tiktok-accounts')
            ->with('success', 'TikTok account status updated successfully.');
    }

    public function destroy(Request $request, TiktokAccount $account): RedirectResponse
    {
        $affiliate = $request->user()->affiliate;
        abort_unless($affiliate, 404);
        abort_unless((int) $account->affiliate_id === (int) $affiliate->id, 403);
        abort_unless($account->status === 'inactive', 403);

        $account->delete();

        return redirect()
            ->route('affiliate.tiktok-accounts')
            ->with('success', 'TikTok account telah dipadam.');
    }
}
