<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Login') — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-800 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-xs mx-auto">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-semibold text-odoo-purple">{{ config('app.name') }}</h1>
            @if (config('app.company_name'))
                <p class="text-sm text-gray-500 mt-1">{{ config('app.company_name') }}</p>
            @endif
        </div>

        @if (session('success'))
            <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded text-sm">
                {{ session('error') }}
            </div>
        @endif

        @yield('content')
    </div>
</body>
</html>
