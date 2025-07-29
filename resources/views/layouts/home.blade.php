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
    <footer class="w-full h-fit mt-40 bg-slate-950">
        <div class="flex items-center justify-between  text-white py-32">
            <div class="w-[60%] ">
                <div class="ml-20 space-y-5">
                    <svg width="79px" height="79px" viewBox="-1.5 0 20 20" version="1.1"
                        xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" fill="#000000">
                        <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                        <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                        <g id="SVGRepo_iconCarrier">
                            <title>apple [#173]</title>
                            <desc>Created with Sketch.</desc>
                            <defs> </defs>
                            <g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                <g id="Dribbble-Light-Preview" transform="translate(-102.000000, -7439.000000)"
                                    fill="#ffffff">
                                    <g id="icons" transform="translate(56.000000, 160.000000)">
                                        <path
                                            d="M57.5708873,7282.19296 C58.2999598,7281.34797 58.7914012,7280.17098 58.6569121,7279 C57.6062792,7279.04 56.3352055,7279.67099 55.5818643,7280.51498 C54.905374,7281.26397 54.3148354,7282.46095 54.4735932,7283.60894 C55.6455696,7283.69593 56.8418148,7283.03894 57.5708873,7282.19296 M60.1989864,7289.62485 C60.2283111,7292.65181 62.9696641,7293.65879 63,7293.67179 C62.9777537,7293.74279 62.562152,7295.10677 61.5560117,7296.51675 C60.6853718,7297.73474 59.7823735,7298.94772 58.3596204,7298.97372 C56.9621472,7298.99872 56.5121648,7298.17973 54.9134635,7298.17973 C53.3157735,7298.17973 52.8162425,7298.94772 51.4935978,7298.99872 C50.1203933,7299.04772 49.0738052,7297.68074 48.197098,7296.46676 C46.4032359,7293.98379 45.0330649,7289.44985 46.8734421,7286.3899 C47.7875635,7284.87092 49.4206455,7283.90793 51.1942837,7283.88393 C52.5422083,7283.85893 53.8153044,7284.75292 54.6394294,7284.75292 C55.4635543,7284.75292 57.0106846,7283.67793 58.6366882,7283.83593 C59.3172232,7283.86293 61.2283842,7284.09893 62.4549652,7285.8199 C62.355868,7285.8789 60.1747177,7287.09489 60.1989864,7289.62485"
                                            id="apple-[#173]"> </path>
                                    </g>
                                </g>
                            </g>
                        </g>
                    </svg>
                    <div>
                        <h1 class="font-medium text-4xl">SkyRental</h1>
                        <h3 class="text-lg font-light max-w-[80%]">Lorem ipsum dolor sit amet, consectetur adipisicing
                            elit. Ex sapiente libero dolore perspiciatis ad hic laudantium illo itaque voluptates harum
                            et nulla aliquid dolorem</h3>
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
                        <li><a href="/kontak" class="hover:underline">Kontak</a></li>
                    </ul>
                </div>

                <!-- Bantuan -->
                <div>
                    <h3 class="text-lg font-semibold mb-2">Bantuan</h3>
                    <ul class="space-y-1 text-sm">
                        <li><a href="/faq" class="hover:underline">FAQ</a></li>
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
