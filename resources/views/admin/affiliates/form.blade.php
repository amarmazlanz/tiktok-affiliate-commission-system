@if ($errors->any())
    <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ $errors->first() }}
    </div>
@endif

<div>
    <label for="name" class="block text-sm font-medium text-slate-700">Name</label>
    <input id="name" name="name" type="text" value="{{ old('name', $affiliate->name) }}" required class="form-field">
</div>

<div>
    <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
    <input id="email" name="email" type="email" value="{{ old('email', $affiliate->email) }}" @required(! $affiliate->exists || $affiliate->user) class="form-field">
    @if (! $affiliate->exists)
        <p class="mt-1 text-xs text-slate-500">Login affiliate akan dibuat automatik dengan password default: password.</p>
    @elseif (! $affiliate->user)
        <p class="mt-1 text-xs text-slate-500">Affiliate luar tidak mempunyai login access. Email boleh dibiarkan kosong.</p>
    @endif
</div>

<div>
    <label for="phone" class="block text-sm font-medium text-slate-700">Phone</label>
    <input id="phone" name="phone" type="text" value="{{ old('phone', $affiliate->phone) }}" class="form-field">
</div>

<div class="grid gap-5 md:grid-cols-2">
    <div>
        <label for="status" class="block text-sm font-medium text-slate-700">Status</label>
        <select id="status" name="status" class="form-field">
            <option value="active" @selected(old('status', $affiliate->status) === 'active')>Active</option>
            <option value="inactive" @selected(old('status', $affiliate->status) === 'inactive')>Inactive</option>
        </select>
    </div>

    <div>
        <label for="upline_id" class="block text-sm font-medium text-slate-700">Direct Upline</label>
        <select id="upline_id" name="upline_id" class="form-field">
            <option value="">No upline</option>
            @foreach ($uplines as $upline)
                <option value="{{ $upline->id }}" @selected((string) old('upline_id', $affiliate->upline_id) === (string) $upline->id)>
                    {{ $upline->name }} ({{ $upline->affiliate_code ?: ($upline->email ?: 'No login access') }})
                </option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-slate-500">Manager level is determined automatically based on the selected upline/downline hierarchy.</p>
    </div>
</div>
