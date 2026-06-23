<?php

namespace App\Services\Exports;

use App\Models\Affiliate;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AffiliateExportService
{
    public function excelPath(Request $request): string
    {
        $path = storage_path('app/exports/affiliate-list-'.Str::slug($request->query('group', 'all-affiliates')).'-'.now()->format('Y-m-d-His').'.xls');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        $writer = new SpreadsheetXmlWriter($path);
        $writer->startSheet('Affiliates');
        $writer->row(['Affiliate Name', 'Affiliate Code', 'Group', 'Type', 'Position', 'Status', 'Login Access', 'Login ID or Email', 'Direct Upline', 'Direct Downlines', 'TikTok Accounts']);

        $this->query($request)
            ->orderBy('id')
            ->chunk(500, function ($affiliates) use ($writer): void {
                foreach ($affiliates as $affiliate) {
                    $writer->row([
                        $affiliate->name,
                        $affiliate->affiliate_code ?: '-',
                        $affiliate->group_name ?: '-',
                        $affiliate->affiliate_type === 'external' ? 'Affiliate Luar' : 'Inhouse',
                        $affiliate->direct_downlines_count > 0 ? 'Manager' : 'Affiliate',
                        ucfirst((string) $affiliate->status),
                        $affiliate->user ? 'Has login access' : 'No login access',
                        $affiliate->user
                            ? ($affiliate->affiliate_code ?: $affiliate->user->email)
                            : '-',
                        $affiliate->upline?->name ?: '-',
                        $affiliate->direct_downlines_count,
                        $affiliate->tiktokAccounts->pluck('username_normalized')->implode(', '),
                    ]);
                }
            });

        $writer->endSheet();
        $writer->close();

        return $path;
    }

    private function query(Request $request)
    {
        return Affiliate::query()
            ->with(['upline:id,name', 'user:id,email,affiliate_code', 'tiktokAccounts:id,affiliate_id,username_normalized'])
            ->withCount(['directDownlines'])
            ->when($request->filled('group'), fn ($query) => $query->where('group_name', (string) $request->query('group')))
            ->when($request->filled('type'), fn ($query) => $query->where('affiliate_type', (string) $request->query('type')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', (string) $request->query('status')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim((string) $request->query('search'));
                $normalizedUsername = strtolower(ltrim($search, '@'));

                $query->where(function ($query) use ($search, $normalizedUsername): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('affiliate_code', 'like', '%'.$search.'%')
                        ->orWhereHas('tiktokAccounts', fn ($query) => $query
                            ->where('username', 'like', '%'.$search.'%')
                            ->orWhere('username_normalized', 'like', '%'.$normalizedUsername.'%'));
                });
            });
    }
}
