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
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" />
    <script src="https://code.jquery.com/jquery-3.4.1.slim.min.js"
        integrity="sha384-J6qa4849blE2+poT4WnyKhv5vZF5SrPo0iEjwBvKU7imGFAV0wwj1yYfoRSJoZ+n" crossorigin="anonymous">
    </script>
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/summernote-lite.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/summernote-lite.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased">
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900 flex lg:space-x-4">
        <livewire:layout.navigation />
        <livewire:layout.mobile-navigation />

        <div class="w-full lg:w-[72%] xl:w-full">
            {{-- alert --}}
            <div class="mb-6 rounded-xl border border-amber-300 bg-amber-50 p-5">
                <x-mary-collapse>

                    <div class="flex items-start gap-4">

                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-amber-100">
                            <x-heroicon-o-exclamation-triangle class="w-7 h-7 text-amber-600" />
                        </div>

                        <div class="flex-1">
                            <x-slot:heading >
                                <h3 class="text-lg font-semibold text-amber-900">
                                    Pembaruan Sistem Pencatatan Keuangan
                                </h3>

                                <p class="mt-2 text-sm leading-6 text-amber-800">
                                    Sistem pencatatan keuangan telah diperbarui untuk meningkatkan
                                    akurasi antara pendapatan yang tercatat pada aplikasi dan uang
                                    yang diterima di kasir.
                                </p>
                            </x-slot:heading>
                            <x-slot:content>
                                <div class="mt-4 rounded-lg bg-white/70 p-4">

                                    <ul class="space-y-2 text-sm text-gray-700">

                                        <li class="flex gap-2">
                                            <span>•</span>
                                            <span>
                                                Mulai versi ini, seluruh pendapatan dicatat berdasarkan
                                                transaksi pembayaran yang benar-benar diterima (Cash,
                                                Transfer, maupun QRIS), termasuk pembayaran DP,
                                                pelunasan, penalty, dan extend.
                                            </span>
                                        </li>

                                        <li class="flex gap-2">
                                            <span>•</span>
                                            <span>
                                                Data pendapatan pada periode sebelumnya mungkin belum
                                                sepenuhnya tampil pada laporan baru. Hal ini bukan
                                                karena data hilang, melainkan karena masih menggunakan
                                                sistem pencatatan lama.
                                            </span>
                                        </li>

                                        <li class="flex gap-2">
                                            <span>•</span>
                                            <span>
                                                Proses migrasi data lama ke sistem baru akan dilakukan
                                                setelah sistem dinyatakan stabil agar seluruh riwayat
                                                pendapatan tetap terjaga dengan baik.
                                            </span>
                                        </li>

                                        <li class="flex gap-2">
                                            <span>•</span>
                                            <span>
                                                Selama masa transisi, laporan pendapatan terbaru
                                                merupakan data yang paling akurat dan menjadi acuan
                                                utama untuk pencatatan kas.
                                            </span>
                                        </li>

                                    </ul>

                                </div>

                                <div class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3">

                                    <p class="text-sm text-green-800">
                                        <strong>Tujuan pembaruan:</strong>
                                        memastikan setiap rupiah yang tercatat pada aplikasi sesuai
                                        dengan uang yang benar-benar diterima sehingga laporan
                                        pendapatan dan kas menjadi lebih akurat.
                                    </p>

                                </div>
                            </x-slot:content>

                        </div>

                    </div>

                </x-mary-collapse>
            </div>
            <!-- Page Heading -->
            <header>
                <div x-data class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 flex justify-between">
                    <div class="flex lg:hidden">
                        <x-mary-button label="Menu" @click="$dispatch('showdrawer')" />
                    </div>
                    <div class="lg:flex hidden">
                        <x-mary-theme-toggle />
                        {{-- <livewire:layout.logout /> --}}
                    </div>
                    {{ $header }}
                </div>
            </header>
            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
</body>

</html>
