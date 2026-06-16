@if ($errors->any())
    <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ $errors->first() }}
    </div>
@endif

<div class="grid gap-5 md:grid-cols-2">
    <div>
        <label for="month" class="block text-sm font-medium text-slate-700">Month</label>
        <select id="month" name="month" class="form-field">
            @foreach ($months as $number => $name)
                <option value="{{ $number }}" @selected((int) old('month', $setting->month) === $number)>{{ $name }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="year" class="block text-sm font-medium text-slate-700">Year</label>
        <input id="year" name="year" type="number" min="2020" max="2100" value="{{ old('year', $setting->year) }}" class="form-field">
    </div>
</div>

<div class="grid gap-5 md:grid-cols-2">
    @foreach ([
        'personal_rate' => 'Personal Rate',
        'manager_bonus_rate' => 'Manager Bonus Rate',
        'l1_rate' => 'Overriding L1 Rate',
        'l2_rate' => 'Overriding L2 Rate',
        'l3_rate' => 'Overriding L3 Rate',
    ] as $field => $label)
        <div>
            <label for="{{ $field }}" class="block text-sm font-medium text-slate-700">{{ $label }}</label>
            <input id="{{ $field }}" name="{{ $field }}" type="number" min="0" max="100" step="0.01" value="{{ old($field, rtrim(rtrim(number_format((float) $setting->{$field} * 100, 4, '.', ''), '0'), '.')) }}" class="form-field">
            <p class="mt-1 text-xs text-slate-500">Enter percentage value. Example: 10 = 10%, 1 = 1%, 0.3 = 0.3%</p>
        </div>
    @endforeach

    <div>
        <label for="status" class="block text-sm font-medium text-slate-700">Status</label>
        <select id="status" name="status" class="form-field">
            <option value="active" @selected(old('status', $setting->status) === 'active')>Active</option>
            <option value="inactive" @selected(old('status', $setting->status) === 'inactive')>Inactive</option>
        </select>
    </div>
</div>
