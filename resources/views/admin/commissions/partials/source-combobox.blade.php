<div class="relative" data-combobox data-entry-source-combobox>
    <input id="source" name="source" type="hidden" value="{{ $selectedSource }}" data-combobox-value>
    <div class="mt-2 flex overflow-hidden rounded-lg border border-slate-300 bg-white shadow-sm focus-within:border-emerald-500 focus-within:ring-4 focus-within:ring-emerald-100">
        <input
            id="source_search"
            type="text"
            autocomplete="off"
            placeholder="All Sources"
            class="min-w-0 flex-1 border-0 px-3 py-2.5 text-sm outline-none"
            data-combobox-input
        >
        <button type="button" class="border-l border-slate-200 px-3 text-xs font-bold text-slate-500 hover:bg-slate-50 hover:text-slate-900" data-combobox-clear>
            Clear
        </button>
    </div>
    <div class="absolute z-30 mt-2 hidden max-h-72 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg" data-combobox-options>
        <button type="button" class="block w-full px-3 py-2 text-left text-sm font-semibold text-slate-700 hover:bg-emerald-50 hover:text-emerald-800" data-combobox-option data-id="" data-label="All Sources" data-search="all sources">
            All Sources
        </button>
        @foreach ($sourceOptions as $source)
            @php
                $sourceLabel = trim($source->name.' '.($source->affiliate_code ? '('.$source->affiliate_code.')' : ''));
                $sourceSearch = strtolower(trim($source->name.' '.$source->affiliate_code));
            @endphp
            <button
                type="button"
                class="block w-full px-3 py-2 text-left text-sm hover:bg-emerald-50"
                data-combobox-option
                data-id="{{ $source->id }}"
                data-label="{{ $sourceLabel }}"
                data-search="{{ $sourceSearch }}"
            >
                <span class="block font-semibold text-slate-900">{{ $source->name }}</span>
                @if ($source->affiliate_code)
                    <span class="block text-xs text-slate-500">{{ $source->affiliate_code }}</span>
                @endif
            </button>
        @endforeach
        <p class="hidden px-3 py-3 text-sm text-slate-500" data-combobox-empty>No source found</p>
    </div>
</div>
