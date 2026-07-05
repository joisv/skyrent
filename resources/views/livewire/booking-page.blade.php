<div x-data="{
    createBooking() {
            {{-- $dispatch('booking-create') --}}
            $dispatch('open-modal', 'booking-create')
        },
        iPhoneWizardtest() {
            $dispatch('open-modal', 'iphone-wizard')
            $dispatch('reload-iphone')
        }
}">
    {{-- @dump($returnToday) --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <button @click="() => {
            $dispatch('open-modal', 'list')    
        }" class="shadow-2xl">
            <x-mary-stat title="iPhone Tersedia" :value="$iphonesAvailable->count() . ' Unit'" icon="o-device-phone-mobile" color="text-green-600" />
        </button>
        <button
            @click="() => {
            $dispatch('open-modal', 'return') 
            $wire.getReturnToday()   
        }"
            class="shadow-2xl">
            <x-mary-stat title="iPhone Belum Kembali" :value="$returnToday->count() . ' Unit'" icon="o-arrow-uturn-left" color="text-purple-600" />
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
            <x-tables.addbtn class="p-2 bg-orange-500 text-white" @click="iPhoneWizardtest()">Booking
                baru</x-tables.addbtn>
            {{-- <x-tables.addbtn type="button" x-data="" @click="createBooking()">
                Add Booking
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
                    <select id="status"
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 px-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                        wire:model.live="filterStatus">

                        <option value="">Semua Booking</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="pending">Pending</option>
                        <option value="returned">Returned</option>
                        <option value="cancelled">Cancelled</option>
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
            <x-tables.th>Serial Number</x-tables.th>
            <x-tables.th>Nama</x-tables.th>
            <x-tables.th>Tanggal & Waktu Mulai</x-tables.th>
            <x-tables.th>Dibuat Oleh</x-tables.th>
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
                    <x-tables.td>
                        <p>{{ $booking->iphone->name ?? '-' }}</p>
                        <p>
                            {{ $booking->iphone->serial_number ?? '-' }}

                        </p>
                    </x-tables.td>
                    <x-tables.td>
                        <p class="pb-2">{{ $booking->customer_name }}</p>
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

                    {{-- <x-tables.td>
                        {{ $booking->requested_booking_date ? \Carbon\Carbon::parse($booking->requested_booking_date)->format('d M Y') . ' ' . $booking->requested_time : '-' }}
                    </x-tables.td> --}}

                    <x-tables.td>
                        <p>{{ $booking->duration }} jam</p>
                       <p> {{ $booking->start_booking_date ? \Carbon\Carbon::parse($booking->start_booking_date)->format('d M Y') . ' ' . $booking->start_time : '-' }}</p>
                    </x-tables.td>
                    <x-tables.td>
                        {{ $booking->user?->name ?? '-' }}
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
    <x-modal name="iphone-wizard" :show="$errors->isNotEmpty()" maxWidth="4xl" minh="min-h-screen overflow-scroll">
        <livewire:rent-iphone-wizard />
    </x-modal>
    {{-- <x-modal name="booking-create" :show="$errors->isNotEmpty()">
        <livewire:booking.create />
    </x-modal> --}}
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
                    <p class="mt-2 text-gray-600">Silakan pilih tanggal lain atau hubungi kami untuk informasi lebih
                        lanjut.</p>
                @endif
            </div>
        @endif
    </x-modal>
    <x-modal name="return" maxWidth="4xl">
        @if (isset($returnToday) && $returnToday->isNotEmpty())
            <div class="p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">iPhone Dikembalikan Hari Ini</h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    @foreach ($returnToday as $booking)
                        @php
                            $isLate = \Carbon\Carbon::parse($booking->end_booking_date)->lt(\Carbon\Carbon::today());
                        @endphp
                        <div
                            class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm hover:shadow-md transition duration-200">

                            {{-- Header --}}
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h3 class="text-base font-semibold text-gray-900">
                                        {{ $booking->customer_name }}
                                    </h3>
                                    <p class="text-xs text-gray-500">
                                        Penyewa
                                    </p>
                                </div>

                                {{-- Badge --}}
                                <span
                                    class="px-2.5 py-1 text-xs font-medium rounded-full
                    {{ $isLate ? 'bg-red-100 text-red-600' : 'bg-yellow-100 text-yellow-700' }}">
                                    {{ $isLate ? 'Terlambat' : 'Hari Ini' }}
                                </span>
                            </div>

                            {{-- Device Info --}}
                            <div class="mb-4">
                                <p class="text-sm text-gray-600">Device</p>
                                <h4 class="text-sm font-medium text-gray-900">
                                    iPhone {{ $booking->iphone->name }}
                                </h4>
                                <p class="text-xs text-gray-500">
                                    SN: {{ $booking->iphone->serial_number }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    Kode Sewa: {{ $booking->booking_code }}
                                </p>
                            </div>

                            {{-- Pengembalian --}}
                            <div class="flex items-center justify-between pt-3 border-t border-gray-100">
                                <div>
                                    <p class="text-xs text-gray-500">Tanggal Kembali</p>
                                    <p class="text-sm font-medium text-gray-800">
                                        {{ \Carbon\Carbon::parse($booking->end_booking_date)->format('d M Y') }}
                                    </p>
                                </div>

                                <div class="text-right">
                                    <p class="text-xs text-gray-500">Jam</p>
                                    <p class="text-sm font-semibold text-yellow-600">
                                        {{ \Carbon\Carbon::parse($booking->end_time)->format('H:i') }}
                                    </p>
                                </div>
                            </div>
                            <div class="mt-4 flex">
                                {{-- Tombol WhatsApp --}}
                                @php
                                    $reminderMessage = $isLate
                                        ? "*Status: TERLAMBAT*\n" .
                                            "Pengembalian iPhone Anda telah melewati batas waktu.\n" .
                                            "Mohon segera melakukan pengembalian untuk menghindari penambahan biaya.\n\n"
                                        : "*Status: PENGEMBALIAN HARI INI*\n" .
                                            "Mohon untuk melakukan pengembalian iPhone sesuai jadwal.\n\n";
                                @endphp

                                <button
                                    @click="() => {
        $wire.set('isLate', {{ $isLate ? 'true' : 'false' }});
        $wire.set('reminderId', {{ $booking->id }});
        $wire.set('message', @js($reminderMessage));
        $dispatch('open-modal', 'reminder-message');
                                    }"
                                    class="flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-md transition w-full justify-center
    {{ $isLate ? 'bg-red-500 text-white hover:bg-red-600' : 'bg-green-500 text-white hover:bg-green-600' }}"
                                    wire:loading.attr="disabled">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="currentColor"
                                        viewBox="0 0 24 24">
                                        <path
                                            d="M20.52 3.48A11.94 11.94 0 0012.06 0C5.48 0 .12 5.36.12 11.94c0 2.1.55 4.16 1.6 5.98L0 24l6.26-1.63a11.9 11.9 0 005.8 1.48h.01c6.58 0 11.94-5.36 11.94-11.94 0-3.19-1.24-6.19-3.49-8.43zM12.07 21.5c-1.8 0-3.57-.48-5.13-1.38l-.37-.22-3.72.97.99-3.63-.24-.37a9.45 9.45 0 01-1.45-5.03c0-5.23 4.25-9.48 9.48-9.48 2.53 0 4.9.99 6.69 2.78a9.42 9.42 0 012.79 6.7c0 5.23-4.25 9.48-9.48 9.48zm5.2-7.1c-.28-.14-1.67-.82-1.93-.92-.26-.1-.45-.14-.64.14-.19.28-.74.92-.91 1.11-.17.19-.33.21-.61.07-.28-.14-1.18-.43-2.24-1.37-.83-.74-1.39-1.66-1.55-1.94-.16-.28-.02-.43.12-.57.13-.13.28-.33.42-.5.14-.17.19-.28.28-.47.09-.19.05-.36-.02-.5-.07-.14-.64-1.54-.88-2.11-.23-.56-.47-.48-.64-.49h-.55c-.19 0-.5.07-.76.36-.26.28-1 1-1 2.44 0 1.44 1.03 2.83 1.18 3.03.14.19 2.03 3.1 4.92 4.35.69.3 1.22.48 1.64.61.69.22 1.32.19 1.82.12.56-.08 1.67-.68 1.9-1.33.23-.65.23-1.2.16-1.33-.07-.12-.26-.19-.54-.33z" />
                                    </svg>
                                    {{ $isLate ? 'Tagih' : 'Ingatkan' }}
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="w-full h-32 flex justify-center items-center col-span-4" wire:loading.flex>
                <svg width="64px" height="64px" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg"
                    fill="none" class="animate-spin">
                    <g fill="#f43f5e" fill-rule="evenodd" clip-rule="evenodd">
                        <path d="M8 1.5a6.5 6.5 0 100 13 6.5 6.5 0 000-13zM0 8a8 8 0 1116 0A8 8 0 010 8z"
                            opacity=".2"></path>
                        <path
                            d="M7.25.75A.75.75 0 018 0a8 8 0 018 8 .75.75 0 01-1.5 0A6.5 6.5 0 008 1.5a.75.75 0 01-.75-.75z">
                        </path>
                    </g>
                </svg>
                <span class="sr-only">Loading...</span>
            </div>
            <div class="p-5 text-center" wire:loading.remove>
                <h1 class="text-lg font-semibold">Tidak ada iPhone yang dikembalikan hari ini.</h1>
                <p class="mt-2 text-gray-600">Tidak ada iPhone yang perlu ditindaklanjuti untuk proses pengembalian
                    hari ini.</p>
            </div>
        @endif
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
    <x-modal name="reminder-message">
        <div class="p-5 space-y-4">

            {{-- Header --}}
            <div>
                <h2 class="text-lg font-semibold text-gray-900">
                    Kirim Pesan WhatsApp
                </h2>
                <p class="text-sm text-gray-500">
                    Edit pesan sebelum dikirim ke penyewa
                </p>
            </div>

            {{-- Divider --}}
            <div class="border-t border-gray-100"></div>

            {{-- Textarea --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Pesan
                </label>

                <textarea wire:model="message" rows="8"
                    class="w-full rounded-xl border border-gray-200 focus:border-green-500 focus:ring focus:ring-green-100 text-sm p-3 resize-none"
                    placeholder="Tulis atau edit pesan di sini...">
        </textarea>
            </div>

            {{-- Action --}}
            <div class="flex justify-end gap-2 pt-2">

                {{-- Send --}}
                <button wire:click="sendReminder" wire:loading.attr="disabled"
                    class="px-4 py-2 text-sm rounded-xl bg-orange-500 text-white hover:bg-orange-600 transition flex items-center gap-2">

                    {{-- Loading --}}
                    <span wire:loading wire:target="sendReminder" class="animate-spin">
                        ⏳
                    </span>

                    <span wire:loading.remove wire:target="sendReminder">
                        Kirim Pesan
                    </span>
                </button>

            </div>

        </div>
    </x-modal>
</div>
