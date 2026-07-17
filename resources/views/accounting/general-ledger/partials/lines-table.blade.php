<table class="odoo-table w-full">
    <thead>
        <tr>
            <th>Tanggal</th>
            <th>No Bukti</th>
            <th>No Doc</th>
            <th>Pihak Kedua</th>
            <th>Deskripsi</th>
            <th class="text-right">Debet</th>
            <th class="text-right">Kredit</th>
            <th class="text-right">Saldo</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($ledgerLines as $row)
            @php $line = $row['line']; @endphp
            <tr class="{{ ($row['highlight'] ?? false) ? 'bg-yellow-50 ring-1 ring-yellow-300' : '' }}">
                <td>{{ $line->journalEntry->entry_date->format('d M Y') }}</td>
                <td>
                    <a href="{{ route('accounting.journal-entries.show', $line->journalEntry) }}" class="odoo-link font-medium">
                        {{ $line->journalEntry->entry_number }}
                    </a>
                </td>
                <td>{{ $line->journalEntry->document_number ?? '—' }}</td>
                <td>{{ $line->journalEntry->partner?->displayName() ?? '—' }}</td>
                <td>{{ $line->description ?? $line->journalEntry->notes ?? $line->journalEntry->description ?? '—' }}</td>
                <td class="text-right font-mono">{{ $line->debit > 0 ? number_format($line->debit, 2, ',', '.') : '—' }}</td>
                <td class="text-right font-mono">{{ $line->credit > 0 ? number_format($line->credit, 2, ',', '.') : '—' }}</td>
                <td class="text-right font-mono font-medium">{{ number_format($row['balance'], 2, ',', '.') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="text-center text-gray-500 py-8">{{ $emptyMessage ?? 'Tidak ada transaksi posted pada periode ini.' }}</td>
            </tr>
        @endforelse
    </tbody>
    @if (isset($showFooter) && $showFooter && $ledgerLines->isNotEmpty())
        <tfoot>
            <tr class="bg-gray-50 font-semibold">
                <td colspan="5" class="text-right">Subtotal</td>
                <td class="text-right font-mono">{{ number_format($totalDebit, 2, ',', '.') }}</td>
                <td class="text-right font-mono">{{ number_format($totalCredit, 2, ',', '.') }}</td>
                <td class="text-right font-mono">{{ number_format($endingBalance, 2, ',', '.') }}</td>
            </tr>
        </tfoot>
    @endif
</table>
