<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AffiliateTiktokAccountRequest;
use App\Services\TiktokAccountRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TiktokAccountRequestController extends Controller
{
    public function index(): View
    {
        return view('admin.tiktok-account-requests.index', [
            'pendingRequests' => AffiliateTiktokAccountRequest::query()
                ->with('affiliate:id,name,affiliate_code,group_name,affiliate_type')
                ->where('status', 'pending_review')
                ->latest()
                ->get(),
            'reviewedRequests' => AffiliateTiktokAccountRequest::query()
                ->with(['affiliate:id,name,affiliate_code', 'reviewer:id,name'])
                ->whereIn('status', ['approved', 'rejected'])
                ->latest('reviewed_at')
                ->take(50)
                ->get(),
        ]);
    }

    public function approve(Request $request, AffiliateTiktokAccountRequest $tiktokAccountRequest, TiktokAccountRequestService $requests): RedirectResponse
    {
        $requests->approve($tiktokAccountRequest, $request->user());

        return redirect()
            ->route('admin.tiktok-account-requests.index')
            ->with('success', 'TikTok account request approved and activated.');
    }

    public function reject(Request $request, AffiliateTiktokAccountRequest $tiktokAccountRequest, TiktokAccountRequestService $requests): RedirectResponse
    {
        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $requests->reject($tiktokAccountRequest, $request->user(), $data['rejection_reason']);

        return redirect()
            ->route('admin.tiktok-account-requests.index')
            ->with('success', 'TikTok account request rejected.');
    }
}
