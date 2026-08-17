<div class="space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

        {{-- Pendapatan --}}
        <div class="bg-white rounded-2xl border p-5">

            <p class="text-sm text-gray-500">
                Pendapatan
            </p>

            <p class="text-2xl font-bold mt-2">
                Rp {{ number_format($affiliateRevenue, 0, ',', '.') }}
            </p>

        </div>


        {{-- Booking --}}
        <div class="bg-white rounded-2xl border p-5">

            <p class="text-sm text-gray-500">
                Total Booking
            </p>

            <p class="text-2xl font-bold mt-2">
                {{ $affiliateBookingCount }}
            </p>

        </div>


        {{-- Transaksi --}}
        <div class="bg-white rounded-2xl border p-5">

            <p class="text-sm text-gray-500">
                Transaksi Pembayaran
            </p>

            <p class="text-2xl font-bold mt-2">
                {{ $affiliatePayments->count() }}
            </p>

        </div>

    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        {{-- Pendapatan Hari Ini --}}
        <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">

            <div class="flex items-center justify-between">

                <div>
                    <p class="text-sm text-gray-500">
                        Pendapatan Hari Ini
                    </p>

                    <p class="text-2xl font-bold mt-1">
                        Rp {{ number_format($revenueToday, 0, ',', '.') }}
                    </p>
                </div>

                <div class="w-11 h-11 rounded-xl bg-green-100
                        flex items-center justify-center">

                    <x-heroicon-o-banknotes class="w-6 h-6 text-green-600" />

                </div>

            </div>

        </div>


        {{-- Booking Hari Ini --}}
        <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">

            <div class="flex items-center justify-between">

                <div>
                    <p class="text-sm text-gray-500">
                        Booking Hari Ini
                    </p>

                    <p class="text-2xl font-bold mt-1">
                        {{ $bookingToday }}
                    </p>
                </div>

                <div
                    class="w-11 h-11 rounded-xl bg-orange-100
                        flex items-center justify-center">

                    <x-heroicon-o-calendar-days class="w-6 h-6 text-orange-600" />

                </div>

            </div>

        </div>

    </div>
    <div class="w-full ">
        <div class="mt-10">
            <x-tables.table name="Pendapatan" count="">
                <x-slot name="secondBtn">
                    <button
                        class="flex items-center justify-center w-1/2 px-5 py-2 text-sm disabled:text-gray-700 transition-colors duration-200 disabled:bg-white border rounded-lg gap-x-2 sm:w-auto dark:hover:bg-gray-800 dark:bg-gray-900 hover:bg-gray-100 dark:text-gray-200 dark:border-gray-700 bg-red-500 text-white"
                        wire:click="destroyAlert" @if (!$mySelected) disabled @endif>
                        <span>Bulk delete</span>
                    </button>
                </x-slot>
                <x-slot name="addBtn">
                    {{-- <x-tables.addbtn type="button" x-data="" @click="window.location.href = ''">
            Add iPhone
        </x-tables.addbtn> --}}
                </x-slot>
                <x-slot name="sort">
                    <div class="flex items-center space-x-2 w-1/2 ">
                        <div class="w-fit">
                            <select id="sort_series"
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 px-5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                                wire:model.live="paginate">
                                <option value="10">10</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                                <option value="150">150</option>
                            </select>
                        </div>
                        <div class="w-fit">
                            <select id="sort"
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 px-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                                wire:model.live="sortField">
                                <option value="">Urutkan berdasarkan</option>
                                <option value="amount">Jumlah Pendapatan</option>
                                <option value="created">Tanggal Dibuat</option>
                                <option value="updated_at">Terakhir Diperbarui</option>
                            </select>

                        </div>
                    </div>
                </x-slot>
                <x-slot name="search">
                    <x-search wire:model.live.debounce.500ms="search" />
                </x-slot>
                <x-slot name="thead">
                    <x-tables.th>
                        <input id="selectedAll"
                            type="checkbox"class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                            wire:model.live="selectedAll">
                        {{-- <input type="hidden" wire:model.live="firstId" value="{{ $serieses[0]->id }}"> --}}
                    </x-tables.th>
                    <x-tables.th>Tanggal dibuat</x-tables.th>
                    <x-tables.th>Kode Booking</x-tables.th>
                    <x-tables.th>Customer</x-tables.th>
                    <x-tables.th>Tipe iPhone</x-tables.th>
                    <x-tables.th>Pembayaran</x-tables.th>
                    <x-tables.th>Tipe Pembayaran</x-tables.th>
                    <x-tables.th>Nominal</x-tables.th>
                    <x-tables.th>Dibayar</x-tables.th>
                    <x-tables.th>Kembalian</x-tables.th>
                    <x-tables.th>Dibuat</x-tables.th>
                    {{-- <x-tables.th>Aksi</x-tables.th> --}}

                </x-slot>
                <x-slot name="tbody">
                    @foreach ($paymentsList as $index => $payment)
                        <tr>
                            <x-tables.td>
                                <input id="default-{{ $index }}" type="checkbox"
                                    class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                                    wire:model.live="mySelected" value="{{ $payment->id }}">
                            </x-tables.td>

                            <x-tables.td>
                                {{ $payment->paid_at->format('d/m/Y H:i') }}
                            </x-tables.td>

                            <x-tables.td>{{ $payment->booking->booking_code }}</x-tables.td>

                            <x-tables.td>{{ $payment->booking->customer_name }}</x-tables.td>

                            <x-tables.td>{{ $payment->booking->iphone->name }}</x-tables.td>

                            <x-tables.td>{{ $payment->payment->name }}</x-tables.td>

                            <x-tables.td>
                                <x-mary-badge :value="ucfirst($payment->type)" />
                            </x-tables.td>

                            <x-tables.td>
                                Rp {{ number_format($payment->amount, 0, ',', '.') }}
                            </x-tables.td>

                            <x-tables.td>
                                Rp {{ number_format($payment->pay, 0, ',', '.') }}
                            </x-tables.td>

                            <x-tables.td>
                                Rp {{ number_format($payment->change, 0, ',', '.') }}
                            </x-tables.td>

                            <x-tables.td>{{ $payment->user->name }}</x-tables.td>

                        </tr>
                    @endforeach

                </x-slot>
            </x-tables.table>
        </div>
    </div>
</div>
