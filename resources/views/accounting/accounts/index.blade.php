@extends('layouts.odoo')

@section('title', 'Chart of Accounts')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-xl font-semibold">Chart of Accounts</h1>
            <p class="text-sm text-gray-500">Daftar akun hierarkis — H = Header, D = Detail (posting) · {{ $accounts->count() }} akun</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('accounting.accounts.create', ['type' => 'header']) }}" class="odoo-btn-secondary">
                + Header
            </a>
            <a href="{{ route('accounting.accounts.create', ['type' => 'detail']) }}" class="odoo-btn-primary">
                + Detail
            </a>
        </div>
    </div>

    <div class="bg-white rounded border border-odoo-border shadow-sm mb-4 p-3">
        <form method="GET" class="flex gap-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari kode atau nama akun..."
                class="flex-1 border border-odoo-border rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-odoo-purple">
            <button type="submit" class="odoo-btn-secondary">Cari</button>
        </form>
    </div>

    <div class="bg-white rounded border border-odoo-border shadow-sm overflow-x-auto">
        <table class="odoo-table w-full">
            <thead>
                <tr>
                    <th>Kode Akun</th>
                    <th>Nama Akun</th>
                    <th>Kelompok</th>
                    <th>Tipe</th>
                    <th>Saldo Normal</th>
                    <th>Kategori</th>
                    <th class="w-32">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($accounts as $account)
                    <tr class="{{ $account->is_header ? 'bg-gray-50 font-semibold' : '' }}">
                        <td>
                            <span style="padding-left: {{ ($account->level - 1) * 16 }}px" class="inline-block">
                                @if (! $account->is_header)
                                    <a href="{{ route('accounting.general-ledger.index', ['account_id' => $account->id]) }}" class="odoo-link">
                                        {{ $account->code }}
                                    </a>
                                @else
                                    {{ $account->code }}
                                @endif
                            </span>
                        </td>
                        <td>{{ $account->name }}</td>
                        <td>{{ $account->group_name }}</td>
                        <td>
                            <span class="text-xs px-1.5 py-0.5 rounded {{ $account->is_header ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' }}">
                                {{ $account->is_header ? 'H' : 'D' }}
                            </span>
                        </td>
                        <td>{{ strtoupper(substr($account->normal_balance, 0, 1)) }}</td>
                        <td>{{ $account->account_category->label() }}</td>
                        <td>
                            <div class="flex items-center gap-2">
                                <a href="{{ route('accounting.accounts.edit', $account) }}" class="text-xs odoo-link">Edit</a>
                                @if ($account->is_header)
                                    <a href="{{ route('accounting.accounts.create', ['type' => 'detail', 'parent_id' => $account->id]) }}"
                                       class="text-xs text-green-700 hover:underline">+ Detail</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
