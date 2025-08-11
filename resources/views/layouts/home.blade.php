<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if (!empty($overide))
        {{ $overide }}
    @else
        <title>{{ $seo->title }}</title>
        <meta name="description" content="{{ $seo->description }}">
        <meta name="robots" content="{{ $seo->robots }}">
    @endif



    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />

    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
</head>

<body>
    <div class="antialiased font-sans dark:bg-gray-900" x-data="{ setNav: false, }">
        <div class="">
            <livewire:layout.home-navigation />
            <button type="button" class="p-2 bg-primary fixed bottom-3 right-3 flex lg:hidden z-50"
                @click="setNav = true" :class="setNav ? 'hidden' : ''">
                <x-icons.dotmenu default="25px" />
            </button>
        </div>
        <main>
            {{ $slot }}
        </main>
    </div>
    <livewire:footer />
</body>

</html>
