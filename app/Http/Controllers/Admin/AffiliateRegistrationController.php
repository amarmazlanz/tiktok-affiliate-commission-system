<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\AffiliateApplication;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AffiliateRegistrationController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['pending', 'duplicate_review', 'approved', 'rejected'])],
            'group' => ['nullable', 'string', 'max:255'],
            'referrer' => ['nullable', 'integer', 'exists:affiliates,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', Rule::in(['25', '50', '100'])],
        ]);

        $applications = AffiliateApplication::query()
            ->select([
                'id',
                'application_reference',
                'referrer_affiliate_id',
                'proposed_upline_id',
                'proposed_group_name',
                'full_name',
                'masked_nric',
                'phone',
                'email',
                'tiktok_username',
                'status',
                'submitted_at',
            ])
            ->with([
                'referrer:id,name,affiliate_code',
                'proposedUpline:id,name,affiliate_code',
            ])
            ->when(filled($filters['search'] ?? null), function (Builder $query) use ($filters): void {
                $search = trim((string) $filters['search']);
                $normalizedTiktok = strtolower(ltrim($search, '@'));

                $query->where(function (Builder $query) use ($search, $normalizedTiktok): void {
                    $query->where('application_reference', 'like', '%'.$search.'%')
                        ->orWhere('full_name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('tiktok_username', 'like', '%'.$search.'%')
                        ->orWhere('normalized_tiktok_username', 'like', '%'.$normalizedTiktok.'%');
                });
            })
            ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(filled($filters['group'] ?? null), fn (Builder $query) => $query->where('proposed_group_name', $filters['group']))
            ->when(filled($filters['referrer'] ?? null), function (Builder $query) use ($filters): void {
                $query->where(function (Builder $query) use ($filters): void {
                    $query->where('referrer_affiliate_id', $filters['referrer'])
                        ->orWhere('proposed_upline_id', $filters['referrer']);
                });
            })
            ->when(filled($filters['from'] ?? null), fn (Builder $query) => $query->whereDate('submitted_at', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn (Builder $query) => $query->whereDate('submitted_at', '<=', $filters['to']))
            ->latest('submitted_at')
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->withQueryString();

        return view('admin.affiliate-registrations.index', [
            'applications' => $applications,
            'filters' => $filters,
            'groups' => AffiliateApplication::query()
                ->whereNotNull('proposed_group_name')
                ->distinct()
                ->orderBy('proposed_group_name')
                ->pluck('proposed_group_name'),
            'referrers' => Affiliate::query()
                ->select(['id', 'name', 'affiliate_code'])
                ->where(function (Builder $query): void {
                    $query->whereIn('id', AffiliateApplication::query()->select('referrer_affiliate_id'))
                        ->orWhereIn('id', AffiliateApplication::query()->select('proposed_upline_id'));
                })
                ->orderBy('name')
                ->get(),
            'summaryCounts' => AffiliateApplication::query()
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ]);
    }

    public function show(AffiliateApplication $application): View
    {
        $application->load([
            'referrer:id,name,affiliate_code,group_name',
            'proposedUpline:id,name,affiliate_code,group_name',
            'reviewer:id,name',
        ]);

        return view('admin.affiliate-registrations.show', compact('application'));
    }
}
