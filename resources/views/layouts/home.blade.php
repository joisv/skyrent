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
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
</head>

<body>
    <div class="antialiased font-sans max-w-screen-2xl mx-auto dark:bg-gray-900">
        <livewire:layout.home-navigation />
        <main>
            {{ $slot }}
        </main>
    </div>
    <footer class="w-full h-fit mt-40 bg-sky-300">
        <div class="flex items-center justify-between  text-white py-32">
            <div class="w-[60%] ">
                <div class="ml-20 space-y-5">
                   <img src="{{ url('logo.png') }}" alt="" srcset="">
                    <div>
                        {{-- <h1 class="font-medium text-4xl">SkyRental</h1> --}}
                        {{-- <h3 class="text-lg font-light max-w-[80%]">Lorem ipsum dolor sit amet, consectetur adipisicing
                            elit. Ex sapiente libero dolore perspiciatis ad hic laudantium illo itaque voluptates harum
                            et nulla aliquid dolorem</h3> --}}
                    </div>
                </div>
            </div>
            <div class="w-[40%] flex items-start justify-center space-x-3">
                <div>
                    <h3 class="text-lg font-semibold mb-2">Navigasi</h3>
                    <ul class="space-y-1 text-sm">
                        <li><a href="/" class="hover:underline">Beranda</a></li>
                        <li><a href="/produk" class="hover:underline">Daftar iPhone</a></li>
                        <li><a wire:navigate href="{{ route('howtorent') }}" class="hover:underline">Cara Sewa</a></li>
                        <li><a wire:navigate href="{{ route('contacts') }}" class="hover:underline">Kontak</a></li>
                    </ul>
                </div>

                <!-- Bantuan -->
                <div>
                    <h3 class="text-lg font-semibold mb-2">Bantuan</h3>
                    <ul class="space-y-1 text-sm">
                        <li><a wire:navigate href="{{ route('faqs') }}" class="hover:underline">FAQ</a></li>
                        <li><a href="/syarat-ketentuan" class="hover:underline">Syarat & Ketentuan</a></li>
                        <li><a href="/kebijakan-privasi" class="hover:underline">Kebijakan Privasi</a></li>
                    </ul>
                </div>

                <!-- Sosial Media -->
                <div>
                    <h3 class="text-lg font-semibold mb-2">Ikuti Kami</h3>
                    <ul class="space-y-1 text-sm">
                        <li><a href="#" class="hover:underline">Instagram</a></li>
                        <li><a href="#" class="hover:underline">WhatsApp</a></li>
                        <li><a href="#" class="hover:underline">TikTok</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="w-full flex items-center justify-center text-white py-10 border-t-2 border-gray-300 ">
            <div class="w-[95%] flex items-center justify-between px-10 py-5 ">
                <p>&copy; {{ date('Y') }} SkyRental. All rights reserved.</p>
                <div class="flex items-center space-x-2">
                    <a href="/syarat-ketentuan" class="hover:underline">Syarat & Ketentuan</a>
                    <a href="/kebijakan-privasi" class="hover:underline">Kebijakan Privasi</a>
                </div>
            </div>
        </div>
    </footer>
</body>

</html>
