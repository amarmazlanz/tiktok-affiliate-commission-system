<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionRateSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CommissionRateSettingController extends Controller
{
    public function index(): View
    {
        return view('admin.commission-rate-settings.index', [
            'settings' => CommissionRateSetting::query()
                ->latest('year')
                ->latest('month')
                ->paginate(15),
            'months' => $this->months(),
        ]);
    }

    public function create(): View
    {
        return view('admin.commission-rate-settings.create', [
            'setting' => new CommissionRateSetting([
                'month' => now()->month,
                'year' => now()->year,
                'status' => 'active',
                ...CommissionRateSetting::DEFAULT_RATES,
            ]),
            'months' => $this->months(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        CommissionRateSetting::create($this->validateSetting($request));

        return redirect()
            ->route('admin.commission-rate-settings.index')
            ->with('success', 'Commission rate setting berjaya ditambah.');
    }

    public function edit(CommissionRateSetting $commissionRateSetting): View
    {
        return view('admin.commission-rate-settings.edit', [
            'setting' => $commissionRateSetting,
            'months' => $this->months(),
        ]);
    }

    public function update(Request $request, CommissionRateSetting $commissionRateSetting): RedirectResponse
    {
        $commissionRateSetting->update($this->validateSetting($request, $commissionRateSetting));

        return redirect()
            ->route('admin.commission-rate-settings.index')
            ->with('success', 'Commission rate setting berjaya dikemaskini.');
    }

    private function validateSetting(Request $request, ?CommissionRateSetting $setting = null): array
    {
        $uniqueRule = Rule::unique('commission_rate_settings')
            ->where(fn ($query) => $query
                ->where('month', $request->input('month'))
                ->where('year', $request->input('year')));

        if ($setting) {
            $uniqueRule->ignore($setting->id);
        }

        $data = $request->validate([
            'month' => ['required', 'integer', 'min:1', 'max:12', $uniqueRule],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'personal_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'manager_bonus_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'l1_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'l2_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'l3_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ], [
            'month.unique' => 'Rate setting untuk bulan dan tahun ini sudah wujud.',
        ]);

        foreach (['personal_rate', 'manager_bonus_rate', 'l1_rate', 'l2_rate', 'l3_rate'] as $field) {
            $data[$field] = ((float) $data[$field]) / 100;
        }

        return $data;
    }

    private function months(): array
    {
        return [
            1 => 'January',
            2 => 'February',
            3 => 'March',
            4 => 'April',
            5 => 'May',
            6 => 'June',
            7 => 'July',
            8 => 'August',
            9 => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December',
        ];
    }
}
