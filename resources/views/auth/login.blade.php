@extends('layouts.guest')

@section('title', 'Login')

@section('content')
    <div class="bg-white rounded border border-odoo-border shadow-sm p-5 w-full">
        <div class="px-4">
            <h2 class="text-lg font-semibold mb-1">Masuk ke Sistem</h2>
            <p class="text-sm text-gray-500 mb-5">Gunakan akun yang diberikan administrator.</p>
        </div>

        <form method="POST" action="{{ route('login') }}" class="space-y-4 px-4">
            @csrf

            <div>
                <label for="email" class="block text-xs text-gray-500 mb-1">Email</label>
                <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                    autocomplete="username"
                    class="w-full border border-odoo-border rounded px-3 py-2 text-sm @error('email') border-red-400 @enderror">
                @error('email')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-xs text-gray-500 mb-1">Kata Sandi</label>
                <input type="password" name="password" id="password" required
                    autocomplete="current-password"
                    class="w-full border border-odoo-border rounded px-3 py-2 text-sm @error('password') border-red-400 @enderror">
                @error('password')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center">
                <input type="checkbox" name="remember" id="remember" value="1"
                    class="rounded border-odoo-border text-odoo-purple focus:ring-odoo-purple"
                    @checked(old('remember'))>
                <label for="remember" class="ml-2 text-sm text-gray-600">Ingat saya</label>
            </div>

            <button type="submit" class="odoo-btn-primary w-full justify-center">Masuk</button>
        </form>
    </div>
@endsection
