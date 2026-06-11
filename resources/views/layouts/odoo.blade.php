<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Accounting') — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-800 min-h-screen">
    <header class="bg-odoo-nav text-white shadow">
        <div class="flex items-center justify-between px-4 h-12">
            <div class="flex items-center gap-4">
                <a href="{{ route('accounting.dashboard') }}" class="font-semibold text-lg tracking-wide">
                    {{ config('app.name') }}
                </a>
                @if (config('app.company_name'))
                    <span class="text-white/70 text-sm hidden sm:inline">{{ config('app.company_name') }}</span>
                @endif
            </div>
            <div class="flex items-center gap-4 text-sm">
                @auth
                    <span class="text-white/80 hidden sm:inline">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-white/80 hover:text-white underline-offset-2 hover:underline">
                            Keluar
                        </button>
                    </form>
                @endauth
            </div>
        </div>
    </header>

    <div class="flex min-h-[calc(100vh-3rem)]">
        @include('layouts.partials.sidebar')

        <main class="flex-1 flex flex-col min-w-0">
            @if (!empty($breadcrumbs))
                <nav class="bg-white border-b border-odoo-border px-4 py-2 text-sm text-gray-600">
                    @foreach ($breadcrumbs as $index => $crumb)
                        @if ($index > 0)
                            <span class="mx-1 text-gray-400">/</span>
                        @endif
                        @if (!empty($crumb['url']) && $index < count($breadcrumbs) - 1)
                            <a href="{{ $crumb['url'] }}" class="odoo-link">{{ $crumb['label'] }}</a>
                        @else
                            <span class="text-gray-800 font-medium">{{ $crumb['label'] }}</span>
                        @endif
                    @endforeach
                </nav>
            @endif

            @if (session('success'))
                <div class="mx-4 mt-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mx-4 mt-4 px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded text-sm">
                    {{ session('error') }}
                </div>
            @endif

            <div class="flex-1 p-4">
                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
