<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
          <div class="min-h-screen bg-gray-100 dark:bg-gray-900 flex lg:space-x-4">
        <livewire:layout.navigation />
        {{-- <livewire:components.mobile-nav /> --}}
        
        <div class="w-full">
            <!-- Page Heading -->
            <header>
                <div x-data class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 flex justify-between">
                    <div class="flex lg:hidden">
                        <x-mary-button label="Open Left" @click="$dispatch('showdrawer')" />
                    </div>
                    <div class="lg:flex hidden">
                        <x-mary-theme-toggle/>
                    </div>
                    {{ $header }}
                </div>
            </header>
            {{-- @if (isset($header))
            @endif --}}

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>
    </div>
    </body>
</html>
