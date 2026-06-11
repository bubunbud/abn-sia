@props([
    'name' => 'partner_id',
    'selected' => null,
])

<div
    x-data="partnerAutocomplete({
        searchUrl: '{{ route('accounting.partners.search') }}',
        fieldName: '{{ $name }}',
        selected: @js($selected),
    })"
    class="relative"
    @click.outside="close()"
>
    <input type="hidden" :name="fieldName" :value="partnerId">

    <input
        type="text"
        x-model="query"
        @input.debounce.300ms="search()"
        @focus="open = true; if (results.length === 0 && query.length >= 1) search()"
        @keydown.arrow-down.prevent="highlightNext()"
        @keydown.arrow-up.prevent="highlightPrev()"
        @keydown.enter.prevent="selectHighlighted()"
        @keydown.escape="close()"
        placeholder="Ketik kode atau nama..."
        autocomplete="off"
        class="w-full border border-odoo-border rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-odoo-purple"
    >

    <div
        x-show="open && results.length > 0"
        x-cloak
        class="absolute z-20 mt-1 w-full bg-white border border-odoo-border rounded shadow-lg max-h-56 overflow-y-auto"
    >
        <template x-for="(item, index) in results" :key="item.id">
            <button
                type="button"
                @click="select(item)"
                class="w-full text-left px-3 py-2 text-sm hover:bg-blue-50 border-b border-odoo-border last:border-0"
                :class="{ 'bg-blue-50': index === highlighted }"
            >
                <span class="font-mono text-odoo-purple" x-text="item.code"></span>
                <span class="text-gray-400 mx-1">—</span>
                <span x-text="item.name"></span>
                <span class="ml-2 text-xs text-gray-400" x-text="item.type_label"></span>
            </button>
        </template>
    </div>

    <p x-show="open && query.length >= 1 && results.length === 0 && !loading" x-cloak class="absolute z-20 mt-1 w-full bg-white border border-odoo-border rounded shadow px-3 py-2 text-sm text-gray-500">
        Tidak ditemukan.
    </p>
</div>
