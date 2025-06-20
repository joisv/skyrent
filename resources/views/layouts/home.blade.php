<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Laravel</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />

    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="antialiased font-sans max-w-screen-2xl mx-auto dark:bg-gray-900">
    <livewire:layout.home-navigation/>
    <main>
        {{ $slot }}
    </main>
    <footer>
        <div class="bg-gray-800 text-white text-center py-4 mt-20">
            <p>&copy; {{ date('Y') }} SkyRental. All rights reserved.</p>
        </div>
    </footer>
</body>

</html>
