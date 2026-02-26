<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Cyclowax B2B Prospectie Tool' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-gray-100 text-gray-900">

    <nav class="bg-white border-b border-gray-200 shadow-sm">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center justify-between">
                <a href="{{ route('stores.index') }}" class="flex items-center gap-2 font-semibold text-lg text-indigo-700">
                    Cyclowax B2B
                </a>
                <div class="flex items-center gap-4">
                    <a href="{{ route('stores.index') }}" class="text-sm font-medium text-gray-600 hover:text-indigo-700">
                        Stores
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
