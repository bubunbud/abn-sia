@extends('layouts.guest')

@section('title', 'Login')

@section('content')
    @php
        $appName = strtolower(config('app.name'));
        $nameParts = preg_split('/[\s\-_]+/', $appName, 2);
    @endphp

    <div class="w-full bg-white rounded shadow-[0_1px_4px_rgba(0,0,0,0.12),0_2px_8px_rgba(0,0,0,0.06)] px-10 py-8">
        {{-- Logo --}}
        <div class="text-center mb-6">
            <h1 class="text-[2.5rem] font-light leading-none tracking-tight">
                <span class="text-[#714B67]">{{ $nameParts[0] }}</span>@if (! empty($nameParts[1]))<span class="text-[#8F8F8F]">-{{ $nameParts[1] }}</span>@endif
            </h1>
        </div>

        {{-- Info box --}}
        <div class="text-center text-[13px] leading-snug text-[#016878] bg-[#E8F6F8] border border-[#B8DEE4] rounded px-3 py-2.5 mb-6">
            @if (config('app.company_name'))
                Akses dan kelola data akuntansi {{ config('app.company_name') }}.
            @else
                Akses dan kelola data akuntansi perusahaan Anda.
            @endif
        </div>

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf

            {{-- Email --}}
            <div>
                <label for="email" class="block text-sm font-semibold text-gray-800 mb-1.5">Email</label>
                <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                    autocomplete="username" placeholder="Enter your email"
                    class="block w-full border border-[#C9B8C5] rounded px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 bg-white
                        focus:outline-none focus:border-[#714B67] focus:ring-1 focus:ring-[#714B67]/25
                        @error('email') border-red-400 focus:border-red-400 focus:ring-red-200 @enderror">
                @error('email')
                    <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
                @enderror
            </div>

            {{-- Password --}}
            <div x-data="{ showPassword: false }">
                <div class="flex items-center justify-between mb-1.5">
                    <label for="password" class="text-sm font-semibold text-gray-800">Password</label>
                    <span class="text-xs text-[#875A7B] hover:text-[#714B67] hover:underline cursor-default"
                        title="Hubungi administrator">Reset Password</span>
                </div>
                <div class="relative flex">
                    <input :type="showPassword ? 'text' : 'password'" name="password" id="password" required
                        autocomplete="current-password" placeholder="Enter your password"
                        class="block w-full border border-[#DEE2E6] rounded-l px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 bg-white
                            focus:outline-none focus:border-[#714B67] focus:ring-1 focus:ring-[#714B67]/25 focus:z-10
                            @error('password') border-red-400 focus:border-red-400 focus:ring-red-200 @enderror">
                    <button type="button" @click="showPassword = !showPassword"
                        class="flex items-center justify-center w-10 shrink-0 border border-l-0 border-[#DEE2E6] rounded-r bg-white text-gray-500 hover:text-gray-700 hover:bg-gray-50"
                        tabindex="-1" aria-label="Tampilkan kata sandi">
                        <svg x-show="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <svg x-show="showPassword" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                        </svg>
                    </button>
                </div>
                @error('password')
                    <p class="text-xs text-red-600 mt-1.5">{{ $message }}</p>
                @enderror
            </div>

            {{-- Tombol --}}
            <div class="pt-1">
                <button type="submit"
                    class="w-full py-2.5 bg-[#714B67] hover:bg-[#5B3D54] text-white text-sm font-bold uppercase tracking-wide rounded transition-colors">
                    Sign In
                </button>
            </div>
        </form>

        <p class="text-center text-sm text-gray-500 mt-6">
            Don't have an account?
        </p>
    </div>
@endsection
