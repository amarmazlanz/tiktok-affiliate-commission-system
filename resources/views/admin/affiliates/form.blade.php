@if ($errors->any())
    <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ $errors->first() }}
    </div>
@endif

<div>
    <label for="name" class="block text-sm font-medium text-slate-700">Name</label>
    <input id="name" name="name" type="text" value="{{ old('name', $affiliate->name) }}" required
        class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
</div>

<div>
    <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
    <input id="email" name="email" type="email" value="{{ old('email', $affiliate->email) }}" required
        class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
    @if (! $affiliate->exists)
        <p class="mt-1 text-xs text-slate-500">Login affiliate akan dibuat automatik dengan password default: password.</p>
    @endif
</div>

<div>
    <label for="phone" class="block text-sm font-medium text-slate-700">Phone</label>
    <input id="phone" name="phone" type="text" value="{{ old('phone', $affiliate->phone) }}"
        class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
</div>

<div class="grid gap-5 md:grid-cols-2">
    <div>
        <label for="status" class="block text-sm font-medium text-slate-700">Status</label>
        <select id="status" name="status"
            class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
            <option value="active" @selected(old('status', $affiliate->status) === 'active')>Active</option>
            <option value="inactive" @selected(old('status', $affiliate->status) === 'inactive')>Inactive</option>
        </select>
    </div>

    <div>
        <label for="upline_id" class="block text-sm font-medium text-slate-700">Direct Upline</label>
        <select id="upline_id" name="upline_id"
            class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
            <option value="">No upline</option>
            @foreach ($uplines as $upline)
                <option value="{{ $upline->id }}" @selected((string) old('upline_id', $affiliate->upline_id) === (string) $upline->id)>
                    {{ $upline->name }} ({{ $upline->email }})
                </option>
            @endforeach
        </select>
    </div>
</div>
