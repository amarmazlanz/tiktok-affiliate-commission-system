<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderImport;
use App\Models\TiktokAccount;
use App\Models\TiktokOrder;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use SplFileObject;

class OrderImportController extends Controller
{
    private const REQUIRED_COLUMNS = [
        'Order ID',
        'Creator Username',
        'Order Status',
        'Est. Commission Base',
    ];

    public function index(): View
    {
        $imports = OrderImport::query()
            ->with('importedBy')
            ->latest()
            ->paginate(25);

        $totalOrders = TiktokOrder::query()->count();

        return view('admin.orders.index', compact('imports', 'totalOrders'));
    }

    public function create(): View
    {
        return view('admin.orders.upload');
    }

    public function store(Request $request): View
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:51200'],
        ]);

        $file = $request->file('csv_file');
        $csv = new SplFileObject($file->getRealPath());
        $csv->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);

        $headers = $this->normalizeHeaders($csv->fgetcsv() ?: []);
        $missingColumns = array_values(array_diff(self::REQUIRED_COLUMNS, $headers));

        if ($missingColumns !== []) {
            return view('admin.orders.result', [
                'summary' => [
                    'total_rows' => 0,
                    'inserted_orders' => 0,
                    'updated_orders' => 0,
                    'skipped_duplicates' => 0,
                    'matched_orders' => 0,
                    'no_upline_orders' => 0,
                    'total_sales_imported' => 0,
                    'mapped_affiliate_sales' => 0,
                    'no_upline_sales' => 0,
                    'no_upline_usernames' => [],
                    'skipped_unmatched_creators' => 0,
                    'skipped_invalid_rows' => 0,
                    'missing_columns' => $missingColumns,
                    'sample_skipped_rows' => [],
                ],
                'import' => null,
            ]);
        }

        $import = OrderImport::query()->create([
            'imported_by' => $request->user()?->id,
            'file_name' => $file->getClientOriginalName(),
        ]);

        $accounts = TiktokAccount::query()
            ->get(['affiliate_id', 'username_normalized'])
            ->keyBy('username_normalized');

        $summary = [
            'total_rows' => 0,
            'inserted_orders' => 0,
            'updated_orders' => 0,
            'skipped_duplicates' => 0,
            'matched_orders' => 0,
            'no_upline_orders' => 0,
            'total_sales_imported' => 0,
            'mapped_affiliate_sales' => 0,
            'no_upline_sales' => 0,
            'no_upline_usernames' => [],
            'skipped_unmatched_creators' => 0,
            'skipped_invalid_rows' => 0,
            'missing_columns' => [],
            'sample_skipped_rows' => [],
        ];
        $pendingOrders = [];
        $now = now();

        while (! $csv->eof()) {
            $line = $csv->fgetcsv();

            if ($line === false || $line === [null] || $this->isBlankRow($line)) {
                continue;
            }

            $summary['total_rows']++;
            $row = $this->combineRow($headers, $line);
            $orderId = trim((string) Arr::get($row, 'Order ID', ''));
            $creatorUsername = trim((string) Arr::get($row, 'Creator Username', ''));
            $creatorUsernameNormalized = $this->normalizeUsername($creatorUsername);
            $estimatedCommissionBase = $this->parseDecimal(Arr::get($row, 'Est. Commission Base'));

            if ($orderId === '' || $creatorUsernameNormalized === '' || $estimatedCommissionBase === null) {
                $this->skipRow($summary, 'Invalid row', $orderId, $creatorUsername);
                continue;
            }

            $account = $accounts->get($creatorUsernameNormalized);
            $isMatched = (bool) $account;
            $salesAmount = (float) $estimatedCommissionBase;

            $summary['total_sales_imported'] += $salesAmount;

            if ($isMatched) {
                $summary['matched_orders']++;
                $summary['mapped_affiliate_sales'] += $salesAmount;
            } else {
                $summary['no_upline_orders']++;
                $summary['no_upline_sales'] += $salesAmount;
                $summary['skipped_unmatched_creators']++;
                $summary['no_upline_usernames'][$creatorUsernameNormalized] = $creatorUsername ?: $creatorUsernameNormalized;
                $this->recordSampleSkip($summary, 'No Upline', $orderId, $creatorUsername);
            }

            $orderData = [
                'order_import_id' => $import->id,
                'affiliate_id' => $account?->affiliate_id,
                'sales_source' => $isMatched ? 'mapped_affiliate' : 'no_upline',
                'creator_username' => $creatorUsername,
                'creator_username_normalized' => $creatorUsernameNormalized,
                'order_status' => trim((string) Arr::get($row, 'Order Status', '')),
                'estimated_commission_base' => $estimatedCommissionBase,
                'actual_commission_base' => $this->parseDecimal(Arr::get($row, 'Actual Commission Base')),
                'actual_commission_payment' => $this->parseDecimal(Arr::get($row, 'Actual Commission Payment')),
                'payment_amount' => $this->parseDecimal(Arr::get($row, 'Payment Amount')),
                'currency' => $this->nullableString(Arr::get($row, 'Currency')),
                'quantity' => $this->parseInteger(Arr::get($row, 'Quantity')),
                'time_created' => $this->parseDate(Arr::get($row, 'Time Created'))?->toDateTimeString(),
                'payment_time' => $this->parseDate(Arr::get($row, 'Payment time'))?->toDateTimeString(),
                'time_commission_paid' => $this->parseDate(Arr::get($row, 'Time Commission Paid'))?->toDateTimeString(),
                'platform' => $this->nullableString(Arr::get($row, 'Platform')),
                'raw_data' => json_encode($row),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $pendingOrders[$orderId] = [
                'order_id' => $orderId,
                ...$orderData,
            ];

            if (count($pendingOrders) >= 1000) {
                $this->flushOrderBatch($pendingOrders, $summary);
                $pendingOrders = [];
            }
        }

        if ($pendingOrders !== []) {
            $this->flushOrderBatch($pendingOrders, $summary);
        }

        $import->update([
            'total_rows' => $summary['total_rows'],
            'inserted_orders' => $summary['inserted_orders'],
            'updated_orders' => $summary['updated_orders'],
            'skipped_rows' => $summary['skipped_invalid_rows'],
            'matched_orders' => $summary['matched_orders'],
            'no_upline_orders' => $summary['no_upline_orders'],
            'total_sales' => round($summary['total_sales_imported'], 2),
        ]);

        return view('admin.orders.result', compact('summary', 'import'));
    }

    public function destroy(OrderImport $orderImport): RedirectResponse
    {
        $orderImport->delete();

        return redirect()->route('admin.orders.index')
            ->with('success', "Upload '{$orderImport->file_name}' dan semua orders berkaitan telah dipadam.");
    }

    public function destroyAll(): RedirectResponse
    {
        $count = TiktokOrder::query()->count();
        TiktokOrder::query()->delete();
        OrderImport::query()->delete();

        return redirect()->route('admin.orders.index')
            ->with('success', "Semua data orders telah dipadam ({$count} orders). Sila upload CSV baru.");
    }

    private function flushOrderBatch(array $orders, array &$summary): void
    {
        $orderIds = array_keys($orders);
        $existingOrderIds = TiktokOrder::query()
            ->whereIn('order_id', $orderIds)
            ->pluck('order_id')
            ->all();
        $existingCount = count($existingOrderIds);

        TiktokOrder::query()->upsert(
            array_values($orders),
            ['order_id'],
            [
                'order_import_id',
                'affiliate_id',
                'sales_source',
                'creator_username',
                'creator_username_normalized',
                'order_status',
                'estimated_commission_base',
                'actual_commission_base',
                'actual_commission_payment',
                'payment_amount',
                'currency',
                'quantity',
                'time_created',
                'payment_time',
                'time_commission_paid',
                'platform',
                'raw_data',
                'updated_at',
            ]
        );

        $summary['updated_orders'] += $existingCount;
        $summary['inserted_orders'] += count($orders) - $existingCount;
    }

    private function normalizeHeaders(array $headers): array
    {
        return array_map(fn ($header) => trim((string) $header, " \t\n\r\0\x0B\xEF\xBB\xBF"), $headers);
    }

    private function combineRow(array $headers, array $line): array
    {
        $line = array_pad($line, count($headers), null);

        return array_combine($headers, array_slice($line, 0, count($headers))) ?: [];
    }

    private function isBlankRow(array $line): bool
    {
        return collect($line)->every(fn ($value) => trim((string) $value) === '');
    }

    private function normalizeUsername(string $username): string
    {
        return str_replace('@', '', strtolower(trim($username)));
    }

    private function parseDecimal(mixed $value): ?string
    {
        $value = trim(str_replace([',', 'RM', 'MYR'], '', (string) $value));

        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function parseInteger(mixed $value): ?int
    {
        $value = trim((string) $value);

        return $value === '' || ! is_numeric($value) ? null : (int) $value;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        foreach (['d/m/Y H:i:s', 'd/m/Y H:i', 'Y-m-d H:i:s', 'Y-m-d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value);
            } catch (\Throwable) {
                //
            }
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function skipRow(array &$summary, string $reason, string $orderId, string $creatorUsername): void
    {
        $summary['skipped_invalid_rows']++;
        $this->recordSampleSkip($summary, $reason, $orderId, $creatorUsername);
    }

    private function recordSampleSkip(array &$summary, string $reason, string $orderId, string $creatorUsername): void
    {
        if (count($summary['sample_skipped_rows']) >= 10) {
            return;
        }

        $summary['sample_skipped_rows'][] = [
            'reason' => $reason,
            'order_id' => $orderId ?: '-',
            'creator_username' => $creatorUsername ?: '-',
        ];
    }
}
