@extends('layouts.odoo')

@section('title', $entry->exists ? $entry->entry_number : 'Buat Jurnal')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">{{ $entry->exists ? 'Edit Jurnal' : 'Buat Jurnal Baru' }}</h1>
        <a href="{{ route('accounting.journal-entries.index') }}" class="odoo-btn-secondary">Batal</a>
    </div>

    <form method="POST"
        action="{{ $entry->exists ? route('accounting.journal-entries.update', $entry) : route('accounting.journal-entries.store') }}"
        x-data="journalForm(@js([
            'isEdit' => $entry->exists,
            'journalTypes' => $journalTypes->map(fn($t) => ['id' => $t->id, 'name' => $t->name])->values(),
            'suggestedNumber' => $suggestedNumber,
            'selectedJournalTypeId' => $selectedJournalTypeId,
            'existingLines' => $entry->exists ? $entry->lines->values()->map(function ($l, $index) use ($entry) {
                $notes = $l->notes;
                if ($index === 0 && blank($notes)) {
                    $notes = $entry->notes ?? $entry->description;
                }

                $exchangeRate = (float) ($l->exchange_rate ?? 1);
                if ($index === 0 && $exchangeRate === 1.0 && (float) ($entry->exchange_rate ?? 1) !== 1.0) {
                    $exchangeRate = (float) $entry->exchange_rate;
                }

                return [
                    'account_id' => $l->account_id,
                    'account_label' => $l->account?->displayName(),
                    'counter_account_id' => $l->counter_account_id,
                    'counter_account_label' => $l->counterAccount?->displayName(),
                    'description' => $l->description,
                    'notes' => $notes,
                    'exchange_rate' => $exchangeRate,
                    'debit' => (float) $l->debit,
                    'credit' => (float) $l->credit,
                ];
            }) : [],
        ]))">
        @csrf
        @if ($entry->exists)
            @method('PUT')
        @endif

        <div class="bg-white rounded border border-odoo-border shadow-sm p-4 mb-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Tipe Jurnal *</label>
                <select name="journal_type_id" x-model="journalTypeId" @change="fetchSuggestedNumber()"
                    class="w-full border border-odoo-border rounded px-3 py-2 text-sm" required>
                    @foreach ($journalTypes as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">
                    No Bukti
                    <span class="text-gray-400">(otomatis, bisa diedit)</span>
                </label>
                <input type="text" name="entry_number" x-model="entryNumber"
                    placeholder="Kosongkan untuk nomor otomatis"
                    class="w-full border border-odoo-border rounded px-3 py-2 text-sm"
                    {{ $entry->exists ? 'required' : '' }}>
                <p class="text-xs text-gray-400 mt-1">Saran: <span x-text="suggestedNumber" class="font-mono"></span></p>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Tanggal *</label>
                <input type="date" name="entry_date" x-model="entryDate" @change="updatePeriod()"
                    class="w-full border border-odoo-border rounded px-3 py-2 text-sm" required>
                <input type="hidden" name="period" :value="period">
                <p class="text-xs text-gray-500 mt-1">
                    Periode: <span x-text="periodLabel" class="font-medium text-odoo-purple"></span>
                    <span class="text-gray-400">(otomatis dari bulan tanggal)</span>
                </p>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">No Doc / Giro</label>
                <input type="text" name="document_number" value="{{ old('document_number', $entry->document_number) }}"
                    class="w-full border border-odoo-border rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Pihak Kedua</label>
                @include('accounting.partials.partner-autocomplete', [
                    'name' => 'partner_id',
                    'selected' => $selectedPartner,
                ])
            </div>
        </div>

        @if ($errors->any())
            <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded text-sm">
                <ul class="list-disc ml-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white rounded border border-odoo-border shadow-sm overflow-x-auto mb-4">
            <div class="flex items-center justify-between px-4 py-3 border-b border-odoo-border">
                <span class="font-medium">Baris Jurnal</span>
                <button type="button" @click="addLine()" class="text-sm odoo-link">+ Tambah Baris</button>
            </div>
            <table class="w-full text-sm min-w-[1100px]">
                <colgroup>
                    <col>
                    <col>
                    <col>
                    <col>
                    <col class="w-32">
                    <col class="w-32">
                    <col class="w-24">
                    <col class="w-36">
                    <col class="w-36">
                    <col class="w-16">
                </colgroup>
                <thead>
                    <tr class="bg-gray-50 text-xs uppercase text-gray-600">
                        <th class="px-2 py-2 text-left" rowspan="2">Akun *</th>
                        <th class="px-2 py-2 text-left" rowspan="2">Akun Lawan</th>
                        <th class="px-2 py-2 text-left" rowspan="2">Deskripsi</th>
                        <th class="px-2 py-2 text-left" rowspan="2">Keterangan</th>
                        <th class="px-2 py-2 text-right w-32" rowspan="2">Debet</th>
                        <th class="px-2 py-2 text-right w-32" rowspan="2">Kredit</th>
                        <th class="px-2 py-2 text-right w-24" rowspan="2">Kurs</th>
                        <th class="px-2 py-2 text-center bg-amber-50 border-l border-odoo-border" colspan="2">Posted to IDR</th>
                        <th class="px-2 py-2 w-16" rowspan="2"></th>
                    </tr>
                    <tr class="bg-amber-50/70 text-xs uppercase text-gray-600">
                        <th class="px-2 py-2 text-right w-36 border-l border-odoo-border">Debet</th>
                        <th class="px-2 py-2 text-right w-36">Kredit</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(line, index) in lines" :key="index">
                        <tr class="border-b border-odoo-border">
                            <td class="px-2 py-1 align-top">
                                <div
                                    class="relative min-w-[220px]"
                                    x-data="accountAutocomplete({
                                        searchUrl: '{{ route('accounting.accounts.search') }}',
                                        fieldName: 'lines[' + index + '][account_id]',
                                        selectedId: line.account_id,
                                        selectedLabel: line.account_label,
                                        required: true,
                                    })"
                                    @click.outside="close()"
                                >
                                    <input type="hidden" :name="fieldName" :value="accountId" :required="required">
                                    <input type="text" x-model="query"
                                        @input.debounce.300ms="search()"
                                        @focus="open = true; if (results.length === 0 && query.length >= 1) search()"
                                        @keydown.arrow-down.prevent="highlightNext()"
                                        @keydown.arrow-up.prevent="highlightPrev()"
                                        @keydown.enter.prevent="selectHighlighted()"
                                        @keydown.escape="close()"
                                        placeholder="Kode atau nama akun..."
                                        autocomplete="off"
                                        class="w-full border border-odoo-border rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-odoo-purple">
                                    <div x-show="open && results.length > 0" x-cloak
                                        class="absolute z-30 mt-0.5 w-full min-w-[280px] bg-white border border-odoo-border rounded shadow-lg max-h-48 overflow-y-auto">
                                        <template x-for="(item, ri) in results" :key="item.id">
                                            <button type="button" @click="select(item)"
                                                class="w-full text-left px-2 py-1.5 text-xs hover:bg-blue-50 border-b border-odoo-border last:border-0"
                                                :class="{ 'bg-blue-50': ri === highlighted }">
                                                <span class="font-mono text-odoo-purple" x-text="item.code"></span>
                                                <span class="text-gray-400 mx-1">—</span>
                                                <span x-text="item.name"></span>
                                            </button>
                                        </template>
                                    </div>
                                    <p x-show="open && query.length >= 1 && results.length === 0 && !loading" x-cloak
                                        class="absolute z-30 mt-0.5 w-full bg-white border border-odoo-border rounded shadow px-2 py-1 text-xs text-gray-500">
                                        Tidak ditemukan.
                                    </p>
                                </div>
                            </td>
                            <td class="px-2 py-1 align-top">
                                <div
                                    class="relative min-w-[200px]"
                                    x-data="accountAutocomplete({
                                        searchUrl: '{{ route('accounting.accounts.search') }}',
                                        fieldName: 'lines[' + index + '][counter_account_id]',
                                        selectedId: line.counter_account_id,
                                        selectedLabel: line.counter_account_label,
                                        required: false,
                                    })"
                                    @click.outside="close()"
                                >
                                    <input type="hidden" :name="fieldName" :value="accountId">
                                    <input type="text" x-model="query"
                                        @input.debounce.300ms="search()"
                                        @focus="open = true; if (results.length === 0 && query.length >= 1) search()"
                                        @keydown.arrow-down.prevent="highlightNext()"
                                        @keydown.arrow-up.prevent="highlightPrev()"
                                        @keydown.enter.prevent="selectHighlighted()"
                                        @keydown.escape="close()"
                                        placeholder="Opsional..."
                                        autocomplete="off"
                                        class="w-full border border-odoo-border rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-odoo-purple">
                                    <div x-show="open && results.length > 0" x-cloak
                                        class="absolute z-30 mt-0.5 w-full min-w-[280px] bg-white border border-odoo-border rounded shadow-lg max-h-48 overflow-y-auto">
                                        <template x-for="(item, ri) in results" :key="item.id">
                                            <button type="button" @click="select(item)"
                                                class="w-full text-left px-2 py-1.5 text-xs hover:bg-blue-50 border-b border-odoo-border last:border-0"
                                                :class="{ 'bg-blue-50': ri === highlighted }">
                                                <span class="font-mono text-odoo-purple" x-text="item.code"></span>
                                                <span class="text-gray-400 mx-1">—</span>
                                                <span x-text="item.name"></span>
                                            </button>
                                        </template>
                                    </div>
                                    <p x-show="open && query.length >= 1 && results.length === 0 && !loading" x-cloak
                                        class="absolute z-30 mt-0.5 w-full bg-white border border-odoo-border rounded shadow px-2 py-1 text-xs text-gray-500">
                                        Tidak ditemukan.
                                    </p>
                                </div>
                            </td>
                            <td class="px-2 py-1">
                                <input type="text" :name="'lines[' + index + '][description]'" x-model="line.description"
                                    class="w-full min-w-[140px] border border-odoo-border rounded px-2 py-1 text-sm">
                            </td>
                            <td class="px-2 py-1">
                                <input type="text" :name="'lines[' + index + '][notes]'" x-model="line.notes"
                                    class="w-full min-w-[140px] border border-odoo-border rounded px-2 py-1 text-sm">
                            </td>
                            <td class="px-2 py-1 w-32 align-top">
                                <input type="number" step="0.01" min="0" :name="'lines[' + index + '][debit]'" x-model="line.debit"
                                    @input="line.credit = line.debit > 0 ? 0 : line.credit"
                                    class="w-full border border-odoo-border rounded px-2 py-1 text-sm text-right font-mono">
                            </td>
                            <td class="px-2 py-1 w-32 align-top">
                                <input type="number" step="0.01" min="0" :name="'lines[' + index + '][credit]'" x-model="line.credit"
                                    @input="line.debit = line.credit > 0 ? 0 : line.debit"
                                    class="w-full border border-odoo-border rounded px-2 py-1 text-sm text-right font-mono">
                            </td>
                            <td class="px-2 py-1 w-24 align-top">
                                <input type="number" step="0.000001" min="0" :name="'lines[' + index + '][exchange_rate]'" x-model="line.exchange_rate"
                                    class="w-full border border-odoo-border rounded px-2 py-1 text-sm text-right font-mono">
                            </td>
                            <td class="px-2 py-1 w-36 align-top bg-amber-50/40 border-l border-odoo-border">
                                <div class="px-2 py-1 text-sm text-right font-mono text-gray-700" x-text="formatAmount(lineIdrDebit(line))"></div>
                            </td>
                            <td class="px-2 py-1 w-36 align-top bg-amber-50/40">
                                <div class="px-2 py-1 text-sm text-right font-mono text-gray-700" x-text="formatAmount(lineIdrCredit(line))"></div>
                            </td>
                            <td class="px-2 py-1 w-16 align-top">
                                <button type="button" @click="removeLine(index)" class="text-red-500 text-xs" x-show="lines.length > 2">Hapus</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
                <tfoot class="bg-gray-50 font-semibold">
                    <tr>
                        <td colspan="4" class="text-right px-2 py-2">Total</td>
                        <td class="text-right px-2 py-2 font-mono w-32" x-text="formatNumber(totalDebit)"></td>
                        <td class="text-right px-2 py-2 font-mono w-32" x-text="formatNumber(totalCredit)"></td>
                        <td class="px-2 py-2 w-24"></td>
                        <td class="text-right px-2 py-2 font-mono w-36 bg-amber-50/40 border-l border-odoo-border" x-text="formatNumber(totalIdrDebit)"></td>
                        <td class="text-right px-2 py-2 font-mono w-36 bg-amber-50/40" x-text="formatNumber(totalIdrCredit)"></td>
                        <td class="px-2 py-2 w-16">
                            <span :class="isBalanced ? 'text-green-600' : 'text-red-600'" class="text-xs" x-text="isBalanced ? '✓ Seimbang' : '✗ Tidak seimbang'"></span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="odoo-btn-primary" :disabled="!isBalanced">Simpan Jurnal</button>
        </div>
    </form>

    <script>
        function partnerAutocomplete(config) {
            return {
                fieldName: config.fieldName,
                partnerId: config.selected?.id ?? '',
                query: config.selected?.label ?? '',
                results: [],
                open: false,
                loading: false,
                highlighted: 0,

                async search() {
                    if (this.query.length < 1) {
                        this.results = [];
                        this.partnerId = '';
                        return;
                    }

                    this.loading = true;
                    try {
                        const res = await fetch(`${config.searchUrl}?q=${encodeURIComponent(this.query)}`);
                        this.results = await res.json();
                        this.highlighted = 0;
                        this.open = true;
                    } finally {
                        this.loading = false;
                    }
                },

                select(item) {
                    this.partnerId = item.id;
                    this.query = item.label;
                    this.close();
                },

                selectHighlighted() {
                    if (this.results[this.highlighted]) {
                        this.select(this.results[this.highlighted]);
                    }
                },

                highlightNext() {
                    if (this.highlighted < this.results.length - 1) {
                        this.highlighted++;
                    }
                },

                highlightPrev() {
                    if (this.highlighted > 0) {
                        this.highlighted--;
                    }
                },

                close() {
                    this.open = false;
                },
            };
        }

        function accountAutocomplete(config) {
            return {
                fieldName: config.fieldName,
                accountId: config.selectedId || '',
                query: config.selectedLabel || '',
                required: config.required || false,
                results: [],
                open: false,
                loading: false,
                highlighted: 0,

                async search() {
                    if (this.query.length < 1) {
                        this.results = [];
                        this.accountId = '';
                        return;
                    }

                    this.loading = true;
                    try {
                        const res = await fetch(`${config.searchUrl}?q=${encodeURIComponent(this.query)}`);
                        this.results = await res.json();
                        this.highlighted = 0;
                        this.open = true;
                    } finally {
                        this.loading = false;
                    }
                },

                select(item) {
                    this.accountId = item.id;
                    this.query = item.label;
                    this.close();
                },

                selectHighlighted() {
                    if (this.results[this.highlighted]) {
                        this.select(this.results[this.highlighted]);
                    }
                },

                highlightNext() {
                    if (this.highlighted < this.results.length - 1) {
                        this.highlighted++;
                    }
                },

                highlightPrev() {
                    if (this.highlighted > 0) {
                        this.highlighted--;
                    }
                },

                close() {
                    this.open = false;
                },
            };
        }

        function journalForm(config) {
            const emptyLine = () => ({
                account_id: '',
                account_label: '',
                counter_account_id: '',
                counter_account_label: '',
                description: '',
                notes: '',
                exchange_rate: 1,
                debit: 0,
                credit: 0,
            });

            const monthNames = [
                'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
            ];

            const initialDate = @js(old('entry_date', $entry->entry_date?->format('Y-m-d') ?? now()->toDateString()));
            const initialPeriod = initialDate
                ? new Date(initialDate + 'T00:00:00').getMonth() + 1
                : {{ (int) now()->format('n') }};

            return {
                journalTypeId: config.selectedJournalTypeId || config.journalTypes[0]?.id,
                entryNumber: config.isEdit ? (config.suggestedNumber || '') : '',
                suggestedNumber: config.suggestedNumber || '',
                entryDate: initialDate,
                period: initialPeriod,
                monthNames,
                lines: config.existingLines.length ? config.existingLines : [emptyLine(), emptyLine()],
                updatePeriod() {
                    if (!this.entryDate) {
                        return;
                    }
                    const date = new Date(this.entryDate + 'T00:00:00');
                    this.period = date.getMonth() + 1;
                },
                get periodLabel() {
                    if (!this.period) {
                        return '—';
                    }
                    return `Periode ${this.period} (${this.monthNames[this.period - 1]})`;
                },
                addLine() { this.lines.push(emptyLine()); },
                removeLine(index) { this.lines.splice(index, 1); },
                lineRate(line) {
                    const rate = parseFloat(line.exchange_rate || 0);
                    return rate > 0 ? rate : 1;
                },
                lineIdrDebit(line) {
                    return parseFloat(line.debit || 0) * this.lineRate(line);
                },
                lineIdrCredit(line) {
                    return parseFloat(line.credit || 0) * this.lineRate(line);
                },
                get totalDebit() {
                    return this.lines.reduce((s, l) => s + parseFloat(l.debit || 0), 0);
                },
                get totalCredit() {
                    return this.lines.reduce((s, l) => s + parseFloat(l.credit || 0), 0);
                },
                get totalIdrDebit() {
                    return this.lines.reduce((s, l) => s + this.lineIdrDebit(l), 0);
                },
                get totalIdrCredit() {
                    return this.lines.reduce((s, l) => s + this.lineIdrCredit(l), 0);
                },
                get isBalanced() {
                    return Math.abs(this.totalDebit - this.totalCredit) < 0.01 && this.totalDebit > 0;
                },
                formatNumber(n) {
                    return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2 }).format(n);
                },
                formatAmount(n) {
                    if (!n || Math.abs(n) < 0.005) {
                        return '—';
                    }
                    return this.formatNumber(n);
                },
                async fetchSuggestedNumber() {
                    const res = await fetch(`{{ route('accounting.journal-entries.preview-number') }}?journal_type_id=${this.journalTypeId}`);
                    const data = await res.json();
                    this.suggestedNumber = data.entry_number;
                    if (!this.entryNumber || this.entryNumber === '') {
                        this.entryNumber = '';
                    }
                },
            };
        }
    </script>
@endsection
