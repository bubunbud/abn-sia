@extends('layouts.odoo')

@section('title', 'Pengguna')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-xl font-semibold">Pengguna</h1>
            <p class="text-sm text-gray-500">Kelola akun pengguna sistem · {{ $users->total() }} data</p>
        </div>
        <a href="{{ route('accounting.users.create') }}" class="odoo-btn-primary">+ Tambah Pengguna</a>
    </div>

    <div class="bg-white rounded border border-odoo-border shadow-sm mb-4 p-3">
        <form method="GET" class="flex flex-wrap gap-2 items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="text-xs text-gray-500">Cari</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Nama atau email..."
                    class="w-full border border-odoo-border rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs text-gray-500">Peran</label>
                <select name="role" class="border border-odoo-border rounded px-3 py-2 text-sm">
                    <option value="">Semua</option>
                    @foreach ($roleOptions as $value => $label)
                        <option value="{{ $value }}" @selected(request('role') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="odoo-btn-secondary">Filter</button>
        </form>
    </div>

    <div class="bg-white rounded border border-odoo-border shadow-sm overflow-x-auto">
        <table class="odoo-table w-full">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Email</th>
                    <th>Peran</th>
                    <th>Status</th>
                    <th>Terakhir Diubah</th>
                    <th class="w-20">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td class="font-medium">
                            {{ $user->name }}
                            @if ($user->id === auth()->id())
                                <span class="text-xs text-gray-400">(Anda)</span>
                            @endif
                        </td>
                        <td>{{ $user->email }}</td>
                        <td>
                            <span class="{{ $user->isAdmin() ? 'odoo-badge-posted' : 'odoo-badge-draft' }}">
                                {{ $user->role->label() }}
                            </span>
                        </td>
                        <td>
                            @if ($user->is_active)
                                <span class="text-green-700 text-sm">Aktif</span>
                            @else
                                <span class="text-red-600 text-sm">Nonaktif</span>
                            @endif
                        </td>
                        <td class="text-gray-500 text-sm">{{ $user->updated_at->format('d M Y H:i') }}</td>
                        <td>
                            <a href="{{ route('accounting.users.edit', $user) }}" class="odoo-link text-sm">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-gray-500 py-8">Belum ada pengguna.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $users->links() }}
    </div>
@endsection
