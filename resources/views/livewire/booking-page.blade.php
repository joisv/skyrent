<div x-data="{
    createBooking() {
        {{-- $dispatch('booking-create') --}}
        $dispatch('open-modal', 'booking-create')
    },
}">
    {{-- @dump($returnToday) --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <button @click="() => {
            $dispatch('open-modal', 'list')    
        }" class="shadow-2xl">
            <x-mary-stat title="iPhone Tersedia" :value="$iphonesAvailable->count() . ' Unit'" icon="o-device-phone-mobile" color="text-green-600" />
        </button>
        <button @click="() => {
            $dispatch('open-modal', 'return')    
        }" class="shadow-2xl">
            <x-mary-stat title="Dikembalikan Hari Ini" :value="$returnToday->count() . ' Unit'" icon="o-arrow-uturn-left"
                color="text-purple-600" />
        </button>
        <button @click="() => { $dispatch('open-modal', 'booking-today') }" class="shadow-2xl">
            <x-mary-stat title="Booking Hari Ini" :value="$bookingToday->count() . ' Booking'" icon="o-clipboard-document-list"
                color="text-blue-600" />
        </button>

        <x-mary-stat title="Pendapatan Hari ini" :value="'Rp ' . number_format($revenueToday, 0, ',', '.')" icon="o-banknotes" color="text-success" />
    </div>
    <x-tables.table name="Booking">
        <x-slot name="secondBtn">
            <button
                class="flex items-center justify-center w-1/2 px-5 py-2 text-sm disabled:text-gray-700 transition-colors duration-200 disabled:bg-white border rounded-lg gap-x-2 sm:w-auto dark:hover:bg-gray-800 dark:bg-gray-900 hover:bg-gray-100 dark:text-gray-200 dark:border-gray-700 bg-red-500 text-white"
                wire:click="destroyAlert" @if (!$mySelected) disabled @endif>
                <span>Bulk delete</span>
            </button>
        </x-slot>
        <x-slot name="addBtn">
            <x-tables.addbtn type="button" x-data="" @click="createBooking()">
                Add Booking
            </x-tables.addbtn>
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

                        <option disabled selected>Sort by</option>
                        <option value="created_at">Terbaru</option>
                        <option value="updated_at">Updated</option>
                        <option value="start_booking_date">Tanggal Mulai</option>
                        <option value="status">Status</option>
                        <option value="price">Harga</option>
                    </select>
                </div>

            </div>
        </x-slot>
        <x-slot name="search">
            <x-search wire:model.live.debounce.500ms="search" />
        </x-slot>
        <x-slot name="thead">
            <x-tables.th>
                <input id="selectedAll" type="checkbox"
                    class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                    wire:model.live="selectedAll">
            </x-tables.th>
            <x-tables.th>Kode Booking</x-tables.th>
            <x-tables.th>Status</x-tables.th>
            <x-tables.th>Nama</x-tables.th>
            <x-tables.th>iPhone</x-tables.th>
            <x-tables.th>Durasi Sewa</x-tables.th>
            <x-tables.th>Tanggal & Waktu Mulai</x-tables.th>
            <x-tables.th>Tanggal & Waktu Selesai</x-tables.th>
            <x-tables.th>Aksi</x-tables.th>
        </x-slot>

        <x-slot name="tbody">
            @foreach ($bookings as $index => $booking)
                <tr>

                    <x-tables.td>
                        <input id="default-{{ $index }}" type="checkbox"
                            class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                            wire:model.live="mySelected" value="{{ $booking->id }}">
                    </x-tables.td>
                    <x-tables.td>{{ $booking->booking_code }}</x-tables.td>
                    <x-tables.td>
                        @switch($booking->status)
                            @case('pending')
                                <span class="px-2 py-1 rounded bg-yellow-500 text-white font-medium text-sm">
                                    Menunggu Pembayaran
                                </span>
                            @break

                            @case('confirmed')
                                <span class="px-2 py-1 rounded bg-green-500 text-white font-medium text-sm">
                                    Dikonfirmasi
                                </span>
                            @break

                            @case('returned')
                                <span class="px-2 py-1 rounded bg-blue-500 text-white font-medium text-sm">
                                    Dikembalikan
                                </span>
                            @break

                            @case('cancelled')
                                <span class="px-2 py-1 rounded bg-red-500 text-white font-medium text-sm">
                                    Dibatalkan
                                </span>
                            @break

                            @default
                                <span class="px-2 py-1 rounded bg-gray-400 text-white font-medium text-sm">
                                    {{ ucfirst($booking->status) }}
                                </span>
                        @endswitch
                    </x-tables.td>

                    <x-tables.td>{{ $booking->customer_name }}</x-tables.td>
                    <x-tables.td>{{ $booking->iphone->name ?? '-' }}</x-tables.td>
                    <x-tables.td>{{ $booking->duration }} jam</x-tables.td>
                    {{-- <x-tables.td>
                        {{ $booking->requested_booking_date ? \Carbon\Carbon::parse($booking->requested_booking_date)->format('d M Y') . ' ' . $booking->requested_time : '-' }}
                    </x-tables.td> --}}

                    <x-tables.td>
                        {{ $booking->start_booking_date ? \Carbon\Carbon::parse($booking->start_booking_date)->format('d M Y') . ' ' . $booking->start_time : '-' }}
                    </x-tables.td>

                    <x-tables.td>
                        {{ $booking->end_booking_date ? \Carbon\Carbon::parse($booking->end_booking_date)->format('d M Y') . ' ' . $booking->end_time : '-' }}
                    </x-tables.td>
                    <x-tables.td>
                        <x-primary-button type="button"
                            @click="() => {
                                $dispatch('get-detail', { id : {{ $booking->id }} })
                                $dispatch('open-modal', 'detail')
                            }">detail</x-primary-button>
                        <x-danger-button type="button"
                            wire:click="destroyAlert({{ $booking->id }}, 'delete')">delete</x-danger-button>
                    </x-tables.td>
                </tr>
            @endforeach
        </x-slot>

    </x-tables.table>
    <div class="w-full mt-5">
        {{-- {{ $iphones->links() }} --}}
    </div>
    <x-modal name="booking-create" :show="$errors->isNotEmpty()">
        <livewire:booking.create />
    </x-modal>
    <x-modal name="detail" :show="$errors->isNotEmpty()">
        <livewire:detail-booking />
    </x-modal>
    <x-modal name="list" :show="$errors->isNotEmpty()">
        @if (!empty($iphonesAvailable))
            <div class="p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Daftar iPhone Tersedia</h2>

                @if ($iphonesAvailable->count() > 0)
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach ($iphonesAvailable as $iphone)
                            <div
                                class="bg-white border rounded-2xl shadow-sm p-4 hover:shadow-md transition duration-200">
                                <div class="flex items-center space-x-3">
                                    <div
                                        class="flex items-center justify-center w-12 h-12 bg-green-100 text-green-600 rounded-xl">
                                        <x-mary-icon name="o-device-phone-mobile" class="w-6 h-6" />
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900">iPhone {{ $iphone->name }}</h3>
                                        {{-- <p class="text-sm text-gray-500">Kode: {{ $iphone->kode ?? '-' }}</p> --}}
                                    </div>
                                </div>
                                <div class="mt-3 flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Status: <span
                                            class="font-medium text-green-600">Tersedia</span></span>
                                    {{-- <button wire:click="bookingNow({{ $iphone->id }})"
                                        class="px-3 py-1.5 bg-green-600 text-white text-sm rounded-xl hover:bg-green-700 transition">
                                        Booking
                                    </button> --}}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-500 text-center py-6">Tidak ada iPhone tersedia saat ini.</p>
                @endif
            </div>
        @endif
    </x-modal>
    <x-modal name="return">
        <div class="p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">iPhone Dikembalikan Hari Ini</h2>

            @if ($returnToday->count() > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach ($returnToday as $booking)
                        <div class="bg-white border rounded-2xl shadow-sm p-4 hover:shadow-md transition duration-200">
                            <div class="flex items-center space-x-3">
                                <div
                                    class="flex items-center justify-center w-12 h-12 bg-yellow-100 text-yellow-600 rounded-xl">
                                    <x-mary-icon name="o-clock" class="w-6 h-6" />
                                </div>
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900">
                                        iPhone {{ $booking->iphone->name }}
                                    </h3>
                                    <p class="text-sm text-gray-500">
                                        Pengembalian:
                                        <span class="font-semibold text-gray-700">
                                            {{ \Carbon\Carbon::parse($booking->end_time)->format('H:i') }}
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <div class="mt-3 flex justify-between items-center">
                                <span class="text-sm text-gray-600">
                                    Status:
                                    <span class="font-medium text-yellow-600">Harus dikembalikan</span>
                                </span>
                                {{-- Jika mau tombol aksi bisa ditaruh di sini --}}
                                {{-- <button wire:click="reminder({{ $booking->id }})"
                            class="px-3 py-1.5 bg-yellow-600 text-white text-sm rounded-xl hover:bg-yellow-700 transition">
                            Ingatkan
                        </button> --}}
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-gray-500 text-center py-6">Tidak ada pengembalian hari ini.</p>
            @endif
        </div>
    </x-modal>
    <x-modal name="booking-today" maxWidth="2xl">
        <div class="p-4">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Booking yang dibuat hari ini</h2>
            @if ($bookingToday->count() > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach ($bookingToday as $booking)
                        <div class="bg-white border rounded-2xl shadow-sm p-4 hover:shadow-md transition duration-200">

                            <div class="flex items-center space-x-3">
                                <div
                                    class="flex items-center justify-center w-12 h-12 bg-blue-100 text-blue-600 rounded-xl">
                                    <x-mary-icon name="o-device-phone-mobile" class="w-6 h-6" />
                                </div>
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900">
                                        iPhone {{ $booking->iphone->name }}
                                    </h3>
                                    <p class="text-sm text-gray-500">
                                        Booking Code:
                                        <span class="font-semibold text-gray-700">
                                            {{ $booking->booking_code }}
                                        </span>
                                    </p>
                                </div>
                            </div>

                            <div class="mt-3 flex justify-between items-center">
                                @php
                                    $statusColors = [
                                        'confirmed' => 'text-green-600',
                                        'pending' => 'text-yellow-600',
                                        'cancelled' => 'text-red-600',
                                    ];
                                    $color = $statusColors[$booking->status] ?? 'text-gray-600';
                                @endphp

                                <span class="text-sm text-gray-600">
                                    Status:
                                    <span class="font-medium {{ $color }}">
                                        {{ ucfirst($booking->status) }}
                                    </span>
                                </span>
                                <span class="text-xs text-gray-500">
                                    {{ \Carbon\Carbon::parse($booking->created_at)->format('H:i') }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-gray-500 text-center py-6">Belum ada booking yang dibuat hari ini.</p>
            @endif
        </div>
    </x-modal>
</div>
