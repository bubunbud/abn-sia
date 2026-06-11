<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Login') — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex items-center justify-center p-6 bg-[#EDEDED] text-gray-800 antialiased">
    <div class="w-full max-w-[300px] flex flex-col items-center">
        @if (session('success'))
            <div class="mb-4 w-full px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-4 w-full px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded text-sm">
                {{ session('error') }}
            </div>
        @endif

        @yield('content')
    </div>
</body>
</html>
