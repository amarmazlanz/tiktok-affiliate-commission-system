<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TiktokAccountController extends Controller
{
    public function __invoke(Request $request): View
    {
        $affiliate = $request->user()->affiliate;
        abort_unless($affiliate, 404);

        return view('affiliate.tiktok-accounts', [
            'affiliate' => $affiliate,
            'tiktokAccounts' => $affiliate->tiktokAccounts()
                ->select(['id', 'affiliate_id', 'username', 'username_normalized', 'status', 'created_at'])
                ->latest()
                ->get(),
        ]);
    }
}
