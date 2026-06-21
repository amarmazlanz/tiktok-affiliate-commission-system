<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TiktokAccount;
use App\Models\TiktokOrder;
use Carbon\Carbon;
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
                    'skipped_unmatched_creators' => 0,
                    'skipped_invalid_rows' => 0,
                    'missing_columns' => $missingColumns,
                    'sample_skipped_rows' => [],
                ],
            ]);
        }

        $accounts = TiktokAccount::query()
            ->get(['affiliate_id', 'username_normalized'])
            ->keyBy('username_normalized');

        $summary = [
            'total_rows' => 0,
            'inserted_orders' => 0,
            'updated_orders' => 0,
            'skipped_duplicates' => 0,
            'skipped_unmatched_creators' => 0,
            'skipped_invalid_rows' => 0,
            'missing_columns' => [],
            'sample_skipped_rows' => [],
        ];

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

            if (! $account) {
                $summary['skipped_unmatched_creators']++;
                $this->recordSampleSkip($summary, 'Unmatched creator', $orderId, $creatorUsername);
                continue;
            }

            $orderData = [
                'affiliate_id' => $account->affiliate_id,
                'creator_username' => $creatorUsername,
                'creator_username_normalized' => $creatorUsernameNormalized,
                'order_status' => trim((string) Arr::get($row, 'Order Status', '')),
                'estimated_commission_base' => $estimatedCommissionBase,
                'actual_commission_base' => $this->parseDecimal(Arr::get($row, 'Actual Commission Base')),
                'actual_commission_payment' => $this->parseDecimal(Arr::get($row, 'Actual Commission Payment')),
                'payment_amount' => $this->parseDecimal(Arr::get($row, 'Payment Amount')),
                'currency' => $this->nullableString(Arr::get($row, 'Currency')),
                'quantity' => $this->parseInteger(Arr::get($row, 'Quantity')),
                'time_created' => $this->parseDate(Arr::get($row, 'Time Created')),
                'payment_time' => $this->parseDate(Arr::get($row, 'Payment time')),
                'time_commission_paid' => $this->parseDate(Arr::get($row, 'Time Commission Paid')),
                'platform' => $this->nullableString(Arr::get($row, 'Platform')),
                'raw_data' => $row,
            ];

            $order = TiktokOrder::query()->where('order_id', $orderId)->first();

            if ($order) {
                $order->update($orderData);
                $summary['updated_orders']++;
            } else {
                TiktokOrder::create([
                    'order_id' => $orderId,
                    ...$orderData,
                ]);
                $summary['inserted_orders']++;
            }
        }

        return view('admin.orders.result', compact('summary'));
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

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
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
