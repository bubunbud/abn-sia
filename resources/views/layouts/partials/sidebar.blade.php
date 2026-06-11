@php
    $menu = [
        ['label' => 'Dashboard', 'route' => 'accounting.dashboard', 'icon' => '▣'],
        ['label' => 'Chart of Accounts', 'route' => 'accounting.accounts.index', 'icon' => '☰'],
        ['label' => 'Pihak Kedua', 'route' => 'accounting.partners.index', 'icon' => '◎'],
        ['label' => 'Journal Entries', 'route' => 'accounting.journal-entries.index', 'icon' => '✎'],
        ['label' => 'General Ledger', 'route' => 'accounting.general-ledger.index', 'icon' => '≡'],
        ['label' => 'Trial Balance', 'route' => 'accounting.trial-balance.index', 'icon' => '∑'],
        [
            'label' => 'Reports',
            'children' => [
                ['label' => 'Balance Sheet', 'route' => 'accounting.reports.balance-sheet'],
                ['label' => 'Balance Sheet Detail', 'route' => 'accounting.reports.balance-sheet-detail'],
                ['label' => 'Profit & Loss', 'route' => 'accounting.reports.profit-loss'],
                ['label' => 'Profit & Loss Detail', 'route' => 'accounting.reports.profit-loss-detail'],
            ],
        ],
        ['label' => 'Period Closing', 'route' => 'accounting.period-closing.index', 'icon' => '◷'],
        [
            'label' => 'Tax',
            'children' => [
                ['label' => 'Tax Codes', 'route' => 'accounting.tax-codes.index'],
                ['label' => 'e-Faktur Export', 'route' => null, 'disabled' => true],
            ],
        ],
    ];

    if (auth()->user()?->isAdmin()) {
        $menu[] = ['label' => 'Pengguna', 'route' => 'accounting.users.index', 'icon' => '👤'];
    }
@endphp

<aside class="w-56 bg-odoo-sidebar border-r border-odoo-border shrink-0" x-data="{ openReports: {{ request()->routeIs('accounting.reports.*') ? 'true' : 'false' }}, openTax: {{ request()->routeIs('accounting.tax-codes.*') ? 'true' : 'false' }} }">
    <div class="px-3 py-3 text-xs font-bold uppercase tracking-wider text-odoo-purple">
        Accounting
    </div>
    <nav class="px-2 pb-4 space-y-0.5 text-sm">
        @foreach ($menu as $item)
            @if (!empty($item['children']))
                @php
                    $groupKey = $item['label'] === 'Reports' ? 'openReports' : 'openTax';
                    $isActive = collect($item['children'])->contains(fn ($c) => !empty($c['route']) && request()->routeIs($c['route']));
                @endphp
                <div>
                    <button type="button"
                        @click="{{ $groupKey }} = !{{ $groupKey }}"
                        class="w-full flex items-center justify-between px-3 py-2 rounded text-gray-700 hover:bg-white {{ $isActive ? 'bg-white font-medium text-odoo-purple' : '' }}">
                        <span>{{ $item['label'] }}</span>
                        <span class="text-xs" x-text="{{ $groupKey }} ? '▾' : '▸'"></span>
                    </button>
                    <div x-show="{{ $groupKey }}" class="ml-3 border-l border-odoo-border pl-2 mt-0.5 space-y-0.5">
                        @foreach ($item['children'] as $child)
                            @if (!empty($child['disabled']))
                                <span class="block px-3 py-1.5 text-gray-400 cursor-not-allowed">{{ $child['label'] }}</span>
                            @else
                                <a href="{{ route($child['route']) }}"
                                   class="block px-3 py-1.5 rounded hover:bg-white {{ request()->routeIs($child['route']) ? 'bg-white font-medium text-odoo-purple' : 'text-gray-600' }}">
                                    {{ $child['label'] }}
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @else
                <a href="{{ route($item['route']) }}"
                   class="flex items-center gap-2 px-3 py-2 rounded hover:bg-white {{ request()->routeIs($item['route']) ? 'bg-white font-medium text-odoo-purple' : 'text-gray-700' }}">
                    <span class="w-4 text-center text-xs opacity-60">{{ $item['icon'] ?? '•' }}</span>
                    {{ $item['label'] }}
                </a>
            @endif
        @endforeach
    </nav>
</aside>
