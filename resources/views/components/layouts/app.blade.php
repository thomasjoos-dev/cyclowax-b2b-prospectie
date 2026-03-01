<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title . ' — Cyclowax B2B' : 'Cyclowax B2B Prospectie Tool' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased bg-base-200">

    {{-- NAVBAR --}}
    <x-nav sticky class="shadow-sm bg-base-100">
        <x-slot:brand>
            <a href="{{ route('stores.index') }}" class="flex items-center gap-2 font-bold text-lg text-base-content">
                <x-icon name="o-bolt" class="w-6 h-6 text-primary" />
                Cyclowax B2B
            </a>
        </x-slot:brand>
        <x-slot:actions>
            <x-button label="Fietswinkels" icon="o-building-storefront" link="{{ route('stores.index') }}" class="btn-ghost btn-sm" responsive />
            @if(app()->environment('local'))
                <x-button label="Styleguide" icon="o-swatch" link="/styleguide" class="btn-ghost btn-sm" responsive />
            @endif
        </x-slot:actions>
    </x-nav>

    {{-- MAIN CONTENT --}}
    <x-main full-width>
        <x-slot:content>
            {{ $slot }}
        </x-slot:content>
    </x-main>

    {{-- TOAST --}}
    <x-toast />
</body>
</html>
